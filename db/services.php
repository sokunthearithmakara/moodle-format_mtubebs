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
 * External functions and service declaration for Course Activity
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    format_mtubebs
 * @category   webservice
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'format_mtubebs_get_content_html' => [
        'classname'   => format_mtubebs\external\get_content_html::class,
        'description' => 'Get content html for a course',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'format_mtubebs_get_section_html' => [
        'classname'   => format_mtubebs\external\get_section_html::class,
        'description' => 'Get section html for a course',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'format_mtubebs_get_cms' => [
        'classname'   => format_mtubebs\external\get_cms::class,
        'description' => 'Get course modules for a course',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'format_mtubebs_get_user_grade_report' => [
        'classname'     => 'format_mtubebs\external\get_user_grade_report',
        'methodname'    => 'execute',
        'description'   => 'Returns user grade report HTML for the grade modal.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'gradereport/user:view',
    ],
    'format_mtubebs_set_course_image' => [
        'classname'     => format_mtubebs\external\set_course_image::class,
        'methodname'    => 'execute',
        'description'   => 'Replace the course overview image.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'moodle/course:update, moodle/course:changesummary',
    ],
];

$services = [];
