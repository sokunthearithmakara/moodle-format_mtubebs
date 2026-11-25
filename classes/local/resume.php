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
 * Remembers the section a learner was last on, so a return visit resumes there.
 *
 * The value is a single section id in a per-course user preference.
 *
 * Nothing is stored unless the course has the option turned on, so a course that
 * never uses the feature collects no data about its learners.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resume {
    /**
     * Preference name prefix. The course id is appended with no separator.
     */
    public const PREFIX = 'mtubebslastsection';

    /** @var bool[] Per-request cache of the course option, keyed by course id. */
    protected static $enabled = [];

    /**
     * Whether this course asks for its sections to be resumable.
     *
     * Memoised because get_format_options() decodes the options blob and formats
     * strings out of it on every call, and both entry points here run on requests
     * that have already asked for it.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function is_enabled(int $courseid): bool {
        if (!isset(self::$enabled[$courseid])) {
            $options = course_get_format($courseid)->get_format_options();
            self::$enabled[$courseid] = !empty($options['resumesection']);
        }

        return self::$enabled[$courseid];
    }

    /**
     * Forget the memoised course option.
     *
     * Only needed by tests, which change the option inside a single request.
     */
    public static function reset_cache(): void {
        self::$enabled = [];
    }

    /**
     * Store the section the user is now on.
     *
     * @param int $courseid Course id.
     * @param \section_info $section Section the user just opened.
     */
    public static function remember(int $courseid, \section_info $section): void {
        global $USER;

        // Section 0 is the course info panel rather than a place to come back to, and
        // a delegated subsection is never a navigation target in its own right.
        if ($section->section <= 0 || $section->is_delegated()) {
            return;
        }

        // Core keeps nothing on disk for these, so set_user_preference() would be a
        // no-op. Returning early skips the pointless work rather than relying on that.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (!self::is_enabled($courseid)) {
            return;
        }

        $name = self::PREFIX . $courseid;
        $stored = get_user_preferences($name, null, $USER->id);

        // Core short-circuits an unchanged value, but only after a SELECT to find it.
        // Preferences are already in memory, so comparing here first makes re-opening
        // the same section cost no queries at all.
        if ((string) $stored === (string) $section->id) {
            return;
        }

        set_user_preference($name, $section->id, $USER->id);
    }

    /**
     * The section to resume into, or null to leave the course on its normal default.
     *
     * The stored id is never trusted. Between visits the section may have been
     * deleted, hidden, restricted behind a condition the learner no longer meets, or
     * the preference may be left over from a course that reused the id. Anything that
     * does not resolve to a section this user may currently see is discarded here, so
     * an unusable id never reaches the browser.
     *
     * @param int $courseid Course id.
     * @return int|null Section id.
     */
    public static function recall(int $courseid): ?int {
        global $USER;

        if (!isloggedin() || isguestuser() || !self::is_enabled($courseid)) {
            return null;
        }

        $stored = (int) get_user_preferences(self::PREFIX . $courseid, 0, $USER->id);
        if ($stored <= 0) {
            return null;
        }

        $modinfo = get_fast_modinfo($courseid);
        $section = $modinfo->get_section_info_by_id($stored);

        // Lookups run against this course's own modinfo, so an id left over from a
        // different course simply comes back empty.
        if (!$section || $section->section <= 0 || $section->is_delegated()) {
            return null;
        }

        if (!$section->uservisible) {
            return null;
        }

        return (int) $section->id;
    }
}
