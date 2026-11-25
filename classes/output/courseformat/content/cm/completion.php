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

namespace format_mtubebs\output\courseformat\content\cm;

use stdClass;
use core_course\output\activity_completion;
use core_completion\cm_completion_details;

/**
 * Base class to render course module completion.
 *
 * @package   format_mtubebs
 * @copyright 2023 Mikel Martin <mikel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion extends \core_courseformat\output\local\content\cm\completion {
    /** @var bool $smallbutton if the button is rendered small (like in course page). */
    private bool $smallbutton = true;

    /**
     * Set if the button is rendered small.
     *
     * By default, the button is rendered small like in the course page. However,
     * in other pages like the activities overview, the button is rendered like
     * a regular button.
     *
     * @param bool $smallbutton if the button is rendered small (like in course page).
     */
    public function set_smallbutton(bool $smallbutton): void {
        $this->smallbutton = $smallbutton;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass|null data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): ?stdClass {
        global $USER, $PAGE;
        if ($PAGE->user_is_editing()) {
            return parent::export_for_template($output);
        }

        if ($this->mod->modname == 'subsection') {
            return parent::export_for_template($output);
        }

        if ($this->smallbutton === false) {
            return parent::export_for_template($output);
        }

        if (!$this->format->show_activity_editor_options($this->mod)) {
            return null;
        }

        $course = $this->mod->get_course();

        $showcompletionconditions = $course->showcompletionconditions == COMPLETION_SHOW_CONDITIONS;
        $completiondetails = cm_completion_details::get_instance($this->mod, $USER->id, $showcompletionconditions);

        $showcompletioninfo = $completiondetails->has_completion() &&
            ($showcompletionconditions || $completiondetails->show_manual_completion());
        if (!$showcompletioninfo) {
            return null;
        }

        $completion = new activity_completion($this->mod, $completiondetails, $this->smallbutton);
        $completiondata = $completion->export_for_template($output);

        if ($completiondata->isautomatic || ($completiondata->ismanual && !$completiondata->istrackeduser)) {
            $completiondata->completiondialog = $this->get_completion_dialog($output, $completiondata);
        }

        return $completiondata;
    }

    /**
     * Get the completion dialog.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @param stdClass $completioninfo the completion info
     * @return array the completion dialog exported for template
     */
    protected function get_completion_dialog(\renderer_base $output, stdClass $completioninfo): array {
        global $PAGE;

        if ($PAGE->user_is_editing()) {
            return parent::get_completion_dialog($output, $completioninfo);
        }

        if ($this->mod->modname == 'subsection') {
            return parent::get_completion_dialog($output, $completioninfo);
        }

        if ($this->smallbutton === false) {
            return parent::get_completion_dialog($output, $completioninfo);
        }

        $completioninfo->hasconditions = $completioninfo->ismanual || count($completioninfo->completiondetails) > 0;
        $dialogcontent = $output->render_from_template('core_courseformat/local/content/cm/completion_dialog', $completioninfo);

        $buttoncontent = '<i class="far fa-square fa-fw fa-xl" aria-hidden="true" title="'
            . get_string('completion', 'completion') . '"></i>';
        $buttonclass = 'text-muted';
        if ($completioninfo->istrackeduser) {
            $buttoncontent = get_string('todo', 'completion');
            if ($completioninfo->overallcomplete) {
                $buttoncontent = '<i class="fa fa-square-check fa-fw fa-xl" aria-hidden="true" title="'
                    . get_string('todo', 'completion') . '"></i>';
                $buttonclass = 'text-success';
            } else {
                $buttoncontent = '<i class="far fa-square fa-fw fa-xl" aria-hidden="true" title="'
                    . get_string('todo', 'completion') . '"></i>';
                $buttonclass = 'text-muted';
            }
        }

        return [
            'completioncontent' => $dialogcontent,
            'buttoncontent' => $buttoncontent,
            'buttonclasses' => $buttonclass,
        ];
    }
}
