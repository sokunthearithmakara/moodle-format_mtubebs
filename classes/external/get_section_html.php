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

namespace format_mtubebs\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Rendered HTML for a course section.
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_section_html extends external_api {
    /**
     * Describes the parameters for format_mtubebs
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'data' => new external_value(PARAM_RAW, 'The data of the response'),
        ]);
    }

    /**
     * Implementation of web service format_mtubebs
     *
     * @param string $data The data of the response
     * @return array
     */
    public static function execute($data) {
        global $CFG, $PAGE, $OUTPUT;
        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'data' => $data,
        ]);
        $data = json_decode($data);
        $courseid = $data->courseid;
        $sectionnum = isset($data->sectionnum) ? (int) $data->sectionnum : -1;
        $sectionid = (int) ($data->sectionid ?? 0);
        $cmid = (int) ($data->cmid ?? 0);
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_course_login($courseid);
        require_once($CFG->dirroot . '/course/format/lib.php');
        require_once($CFG->dirroot . '/course/format/mtubebs/lib.php');

        $PAGE->set_context($context);
        format_mtubebs_set_ajax_page_url((int) $courseid, (int) $sectionid ?: null);

        $format = course_get_format($courseid);
        $renderer = $format->get_renderer($PAGE);
        $modinfo = get_fast_modinfo($courseid);
        $formatoptions = $format->get_format_options();
        $course = $format->get_course();
        $cmsummaryclass = $format->get_output_classname('content\\section\\cmsummary');

        $canmanage = has_capability('moodle/course:update', $context);

        if ($cmid > 0) {
            // A link to an activity: show the section it is in.
            $sectioninfo = self::get_section_of_activity($modinfo, $cmid);
            $sectionid = $sectioninfo ? (int) $sectioninfo->id : 0;
        } else if ($sectionid <= 0 && $sectionnum >= 0) {
            $sectioninfo = $modinfo->get_section_info($sectionnum);
            $sectionid = $sectioninfo ? (int) $sectioninfo->id : 0;
        }
        if ($sectionid > 0) {
            course_section_view($context, $sectionid);
        }

        // Get section list.
        $sections = [];
        $sectioninfos = [];
        foreach (format_mtubebs_get_sectioninfo_sequence($course, $formatoptions) as $section) {
            if ($format->is_section_visible($section)) {
                $sectioninfos[$section->id] = $section;
            } else {
                continue;
            }

            $cmsummary = new $cmsummaryclass($format, $section);
            $summary = $cmsummary->export_for_template($renderer);
            $progress = ($summary->total ?? 0) > 0 ?
                round(($summary->complete ?? 0) / $summary->total * 100) : 0;

            $sections[] = [
                'id' => $section->id,
                'section' => $section->section,
                'name' => get_section_name($courseid, $section),
                'shortname' => $section->shortname ? format_string($section->shortname) : get_section_name($courseid, $section),
                'restricted' => !$section->available && !empty($section->availableinfo),
                'isdelegated' => $section->is_delegated(),
                'visible' => $section->visible == 1,
                'current' => ($section->section == $course->marker),
                'completed' => $progress == 100,
                'hascompletion' => $summary->showcompletion,
                'isempty' => $summary->isempty,
                'showsectionnumber' => $formatoptions['showsectionnumber'] == 1,
            ];
        }

        $return = new \stdClass();
        $return->section0state = self::get_section0_state($modinfo);
        $section = $sectioninfos[$sectionid] ?? null;
        if (!$section) {
            $return->html = get_string('sectionnotavailable', 'format_mtubebs');
            $return->sections = $sections;
            return [
                'data' => json_encode($return),
            ];
        }
        // Remember where the learner got to. This runs on every section switch,
        // including prev/next and the browser's back button, so it needs no request of
        // its own. resume::remember() decides whether anything is stored.
        \format_mtubebs\local\resume::remember($courseid, $section);

        if (!empty($section->hidesectionheader) && !$canmanage) {
            $return->sectionhead = '';
        } else {
            $return->sectionhead = $OUTPUT->render_from_template('format_mtubebs/sectionhead', [
                'section' => $section->section,
                'name' => get_section_name($courseid, $section),
                'visible' => $section->visible == 1,
                'current' => ($section->section == $course->marker),
                'currentsectionlabel' => $formatoptions['currentsectionlabel'] ?
                    format_string($formatoptions['currentsectionlabel']) : get_string('current', 'format_mtubebs'),
                'showsectionnumber' => $formatoptions['showsectionnumber'] == 1,
            ]);
        }
        $return->sections = $sections;
        $return->sectionid = (int) $section->id;
        $return->sectionnum = (int) $section->section;

        $outputclass = $format->get_output_classname('content\\section');
        $return->html = $renderer->render(new $outputclass($format, $section));

        return [
            'data' => json_encode($return),
        ];
    }

    /**
     * The section of the course page an activity is shown in.
     *
     * @param \course_modinfo $modinfo
     * @param int $cmid
     * @return \section_info|null The section, its parent for an activity in a subsection, null when there is none to show.
     */
    protected static function get_section_of_activity(\course_modinfo $modinfo, int $cmid): ?\section_info {
        $cm = $modinfo->cms[$cmid] ?? null;
        if (!$cm || !$cm->uservisible) {
            return null;
        }
        $section = $cm->get_section_info();
        if ($section->is_delegated()) {
            $section = $section->get_component_instance()?->get_parent_section();
        }
        return $section && $section->section > 0 ? $section : null;
    }

    /**
     * What the user has access to and has completed in section 0.
     *
     * The page reloads section 0 only when what the user does in another section changes this,
     * such as a certificate that the completion of an activity unlocks.
     *
     * @param \course_modinfo $modinfo
     * @return string A hash.
     */
    protected static function get_section0_state(\course_modinfo $modinfo): string {
        $completion = new \completion_info($modinfo->get_course());
        $istracked = $completion->is_tracked_user($modinfo->get_user_id());
        $state = [];
        $cms = $modinfo->get_section_info(0)->get_sequence_cm_infos();
        while ($cm = array_shift($cms)) {
            $state[] = [$cm->id, $cm->uservisible, $cm->availableinfo];
            // The buttons of manual completion are kept up to date on the page.
            if ($istracked && $completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {
                $state[] = $completion->get_data($cm, true)->completionstate;
            }
            // The activities of a subsection.
            if ($subsection = $cm->get_delegated_section_info()) {
                $state[] = [$subsection->id, $subsection->uservisible, $subsection->availableinfo];
                array_push($cms, ...$subsection->get_sequence_cm_infos());
            }
        }
        return sha1(json_encode($state));
    }

    /**
     * Describes the return value for format_mtubebs
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'data' => new external_value(PARAM_RAW, 'The data of the response'),
        ]);
    }
}
