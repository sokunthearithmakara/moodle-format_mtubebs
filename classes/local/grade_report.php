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

use context_course;
use context_user;
use core_user;
use grade_plugin_return;
use gradereport_user\report\user as user_report;
use moodle_exception;
use stdClass;

/**
 * User grade report HTML for the MooTube Basic grade modal.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_report {
    /** @var string Body classes applied to the grade report page. */
    public const BODY_CLASSES = 'path-grade-report-user path-grade-report-user-index grade-report-user pagelayout-report';

    /** @var string Format option value: show the core user report table. */
    public const STYLE_CORE = 'core';

    /** @var string Format option value: show the custom grade table. */
    public const STYLE_CUSTOM = 'custom';

    /**
     * Whether a course shows the custom grade table in the user report.
     *
     * Only MooTube Basic courses have the option; courses in other formats always get the core table.
     *
     * @param stdClass $course
     * @return bool
     */
    public static function uses_custom_table(stdClass $course): bool {
        if (($course->format ?? '') !== 'mtubebs') {
            return false;
        }
        $options = course_get_format($course)->get_format_options();
        return ($options['gradetablestyle'] ?? self::STYLE_CORE) === self::STYLE_CUSTOM;
    }

    /**
     * Build the user grade report HTML fragment.
     *
     * @param int $courseid Course id.
     * @param int $userid User id, 0 means the current user.
     * @return array{html: string, reportcontainerid: string, bodyclasses: string}
     */
    public static function get_report_html(int $courseid, int $userid = 0): array {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/user/lib.php');

        $course = get_course($courseid);
        $context = context_course::instance($course->id);

        require_capability('gradereport/user:view', $context);

        if ($userid === 0) {
            $userid = (int) $USER->id;
        } else {
            if (!$DB->get_record('user', ['id' => $userid, 'deleted' => 0]) || isguestuser($userid)) {
                throw new moodle_exception('invaliduser');
            }
            core_user::require_active_user(core_user::get_user($userid, '*', MUST_EXIST));
        }

        self::validate_grade_access($course, $context, $userid);
        if ($userid != $USER->id && !groups_user_groups_visible($course, $userid)) {
            throw new moodle_exception('notingroup');
        }

        grade_regrade_final_grades($course->id);

        $gpr = new grade_plugin_return([
            'type' => 'report',
            'plugin' => 'user',
            'courseid' => $course->id,
            'courseidnumber' => $course->idnumber,
            'userid' => $userid,
        ]);

        $viewasuser = self::resolve_view_as_user($context, $userid);

        $html = '';
        $reportcontainerid = '';

        $currentgroup = groups_get_course_group($course);
        if ($currentgroup && !groups_is_member($currentgroup, $userid)) {
            $html = $OUTPUT->notification(get_string('groupusernotmember', 'error'), 'warning', false);
            return [
                'html' => $html,
                'reportcontainerid' => $reportcontainerid,
                'bodyclasses' => self::BODY_CLASSES,
            ];
        }

        if (self::uses_custom_table($course)) {
            // In a web service $OUTPUT is still the bootstrap proxy, not a renderer_base.
            $renderer = $PAGE->get_renderer('core');
            $html = $renderer->render(new \format_mtubebs\output\grade_table($course, $userid, $viewasuser));
            \gradereport_user\event\grade_report_viewed::create([
                'context' => $context,
                'courseid' => $course->id,
                'relateduserid' => $userid,
            ])->trigger();
            return [
                'html' => $html,
                'reportcontainerid' => "user-report-{$userid}",
                'bodyclasses' => self::BODY_CLASSES . ' mtubebs-gradetable-report',
            ];
        }

        $report = new user_report($course->id, $gpr, $context, $userid, $viewasuser);
        if ($report->fill_table()) {
            $html = $report->print_table(true);
            $reportcontainerid = "user-report-{$userid}";
            $report->viewed();
        }

        return [
            'html' => $html,
            'reportcontainerid' => $reportcontainerid,
            'bodyclasses' => self::BODY_CLASSES,
        ];
    }

    /**
     * Validate whether the current user can view the requested grade report.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param int $userid
     * @return void
     */
    protected static function validate_grade_access(stdClass $course, context_course $context, int $userid): void {
        global $USER;

        $access = false;

        if (has_capability('moodle/grade:viewall', $context)) {
            $access = true;
        } else if ($userid == $USER->id && has_capability('moodle/grade:view', $context) && $course->showgrades) {
            $access = true;
        } else if (has_capability('moodle/grade:viewall', context_user::instance($userid)) && $course->showgrades) {
            $access = true;
        }

        if (!$access) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error');
        }
    }

    /**
     * Resolve the view-as-user flag used by the core user grade report.
     *
     * @param context_course $context
     * @param int $userid
     * @return bool
     */
    protected static function resolve_view_as_user(context_course $context, int $userid): bool {
        global $USER;

        if ($userid == $USER->id) {
            return false;
        }

        if (has_capability('moodle/grade:viewall', $context)) {
            $userview = get_user_preferences('gradereport_user_view_user', GRADE_REPORT_USER_VIEW_USER);
            return $userview == GRADE_REPORT_USER_VIEW_USER;
        }

        return true;
    }
}
