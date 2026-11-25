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

namespace format_mtubebs\output;

/**
 * Renderer for format_mtubebs
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \format_topics\output\renderer {
    /**
     * In Moodle 4.5 we may have sub-sections.
     *
     * @param \renderable $widget
     * @return bool|string
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function render_delegatedsection($widget) {
        global $CFG;
        $data = $widget->export_for_template($this);
        $format = course_get_format($this->page->course);
        $section = $format->get_section($data->num);
        $data->contentcollapsed = $section->showcollapsed == 1;
        $data->preventcollapsing = $section->showcollapsed == -1;
        // The page is grey with white subsections from Moodle 5.3, and white before it.
        $data->shaded = $CFG->branch < 503;
        if ($this->page->user_is_editing()) {
            $template = 'core_courseformat/local/content/delegatedsection';
        } else {
            $template = 'format_mtubebs/local/content/delegatedsection';
        }

        return $this->render_from_template($template, $data);
    }
}
