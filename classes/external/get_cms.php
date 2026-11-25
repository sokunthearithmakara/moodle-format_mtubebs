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
use format_mtubebs\local\activity_links;

/**
 * Course module list data for section rendering.
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_cms extends external_api {
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
        global $CFG, $OUTPUT;
        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'data' => $data,
        ]);
        $data = json_decode($data);
        $courseid = $data->courseid;
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_course_login($courseid);
        $modinfo = get_fast_modinfo($courseid);
        $sections = $modinfo->get_section_info_all();
        // The section each subsection is in.
        $subsectioninstances = $modinfo->get_instances_of('subsection');
        $parentnums = [];
        foreach ($subsectioninstances as $subsection) {
            $thissection = \mod_subsection\manager::create_from_coursemodule($subsection)->get_delegated_section_info();
            $parentnums[$thissection->section] = $subsection->sectionnum;
        }

        $cms = $modinfo->get_cms();
        $modarray = [];
        $modnamelabels = [];
        foreach ($cms as $cm) {
            $mod = [];
            // Not getting the deleted modules.
            if ($cm->deletioninprogress) {
                continue;
            }

            // Not getting the hidden from student modules.
            if (!$cm->is_visible_on_course_page() && !$cm->uservisible) {
                continue;
            }

            // Not getting modules without view page.
            if (!$cm->has_view()) {
                continue;
            }

            $mod["name"] = format_string($cm->name);
            $mod["url"] = isset($cm->url) ? $cm->url->__toString() :
                $CFG->wwwroot . '/mod/' . $cm->modname . '/view.php?id=' . $cm->id;
            $modname = $cm->modname;
            $mod["icon"] = $OUTPUT->image_icon('monologo', get_string('pluginname', $modname), $modname);
            $mod["id"] = $cm->id;
            $mod["sectionnum"] = $cm->sectionnum;
            $mod["sectionname"] = format_string(\get_section_name($courseid, $mod["sectionnum"]));
            $section = $sections[$cm->sectionnum];
            if ($section->is_delegated()) {
                $parentnum = $parentnums[$cm->sectionnum] ?? null;
                if ($parentnum !== null) {
                    $mod["sectionnum"] = $parentnum;
                    $mod["sectionname"] = format_string(\get_section_name($courseid, $mod["sectionnum"])) . ' / '
                        . format_string(\get_section_name($courseid, $cm->sectionnum));
                }
            }
            $mod["modname"] = $cm->modname;
            $mod['visible'] = $cm->uservisible;
            // Get the modname label.
            if (!isset($modnamelabels[$modname])) {
                $modnamelabels[$modname] = get_string('pluginname', $modname);
            }
            $mod["modnamelabel"] = $modnamelabels[$modname];
            if ($mod['sectionnum'] == 0) {
                $mod['sectionname'] = '';
            }
            array_push($modarray, $mod);
        }
        $modarray = self::attach_search_links($modarray, (int) $courseid);
        return [
            'data' => json_encode($modarray),
        ];
    }

    /**
     * Attach tag, competency, and outcome links used by the section-nav search.
     *
     * @param array $mods Activity rows keyed for the search modal.
     * @param int $courseid
     * @return array
     */
    private static function attach_search_links(array $mods, int $courseid): array {
        if (!$mods || $courseid <= 0) {
            return $mods;
        }

        $cmids = array_map('intval', array_column($mods, 'id'));
        $context = \context_course::instance($courseid);
        $competencies = activity_links::competencies($cmids, $context);
        $outcomes = activity_links::outcomes($cmids, $context);
        $tags = activity_links::tags($cmids);

        foreach ($mods as $index => $mod) {
            $cmid = (int) $mod['id'];
            $mods[$index]['tags'] = $tags[$cmid] ?? [];
            $mods[$index]['tagids'] = self::id_list($mods[$index]['tags']);
            $mods[$index]['competencies'] = $competencies[$cmid] ?? [];
            $mods[$index]['competencyids'] = self::id_list($mods[$index]['competencies']);
            $mods[$index]['outcomes'] = $outcomes[$cmid] ?? [];
            $mods[$index]['outcomeids'] = self::id_list($mods[$index]['outcomes']);
        }

        return $mods;
    }

    /**
     * Comma-separated ids for a data attribute.
     *
     * @param array $items
     * @return string
     */
    private static function id_list(array $items): string {
        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return implode(',', $ids);
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
