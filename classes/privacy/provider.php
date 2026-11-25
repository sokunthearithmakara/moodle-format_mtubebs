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

namespace format_mtubebs\privacy;

use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider implementation for format_mtubebs.
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * The user preference for the section to resume into on a return visit.
     *
     * Taken from the class that writes it, so the name has one definition.
     */
    public const SECTION_PREFERENCES_RESUME = \format_mtubebs\local\resume::PREFIX;

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            self::SECTION_PREFERENCES_RESUME,
            'privacy:metadata:preference:' . self::SECTION_PREFERENCES_RESUME
        );

        return $collection;
    }

    /**
     * Export all user preferences for this plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid): void {
        // Read the stored preferences rather than walking the user's enrolments. These are
        // written for any course the user opens, which includes courses reached through a
        // capability instead of an enrolment - admins, managers, teachers - and courses the
        // user has since been unenrolled from. Enumerating enrolments missed all of those.
        foreach (self::get_stored_course_preferences($userid) as $preferencename => $entry) {
            writer::export_user_preference(
                'format_mtubebs',
                $preferencename,
                $entry->value,
                get_string(
                    'preference:' . $entry->prefix,
                    'format_mtubebs',
                    self::get_course_label($entry->courseid)
                )
            );
        }
    }

    /**
     * Stored per-course preferences for a user, keyed by preference name.
     *
     * @param int $userid User id.
     * @return \stdClass[] Objects with prefix, courseid and value.
     */
    private static function get_stored_course_preferences(int $userid): array {
        global $DB;

        $found = [];
        $prefix = self::SECTION_PREFERENCES_RESUME;
        $like = $DB->sql_like('name', ':name');
        $records = $DB->get_records_select(
            'user_preferences',
            "userid = :userid AND {$like}",
            ['userid' => $userid, 'name' => $DB->sql_like_escape($prefix) . '%'],
            'name'
        );

        foreach ($records as $record) {
            $suffix = substr($record->name, strlen($prefix));
            // Only a bare course id belongs to this plugin.
            if ($suffix === '' || !ctype_digit($suffix)) {
                continue;
            }
            $found[$record->name] = (object) [
                'prefix' => $prefix,
                'courseid' => (int) $suffix,
                'value' => $record->value,
            ];
        }

        return $found;
    }

    /**
     * A human-readable label for a course that may no longer exist.
     *
     * @param int $courseid Course id.
     * @return string
     */
    private static function get_course_label(int $courseid): string {
        global $DB;

        $fullname = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        if ($fullname === false) {
            // Orphaned preference: the course is gone, so name it by id.
            return get_string('course') . ' ' . $courseid;
        }

        return format_string($fullname, true, ['context' => context_course::instance($courseid)]);
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // The data this plugin holds is in user preferences, which core exports on their own.
        return new contextlist();
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof context_course) {
            return;
        }

        // Nothing else removes these. Core only wipes user_preferences in the CONTEXT_USER
        // branch of its own provider.
        self::delete_course_preferences($context->instanceid);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if ($contextlist->count() === 0) {
            return;
        }

        $user = $contextlist->get_user();
        $courseids = [];

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $courseids[] = $context->instanceid;
            }
        }

        if ($courseids === []) {
            return;
        }

        self::delete_course_preferences($courseids, [$user->id]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();

        if ($userids === []) {
            return;
        }

        self::delete_course_preferences($context->instanceid, $userids);
    }

    /**
     * Remove this plugin's per-course preferences.
     *
     * @param int|int[] $courseids Course id or ids.
     * @param int[] $userids Restrict to these users; all users when empty.
     */
    private static function delete_course_preferences($courseids, array $userids = []): void {
        global $DB;

        $names = [];
        foreach ((array) $courseids as $courseid) {
            $names[] = self::SECTION_PREFERENCES_RESUME . (int) $courseid;
        }

        if ($names === []) {
            return;
        }

        [$namesql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED, 'pref');
        $select = "name {$namesql}";

        if ($userids !== []) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
            $select .= " AND userid {$usersql}";
            $params += $userparams;
        }

        $DB->delete_records_select('user_preferences', $select, $params);
    }
}
