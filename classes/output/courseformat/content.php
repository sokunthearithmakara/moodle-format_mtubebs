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

namespace format_mtubebs\output\courseformat;

/**
 * Class content
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends \format_topics\output\courseformat\content {
    /**
     * Returns the output class template path.
     *
     * This method redirects the default template when the course content is rendered.
     * @param \renderer_base $renderer the renderer
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        global $PAGE;
        if ($PAGE->user_is_editing()) {
            return 'core_courseformat/local/content';
        }
        return 'format_mtubebs/local/content';
    }
}
