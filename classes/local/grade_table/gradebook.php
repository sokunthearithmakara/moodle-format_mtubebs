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

namespace format_mtubebs\local\grade_table;

use context_course;
use grade_grade;
use grade_helper;
use grade_report;
use grade_tree;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->dirroot . '/grade/report/user/lib.php');

/**
 * The gradebook of one user as rows: grade categories, activity and manual items, category totals and the course total.
 *
 * It applies the rules of the core user report: the gradebook tree and order, the "Show hidden items" and
 * "Hide totals if they contain hidden items" settings, activities hidden from the user, adjusted totals, bounds
 * and weights when hidden items are excluded, and each item's grade display type.
 *
 * Ported from format_mcourse. The viewer may be a teacher looking at a learner's report: activity visibility is
 * judged for the learner, and in "view as user" mode hidden grades stay hidden as they do for the learner.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook extends grade_report {
    /** @var string row of a grade category heading */
    public const ROW_CATEGORY = 'category';
    /** @var string row of an activity, manual, calculated or outcome item */
    public const ROW_ITEM = 'item';
    /** @var string row of a category total */
    public const ROW_CATEGORYTOTAL = 'categoryitem';
    /** @var string row of the course total */
    public const ROW_COURSETOTAL = 'courseitem';

    /** @var \stdClass the user whose grades are shown (used by blank_hidden_total_and_adjust_bounds) */
    public $user;

    /** @var bool whether the viewer can see hidden grades */
    public $canviewhidden;

    /** @var int one of the GRADE_REPORT_USER_HIDE_* or show all hidden items */
    public $showhiddenitems;

    /** @var array the "Hide totals if they contain hidden items" setting, by course id */
    public $showtotalsifcontainhidden;

    /** @var \course_modinfo */
    protected $modinfo;

    /** @var grade_grade[] the user's grades, by grade item id */
    protected array $grades = [];

    /** @var array[] the categories shown, by id: parent (category id or null) and aggregation */
    protected array $categories = [];

    /** @var array the weights items are set up with in their category, by grade item id, from 0 to 1 or null */
    protected array $configuredweights = [];

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param int $userid the user whose grades are shown
     * @param bool $viewasuser show the grades exactly as the user sees them (hidden grades stay hidden)
     */
    public function __construct(int $courseid, int $userid, bool $viewasuser = false) {
        global $CFG, $DB;
        $context = context_course::instance($courseid);
        parent::__construct($courseid, null, $context);

        $this->user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $this->canviewhidden = !$viewasuser && has_capability('moodle/grade:viewhidden', $context);
        $this->showhiddenitems = grade_get_setting(
            $courseid,
            'report_user_showhiddenitems',
            $CFG->grade_report_user_showhiddenitems
        );
        $this->showtotalsifcontainhidden = [$courseid => grade_get_setting(
            $courseid,
            'report_user_showtotalsifcontainhidden',
            $CFG->grade_report_user_showtotalsifcontainhidden
        )];
        // Regrade first, so the tree and the grades are up to date.
        grade_regrade_final_grades_if_required($this->course);
        $categorytotalfirst = grade_get_setting($courseid, 'aggregationposition', $CFG->grade_aggregationposition);
        // Outcomes have their own tab, so the grade table leaves them out for everyone.
        $this->gtree = new grade_tree($courseid, false, $categorytotalfirst, null, true);
        // The tree's modinfo is the viewer's; activity visibility is the shown user's.
        $this->modinfo = get_fast_modinfo($courseid, $userid);
        $this->load_grades();
    }

    /**
     * Loads the user's grades in the course with one query.
     */
    protected function load_grades(): void {
        global $DB;
        $sql = "SELECT gg.*
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid AND gi.courseid = :courseid";
        $rs = $DB->get_recordset_sql($sql, ['userid' => $this->user->id, 'courseid' => $this->courseid]);
        foreach ($rs as $record) {
            $this->grades[$record->itemid] = new grade_grade($record, false);
        }
        $rs->close();
    }

    /**
     * The rows the user can see, in gradebook order.
     *
     * Each row has: type (a ROW_* constant), depth (1 for the top level), name, typename, item (grade_item, not for
     * categories), grade (grade_grade), value (the grade to show, adjusted for hidden items), cm (cm_info of an
     * activity item, if any), weight (weight in the parent category from 0 to 1, or null), weightstatus (the
     * aggregation status: used, unknown, extra, novalue, dropped or excluded), ishidden (hidden from students,
     * shown to the viewer), gradehidden (the grade is hidden from the viewer), hiddenasdate (show the submission
     * date instead of the hidden grade), and weightearned and weightpossible (see add_weights()).
     *
     * @return array[]
     */
    public function get_rows(): array {
        $rows = [];
        $this->collect($this->gtree->top_element, $rows);
        $this->add_weights($rows);
        return $rows;
    }

    /**
     * Adds to item and category total rows their weight in their category, earned and possible, as the
     * gradebook setup page shows weights: an item's share of its category, a category's share of its parent.
     *
     * - weightpossible: the item's weight in its category, from 0 to 1, from the weights the category is set up
     *   with, so an item without a grade still shows what it is worth; null for aggregations without weights
     *   (median, lowest, highest, mode).
     * - weightearned: the share of weightpossible the shown grade earns (a grade of 80% earns 80% of it), or null
     *   when there is no grade, it is dropped or excluded, or the weight is unknown.
     *
     * @param array $rows rows of get_rows()
     */
    protected function add_weights(array &$rows): void {
        foreach ($rows as $key => $row) {
            $rows[$key]['weightearned'] = null;
            $rows[$key]['weightpossible'] = null;
            if (!in_array($row['type'], [self::ROW_ITEM, self::ROW_CATEGORYTOTAL])) {
                continue;
            }
            $item = $row['item'];
            // The category the item is aggregated in: an item's own, or the parent of a total's category.
            $categoryid = $row['type'] === self::ROW_ITEM ? (int)$item->categoryid
                : ($this->categories[(int)$item->iteminstance]['parent'] ?? null);
            if (!$categoryid || !isset($this->categories[$categoryid])) {
                continue;
            }
            $possible = $this->configuredweights[$item->id] ?? null;
            $rows[$key]['weightpossible'] = $possible;

            $counts = !in_array($row['weightstatus'], ['dropped', 'excluded']);
            if ($possible === null || $row['value'] === null || $row['gradehidden'] || !$counts) {
                continue;
            }
            if ($this->categories[$categoryid]['aggregation'] == GRADE_AGGREGATE_SUM) {
                // Natural aggregation does not use the minimum grade.
                $normalised = $item->grademax != 0 ? $row['value'] / $item->grademax : 0;
            } else {
                $normalised = grade_grade::standardise_score($row['value'], $item->grademin, $item->grademax, 0, 1);
            }
            $rows[$key]['weightearned'] = $normalised * $possible;
        }
    }

    /**
     * Records a category and the weights its children are set up with.
     *
     * The weights do not depend on the user's grades, so an ungraded item gets the weight it will have once
     * graded. Items hidden from the user are left out when totals are shown without them.
     *
     * @param \grade_category $category
     * @param array $children grade_tree elements of the category
     */
    protected function add_category(\grade_category $category, array $children): void {
        $this->categories[(int)$category->id] = [
            'parent' => $category->parent ? (int)$category->parent : null,
            'aggregation' => (int)$category->aggregation,
        ];

        $excludehidden = !$this->canviewhidden
            && $this->showtotalsifcontainhidden[$this->courseid] == GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN;
        $items = [];
        $leftout = false;
        foreach ($children as $child) {
            $item = match ($child['type']) {
                'item' => $child['object'],
                'category' => $child['object']->load_grade_item(),
                default => null,
            };
            if ($item && $excludehidden && $item->is_hidden()) {
                $leftout = true;
            } else if ($item) {
                $items[] = $item;
            }
        }
        if (!$items) {
            return;
        }

        // Extra credit items add to the total without counting in the sum the others are shared out of.
        $isextra = fn($item) => $item->aggregationcoef > 0;
        $range = fn($item) => (float)$item->grademax - (float)$item->grademin;
        $share = function (array $values) use ($items): array {
            $sum = array_sum($values);
            $weights = [];
            foreach ($items as $i => $item) {
                $weights[$item->id] = $sum > 0 && isset($values[$i]) ? $values[$i] / $sum : null;
            }
            return $weights;
        };
        $regular = array_filter($items, fn($item) => !$isextra($item));

        switch ($category->aggregation) {
            case GRADE_AGGREGATE_SUM:
                // Natural: the stored weights, shared out again only when hidden items are left out of the totals.
                // Items the tree does not list (outcomes) keep their part of the stored weights.
                $sum = $leftout ? array_sum(array_map(fn($item) => (float)$item->aggregationcoef2, $regular)) : 1;
                $weights = [];
                foreach ($items as $item) {
                    $weights[$item->id] = $sum > 0 ? (float)$item->aggregationcoef2 / $sum : null;
                }
                break;
            case GRADE_AGGREGATE_WEIGHTED_MEAN:
                $weights = $share(array_map(fn($item) => max(0, (float)$item->aggregationcoef), $items));
                break;
            case GRADE_AGGREGATE_WEIGHTED_MEAN2:
                $sum = array_sum(array_map($range, $regular));
                $weights = [];
                foreach ($items as $item) {
                    $weights[$item->id] = $sum > 0 ? $range($item) / $sum : null;
                }
                break;
            case GRADE_AGGREGATE_MEAN:
                $weights = array_fill_keys(array_map(fn($item) => $item->id, $items), 1 / count($items));
                break;
            case GRADE_AGGREGATE_EXTRACREDIT_MEAN:
                $weights = array_fill_keys(
                    array_map(fn($item) => $item->id, $items),
                    $regular ? 1 / count($regular) : null
                );
                break;
            default:
                // Median, lowest, highest and mode have no weights.
                $weights = array_fill_keys(array_map(fn($item) => $item->id, $items), null);
        }
        $this->configuredweights = $weights + $this->configuredweights;
    }

    /**
     * Adds the rows of a tree element and its children.
     *
     * @param array $element grade_tree element
     * @param array $rows
     */
    protected function collect(array $element, array &$rows): void {
        global $CFG;
        $type = $element['type'];
        $object = $element['object'];
        // The course category has depth 1. Items and totals have the depth of their category, so items directly
        // in the course and the course total are at level 1, a top category heading too, and its items at level 2.
        $depth = (int)$element['depth'];

        if ($type === 'category') {
            if ($object->is_hidden() && !$this->canviewhidden && $this->hides($object)) {
                return;
            }
            $this->add_category($object, $element['children'] ?? []);
            if ($element['depth'] > 1) {
                $rows[] = [
                    'type' => self::ROW_CATEGORY,
                    'depth' => $depth - 1,
                    'name' => $object->get_name(),
                    'ishidden' => $object->is_hidden(),
                ];
            }
            foreach ($element['children'] ?? [] as $child) {
                $this->collect($child, $rows);
            }
            return;
        }

        if (!in_array($type, ['item', 'categoryitem', 'courseitem'])) {
            return;
        }

        $grade = $this->grades[$object->id] ?? new grade_grade(['userid' => $this->user->id, 'itemid' => $object->id], false);
        $grade->grade_item = $object;

        if ($grade->is_hidden() && !$this->canviewhidden && $this->hides($grade)) {
            return;
        }
        $cm = null;
        if ($object->itemtype === 'mod' && $object->iteminstance) {
            $cm = $this->modinfo->get_instances_of($object->itemmodule)[$object->iteminstance] ?? null;
            // An activity the user cannot see is listed only when it shows why (restricted access).
            if ($cm && !$cm->uservisible && (!$cm->visible || !$cm->availableinfo)) {
                return;
            }
        }

        $value = $grade->finalgrade;
        $hint = $grade->get_aggregation_hint();
        if (!$this->canviewhidden) {
            $adjusted = $this->blank_hidden_total_and_adjust_bounds($this->courseid, $object, $value);
            $value = $adjusted['grade'];
            $object->grademax = $adjusted['grademax'];
            $object->grademin = $adjusted['grademin'];
            $hint = ['status' => $adjusted['aggregationstatus'], 'weight' => $adjusted['aggregationweight']];
        } else if ($value !== null) {
            $object->grademax = $grade->get_grade_max();
            $object->grademin = $grade->get_grade_min();
        }

        $gradehidden = $grade->is_hidden() && !$this->canviewhidden;
        $rows[] = [
            'type' => $type === 'item' ? self::ROW_ITEM : $type,
            'depth' => $depth,
            'name' => $type === 'courseitem' ? get_string('coursetotal', 'grades') : $object->get_name(true),
            'typename' => $type === 'item' ? grade_helper::get_element_type_string($element) : '',
            'item' => $object,
            'grade' => $grade,
            'value' => $value,
            'cm' => $cm,
            'weight' => is_numeric($hint['weight']) ? (float)$hint['weight'] : null,
            'weightstatus' => $hint['status'],
            'ishidden' => $grade->is_hidden() && $this->canviewhidden,
            'gradehidden' => $gradehidden,
            'hiddenasdate' => $gradehidden && !empty($CFG->grade_hiddenasdate) && $grade->get_datesubmitted()
                && $type === 'item',
        ];
    }

    /**
     * Whether the "Show hidden items" setting hides a hidden category or grade from the user.
     *
     * @param \grade_category|grade_grade $object
     * @return bool
     */
    protected function hides($object): bool {
        return $this->showhiddenitems == GRADE_REPORT_USER_HIDE_HIDDEN
            || ($this->showhiddenitems == GRADE_REPORT_USER_HIDE_UNTIL && !$object->is_hiddenuntil());
    }

    #[\Override]
    public function process_data($data) {
    }

    #[\Override]
    public function process_action($target, $action) {
    }
}
