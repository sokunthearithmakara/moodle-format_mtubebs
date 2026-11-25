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

namespace format_mtubebs\local;

/**
 * Tags, competencies and outcomes of a course's activities, for the filters of the activity search.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_links {
    /**
     * Tags linked to course modules, keyed by cmid.
     *
     * @param int[] $cmids
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    public static function tags(array $cmids): array {
        global $CFG, $DB;

        if (!$cmids || empty($CFG->usetags) || !\core_tag_tag::is_enabled('core', 'course_modules')) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        $rows = $DB->get_records_sql(
            "SELECT ti.id, ti.itemid AS cmid, t.id AS tagid, t.name
               FROM {tag_instance} ti
               JOIN {tag} t ON t.id = ti.tagid
              WHERE ti.component = :component
                AND ti.itemtype = :itemtype
                AND ti.itemid {$insql}
           ORDER BY t.name ASC",
            $params + ['component' => 'core', 'itemtype' => 'course_modules']
        );

        $links = [];
        foreach ($rows as $row) {
            $links[(int) $row->cmid][] = ['id' => (int) $row->tagid, 'name' => (string) $row->name];
        }
        return $links;
    }

    /**
     * Competencies linked to course modules, keyed by cmid.
     *
     * @param int[] $cmids
     * @param \context $context Context the names are formatted in.
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    public static function competencies(array $cmids, \context $context): array {
        global $DB;

        if (!$cmids || !class_exists(\core_competency\api::class) || !\core_competency\api::is_enabled()) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        $rows = $DB->get_records_sql(
            "SELECT cmc.id, cmc.cmid, c.id AS competencyid, c.shortname, c.idnumber
               FROM {competency_modulecomp} cmc
               JOIN {competency} c ON c.id = cmc.competencyid
              WHERE cmc.cmid {$insql}
           ORDER BY c.shortname ASC",
            $params
        );

        $links = [];
        foreach ($rows as $row) {
            $links[(int) $row->cmid][] = [
                'id' => (int) $row->competencyid,
                'name' => self::format_name((string) $row->shortname, (string) $row->idnumber, $context),
            ];
        }
        return $links;
    }

    /**
     * Outcomes linked to course modules, keyed by cmid.
     *
     * @param int[] $cmids
     * @param \context $context Context the names are formatted in.
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    public static function outcomes(array $cmids, \context $context): array {
        global $CFG, $DB;

        if (!$cmids || empty($CFG->enableoutcomes)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        $rows = $DB->get_records_sql(
            "SELECT DISTINCT gi.id, cm.id AS cmid, go.id AS outcomeid, go.shortname, go.fullname
               FROM {grade_items} gi
               JOIN {course_modules} cm
                 ON cm.course = gi.courseid
                AND cm.instance = gi.iteminstance
                AND cm.deletioninprogress = 0
               JOIN {modules} m ON m.id = cm.module AND m.name = gi.itemmodule
               JOIN {grade_outcomes} go ON go.id = gi.outcomeid
              WHERE gi.itemtype = 'mod'
                AND cm.id {$insql}
           ORDER BY go.shortname ASC",
            $params
        );

        $links = [];
        foreach ($rows as $row) {
            $links[(int) $row->cmid][] = [
                'id' => (int) $row->outcomeid,
                'name' => self::format_name((string) $row->fullname, (string) $row->shortname, $context),
            ];
        }
        return $links;
    }

    /**
     * Display label: "Name (code)" when there is a code.
     *
     * @param string $name
     * @param string $code
     * @param \context $context
     * @return string
     */
    private static function format_name(string $name, string $code, \context $context): string {
        $name = format_string($name, true, ['context' => $context]);
        $code = trim($code);
        return $code === '' ? $name : $name . ' (' . format_string($code, true, ['context' => $context]) . ')';
    }
}
