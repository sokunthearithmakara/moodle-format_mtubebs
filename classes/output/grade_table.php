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

namespace format_mtubebs\output;

use cm_info;
use completion_info;
use core\activity_dates;
use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use format_mtubebs\local\grade_table\gradebook;
use grade_grade;
use grade_item;
use stdClass;

/**
 * The custom grade table of the user report: grade categories, activity and manual items, category totals and the
 * course total, each with its status, due date, weight and grade.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_table implements named_templatable, renderable {
    /** @var string[] activity date ids that count as the due date, by preference */
    protected const DUE_DATES = ['duedate', 'timeclose', 'timedue'];

    /** @var string[] aggregation statuses shown under the weight (an empty grade already shows as --) */
    protected const WEIGHT_NOTES = ['extra', 'dropped', 'excluded'];

    /** @var completion_info|null */
    protected ?completion_info $completion = null;

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param int $userid the user whose grades are shown
     * @param bool $viewasuser show the grades exactly as the user sees them
     */
    public function __construct(
        /** @var stdClass */
        protected stdClass $course,
        /** @var int */
        protected int $userid,
        /** @var bool */
        protected bool $viewasuser = false,
    ) {
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'format_mtubebs/grade_table';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->completion = new completion_info($this->course);
        $gradebook = new gradebook((int)$this->course->id, $this->userid, $this->viewasuser);

        $rows = [];
        $hasitems = false;
        foreach ($gradebook->get_rows() as $row) {
            if ($row['type'] === gradebook::ROW_CATEGORY) {
                $rows[] = [
                    'iscategory' => true,
                    'isitem' => false,
                    'istotal' => false,
                    'name' => $row['name'],
                    'depth' => $row['depth'],
                    'ishidden' => $row['ishidden'],
                ];
                continue;
            }
            $hasitems = $hasitems || $row['type'] === gradebook::ROW_ITEM;
            $rows[] = $this->export_grade_row($row);
        }

        return [
            'userid' => $this->userid,
            'rows' => $rows,
            'hasitems' => $hasitems,
        ];
    }

    /**
     * Exports the row of a grade item or total.
     *
     * @param array $row a row of gradebook::get_rows()
     * @return array
     */
    protected function export_grade_row(array $row): array {
        /** @var grade_item $item */
        $item = $row['item'];
        /** @var grade_grade $grade */
        $grade = $row['grade'];
        /** @var cm_info|null $cm */
        $cm = $row['cm'];
        $istotal = $row['type'] !== gradebook::ROW_ITEM;
        $hasvalue = !$row['gradehidden'] && !$item->needsupdate && $row['value'] !== null;
        $due = $cm && $cm->uservisible ? $this->get_due_date($cm) : null;

        $weightnote = null;
        if ($row['type'] !== gradebook::ROW_COURSETOTAL && in_array($row['weightstatus'], self::WEIGHT_NOTES)) {
            $weightnote = get_string('gradetableweight' . $row['weightstatus'], 'format_mtubebs');
        }

        // The course total has no parent to weigh in: show its percentage, as the core report's Percentage column does.
        $weight = $this->format_percent($row['weightearned']);
        if ($row['type'] === gradebook::ROW_COURSETOTAL && $hasvalue) {
            $weight = grade_format_gradevalue($row['value'], $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE);
        }

        return [
            'iscategory' => false,
            'isitem' => !$istotal,
            'istotal' => $istotal,
            'iscoursetotal' => $row['type'] === gradebook::ROW_COURSETOTAL,
            'id' => $item->id,
            'cmid' => $cm?->id,
            'depth' => $row['depth'],
            'name' => $cm ? $cm->get_formatted_name() : $row['name'],
            'url' => $cm && $cm->uservisible ? $cm->url?->out(false) : null,
            'type' => $row['typename'],
            'ishidden' => $row['ishidden'],
            'status' => $this->export_status($cm, $hasvalue ? $grade : null, $due),
            'due' => $due ? [
                'date' => userdate($due, get_string('strftimedateshortmonthabbr', 'langconfig')),
                'time' => userdate($due, get_string('strftimetime', 'langconfig')),
            ] : null,
            'weight' => $weight,
            'weightmax' => $row['weightpossible'] === null ? null : [
                'value' => $this->format_percent($row['weightpossible']),
                'label' => get_string('gradetablegrademax', 'format_mtubebs', $this->format_percent($row['weightpossible'])),
            ],
            'weightnote' => $weightnote,
            'grade' => $this->format_grade($row),
            'grademax' => $this->format_grademax($item),
            'feedback' => !$istotal ? $this->format_feedback($grade, $row['gradehidden']) : null,
        ];
    }

    /**
     * A weight from 0 to 1 as a percentage, or null.
     *
     * @param float|null $weight
     * @return string|null
     */
    protected function format_percent(?float $weight): ?string {
        return $weight === null ? null : get_string('percents', 'moodle', format_float($weight * 100, 2, true, true));
    }

    /**
     * The possible points of an item, or null when its grade is not shown as points (scales, percentages, letters).
     *
     * The gradebook has already set the item's bounds to those of the shown grade (adjusted for hidden items).
     *
     * @param grade_item $item
     * @return array|null value (e.g. "100") and an accessible label (e.g. "out of 100")
     */
    protected function format_grademax(grade_item $item): ?array {
        $pointtypes = [GRADE_DISPLAY_TYPE_REAL, GRADE_DISPLAY_TYPE_REAL_PERCENTAGE, GRADE_DISPLAY_TYPE_REAL_LETTER];
        if ($item->gradetype != GRADE_TYPE_VALUE || !in_array($item->get_displaytype(), $pointtypes)) {
            return null;
        }
        $value = format_float((float)$item->grademax, $item->get_decimals(), true, true);
        return ['value' => $value, 'label' => get_string('gradetablegrademax', 'format_mtubebs', $value)];
    }

    /**
     * The grade to show, in the item's display type, or null when there is none to show.
     *
     * @param array $row a row of gradebook::get_rows()
     * @return string|null
     */
    protected function format_grade(array $row): ?string {
        $item = $row['item'];
        if ($item->needsupdate) {
            return get_string('error');
        }
        if ($row['hiddenasdate']) {
            return get_string(
                'submittedon',
                'grades',
                userdate($row['grade']->get_datesubmitted(), get_string('strftimedatetimeshort', 'langconfig'))
            );
        }
        if ($row['gradehidden'] || $row['value'] === null) {
            return null;
        }
        return grade_format_gradevalue($row['value'], $item, true);
    }

    /**
     * The formatted feedback of a grade, or null when it has none or it is hidden.
     *
     * @param grade_grade $grade
     * @param bool $hidden whether the grade is hidden from the viewer
     * @return string|null
     */
    protected function format_feedback(grade_grade $grade, bool $hidden): ?string {
        if ($hidden || empty($grade->id) || trim(strip_tags((string)$grade->feedback, '<img>')) === '') {
            return null;
        }
        $context = $grade->get_context();
        $feedback = file_rewrite_pluginfile_urls(
            $grade->feedback,
            'pluginfile.php',
            $context->id,
            GRADE_FILE_COMPONENT,
            GRADE_FEEDBACK_FILEAREA,
            $grade->id
        );
        return format_text($feedback, $grade->feedbackformat, ['context' => $context]);
    }

    /**
     * The due date of an activity, or null when it has none.
     *
     * @param cm_info $cm
     * @return int|null
     */
    protected function get_due_date(cm_info $cm): ?int {
        $dates = [];
        foreach (activity_dates::get_dates_for_module($cm, $this->userid) as $date) {
            $dates[$date['dataid'] ?? ''] = (int)$date['timestamp'];
        }
        foreach (self::DUE_DATES as $dataid) {
            if (!empty($dates[$dataid])) {
                return $dates[$dataid];
            }
        }
        return null;
    }

    /**
     * The completion state of an activity for the user, or null when it does not track completion.
     *
     * @param cm_info $cm
     * @return int|null
     */
    protected function completion_state(cm_info $cm): ?int {
        if ($this->completion->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
            return null;
        }
        return (int)$this->completion->get_data($cm, true, $this->userid)->completionstate;
    }

    /**
     * Exports the status pill: locked, passed, failed or overdue, or null for none.
     *
     * @param cm_info|null $cm the activity of the item, if any
     * @param grade_grade|null $grade the grade, when the viewer can see a value
     * @param int|null $due
     * @return array|null
     */
    protected function export_status(?cm_info $cm, ?grade_grade $grade, ?int $due): ?array {
        if ($cm && !$cm->uservisible) {
            return ['text' => get_string('gradetablestatuslocked', 'format_mtubebs'), 'islocked' => true];
        }
        $state = $cm ? $this->completion_state($cm) : null;
        $passed = $state === COMPLETION_COMPLETE_PASS;
        $failed = $state === COMPLETION_COMPLETE_FAIL;
        if (!$passed && !$failed && $grade) {
            $ispassed = $grade->is_passed($grade->grade_item);
            $passed = $ispassed === true;
            $failed = $ispassed === false;
        }
        if ($passed) {
            return ['text' => get_string('gradetablestatuspassed', 'format_mtubebs'), 'ispassed' => true];
        }
        if ($failed) {
            return ['text' => get_string('gradetablestatusfailed', 'format_mtubebs'), 'isfailed' => true];
        }
        if ($cm && !$grade && $state !== COMPLETION_COMPLETE && $due && $due < time()) {
            return ['text' => get_string('gradetablestatusoverdue', 'format_mtubebs'), 'isoverdue' => true];
        }
        return null;
    }
}
