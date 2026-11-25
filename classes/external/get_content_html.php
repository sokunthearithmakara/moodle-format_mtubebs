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

/**
 * Rendered HTML for the section content area.
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_content_html extends external_api {
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
        global $CFG, $PAGE;
        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'data' => $data,
        ]);
        $data = json_decode($data);
        $courseid = $data->courseid;
        $context = \context_course::instance($courseid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/course/format/lib.php');

        $PAGE->set_context($context);
        $format = course_get_format($courseid);
        format_mtubebs_set_ajax_page_url((int) $courseid);
        $modinfo = get_fast_modinfo($courseid);

        $return = new \stdClass();
        $renderer = $format->get_renderer($PAGE);
        // Output course content.
        $section = $modinfo->get_section_info(0);
        $outputclass = $format->get_output_classname('content\\section');
        $widget = new $outputclass($format, $section);
        $return->html = $renderer->render($widget);

        return [
            'data' => json_encode($return),
        ];
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
