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
 * Contains the default section course format output class.
 *
 * @package   format_mtubebs
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mtubebs\output\courseformat\content;

/**
 * Base class to render a course section.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends \format_topics\output\courseformat\content\section {
    /**
     * Returns true if the current section should be shown collapsed.
     *
     * @return bool
     */
    protected function is_section_collapsed(): bool {
        global $PAGE;
        $collapsed = parent::is_section_collapsed();
        if ($PAGE->user_is_editing()) {
            return $collapsed;
        }

        if ($this->section->is_delegated()) {
            $collapsed = false;
        }
        return $collapsed;
    }

    /**
     * Get the template name.
     *
     * @param \renderer_base $renderer Renderer base.
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        global $PAGE;
        if ($PAGE->user_is_editing()) {
            return 'core_courseformat/local/content/section';
        }
        // Section 0 is in the side panel.
        if ($this->section->section == 0) {
            return 'format_mtubebs/local/content/initialsection';
        }
        return 'format_mtubebs/local/content/section';
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $data = parent::export_for_template($output);
        if (!empty($this->section->hidesectiondescription)) {
            $data->summary = false;
        }
        return $data;
    }
}
