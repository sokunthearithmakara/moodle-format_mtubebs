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
 * Hook callbacks for Interactive Video
 *
 * @package    format_mtubebs
 * @copyright  2024 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \format_mtubebs\hook_callbacks::class . '::init_plugin_admin_settings',
        'priority' => 0,
    ],
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \format_mtubebs\hook_callbacks::class . '::redirect_to_course_page',
        'priority' => 0,
    ],
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => \format_mtubebs\hook_callbacks::class . '::render_activity_nav',
        'priority' => 0,
    ],
];
