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
 * MooTube Basic course format admin settings definitions.
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'format_mtubebs',
        get_string('pluginname', 'format_mtubebs')
    );

    $settings->add(new admin_setting_configtextarea(
        'format_mtubebs/formatoptions',
        get_string('formatoptions', 'format_mtubebs'),
        '<button class="btn btn-secondary " type="button" name="formatoptions" data-origin="settingspage">
            <i class="fa fa-cog fa-fw"></i> ' . get_string('edit', 'format_mtubebs') . '
        </button>',
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_mtubebs/distractionfreeforall',
        get_string('distractionfreeforall', 'format_mtubebs'),
        get_string('distractionfreeforalldesc', 'format_mtubebs'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'format_mtubebs/topofscrollnode',
        get_string('topofscrollnode', 'format_mtubebs'),
        get_string('topofscrollnodedescription', 'format_mtubebs'),
        '#topofscroll, #region-main-box',
        PARAM_TEXT,
    ));

    $settings->add(new admin_setting_configtext(
        'format_mtubebs/pagenode',
        get_string('pagenode', 'format_mtubebs'),
        get_string('pagenodedescription', 'format_mtubebs'),
        '#page',
        PARAM_TEXT,
    ));

    $settings->add(new admin_setting_configtext(
        'format_mtubebs/secondarymenunode',
        get_string('secondarymenunode', 'format_mtubebs'),
        get_string('secondarymenunodedesc', 'format_mtubebs'),
        '.secondary-navigation',
        PARAM_TEXT,
    ));

    $settings->add(new admin_setting_configtext(
        'format_mtubebs/drawertoggles',
        get_string('drawertoggles', 'format_mtubebs'),
        get_string('drawertogglesdesc', 'format_mtubebs'),
        '.drawer-toggles',
        PARAM_TEXT,
    ));
}
