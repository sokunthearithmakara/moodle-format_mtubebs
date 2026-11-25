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

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use format_mtubebs\local\grade_report;

/**
 * Return user grade report HTML for the MooTube Basic grade modal.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_grade_report extends external_api {
    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'userid' => new external_value(PARAM_INT, 'User id, 0 means current user', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get user grade report HTML.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function execute(int $courseid, int $userid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        return grade_report::get_report_html($params['courseid'], $params['userid']);
    }

    /**
     * Webservice return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Grade report HTML fragment'),
            'reportcontainerid' => new external_value(PARAM_ALPHANUMEXT, 'User report container id'),
            'bodyclasses' => new external_value(PARAM_RAW, 'Body classes for grade report styling'),
        ]);
    }
}
