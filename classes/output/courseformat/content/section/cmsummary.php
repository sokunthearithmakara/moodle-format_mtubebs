<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains the default activities summary (used for singlesection format).
 *
 * @package   format_mtubebs
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mtubebs\output\courseformat\content\section;

use completion_info;
use stdClass;

/**
 * Base class to render a course section summary.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmsummary extends \core_courseformat\output\local\content\section\cmsummary {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        [$complete, $total, $showcompletion, $empty] = $this->calculate_section_stats();

        return (object)[
            'showcompletion' => $showcompletion,
            'total' => $total,
            'complete' => $complete,
            'isempty' => $empty,
        ];
    }

    /**
     * Calculate the activities count of the current section.
     *
     * @return array [completed activities, total of activities, show completion, is empty]
     */
    protected function calculate_section_stats(): array {
        global $USER;

        $format = $this->format;
        $course = $format->get_course();
        $section = $this->section;
        $userid = (int) $USER->id;
        $modinfo = $format->get_modinfo();
        $completioninfo = new completion_info($course);

        $total = 0;
        $complete = 0;

        $cmids = $modinfo->sections[$section->section] ?? [];

        $cancomplete = $userid > 0 && !isguestuser($userid);
        $showcompletion = false;

        // A module awaiting deletion is not content. The loop below already skips those,
        // so this test has to agree with it - otherwise a section whose last activity is
        // mid-deletion still counts as non-empty and never shows the empty indicator.
        $empty = true;
        foreach ($cmids as $cmid) {
            $pending = $modinfo->cms[$cmid] ?? null;
            if ($pending && !$pending->deletioninprogress) {
                $empty = false;
                break;
            }
        }

        foreach ($this->get_section_activities($modinfo) as $thismod) {
            if (!$thismod->uservisible) {
                continue;
            }
            if ($thismod->modname == 'label' && $completioninfo->is_enabled($thismod) == COMPLETION_TRACKING_NONE) {
                continue;
            }
            if ($cancomplete && $completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
                $showcompletion = true;
                $total++;
                $completiondata = $completioninfo->get_data($thismod, false, $userid);
                if (
                    $completiondata->completionstate == COMPLETION_COMPLETE ||
                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS
                ) {
                    $complete++;
                }
            }
        }

        return [$complete, $total, $showcompletion, $empty];
    }

    /**
     * The activities of the section, with those of its subsections in place of the subsection.
     *
     * @param \course_modinfo $modinfo
     * @return \cm_info[]
     */
    private function get_section_activities(\course_modinfo $modinfo): array {
        $activities = [];
        foreach ($modinfo->sections[$this->section->section] ?? [] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->deletioninprogress) {
                continue;
            }
            if ($cm->modname !== 'subsection') {
                $activities[] = $cm;
                continue;
            }
            if (!$cm->uservisible) {
                continue;
            }
            $delegated = \mod_subsection\manager::create_from_coursemodule($cm)->get_delegated_section_info();
            foreach ($delegated?->get_sequence_cm_infos() ?? [] as $subcm) {
                if (!$subcm->deletioninprogress) {
                    $activities[] = $subcm;
                }
            }
        }
        return $activities;
    }
}
