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

namespace format_mtubebs;

use format_mtubebs\local\grade_report;
use format_mtubebs\local\grade_table\gradebook;
use format_mtubebs\output\grade_table;

/**
 * Tests for the custom grade table of the user report.
 *
 * @package    format_mtubebs
 * @category   test
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grade_table::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(gradebook::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(grade_report::class)]
final class grade_table_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/report/lib.php');
        require_once($CFG->dirroot . '/course/format/mtubebs/lib.php');
        $this->resetAfterTest();
    }

    /**
     * Rows, order, weights, feedback and totals follow the core user report rules.
     */
    public function test_rows(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'mtubebs', 'numsections' => 2]);
        $assign = $gen->create_module('assign', ['course' => $course->id, 'section' => 2, 'grade' => 100]);
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');

        $category = $gen->create_grade_category(['courseid' => $course->id, 'fullname' => 'Assignments']);
        $assignitem = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id]);
        $assignitem->set_parent($category->id);
        $manual = $gen->create_grade_item([
            'courseid' => $course->id,
            'itemname' => 'Participation',
            'grademax' => 50,
            'categoryid' => $category->id,
        ]);
        $hidden = $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Bonus', 'hidden' => 1]);
        $assignitem->update_final_grade($student->id, 80, 'test');
        \grade_item::fetch(['id' => $manual->id])->update_final_grade($student->id, 40, 'test', 'Well done.');
        grade_set_setting($course->id, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN);

        $this->setUser($student);
        $data = $this->export($course, (int)$student->id);
        $rows = array_column($data['rows'], null, 'name');
        $this->assertTrue($data['hasitems']);
        $this->assertTrue($rows['Assignments']['iscategory']);
        $this->assertSame(1, $rows['Assignments']['depth']);

        $assignrow = $rows[$assign->name];
        $this->assertTrue($assignrow['isitem']);
        $this->assertSame((int)$assign->cmid, (int)$assignrow['cmid']);
        $this->assertSame(2, $assignrow['depth']);
        $this->assertSame('80.00', $assignrow['grade']);
        $this->assertSame('100', $assignrow['grademax']['value']);
        // Weights are within the category, as on the gradebook setup page: 100/150 of Assignments, 80% of it earned.
        $this->assertSame('53.33%', $assignrow['weight']);
        $this->assertSame('66.67%', $assignrow['weightmax']['value']);

        $manualrow = $rows['Participation'];
        $this->assertSame(get_string('manualitem', 'grades'), $manualrow['type']);
        $this->assertNull($manualrow['url']);
        $this->assertSame('40.00', $manualrow['grade']);
        $this->assertSame('50', $manualrow['grademax']['value']);
        $this->assertStringContainsString('Well done.', $manualrow['feedback']);

        $this->assertSame('120.00', $rows['Assignments total']['grade']);
        $this->assertSame('150', $rows['Assignments total']['grademax']['value']);
        $this->assertSame('80%', $rows['Assignments total']['weight']);
        $this->assertSame('100%', $rows['Assignments total']['weightmax']['value']);
        $coursetotal = $rows[get_string('coursetotal', 'grades')];
        $this->assertTrue($coursetotal['iscoursetotal']);
        // No parent category to weigh in: the course total shows its percentage, as the core report does.
        $this->assertSame('80.00 %', $coursetotal['weight']);
        $this->assertNull($coursetotal['weightmax']);
        $this->assertArrayNotHasKey('Bonus', $rows);

        // A teacher looking at the student's report sees the hidden item, unless viewing as the user.
        $this->setUser($teacher);
        $rows = array_column($this->export($course, (int)$student->id)['rows'], null, 'name');
        $this->assertTrue($rows['Bonus']['ishidden']);
        $this->assertSame((int)$hidden->id, (int)$rows['Bonus']['id']);
        $rows = array_column($this->export($course, (int)$student->id, true)['rows'], null, 'name');
        $this->assertArrayNotHasKey('Bonus', $rows);
    }

    /**
     * Access restrictions are judged for the learner, also when a teacher opens the learner's report.
     */
    public function test_restricted_activity_is_locked_for_the_learner(): void {
        set_config('enableavailability', 1);
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'mtubebs']);
        $availability = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
            'showc' => [true],
        ]);
        $assign = $gen->create_module('assign', ['course' => $course->id, 'availability' => $availability]);
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        $rows = array_column($this->export($course, (int)$student->id)['rows'], null, 'name');
        $this->assertTrue($rows[$assign->name]['status']['islocked']);
        $this->assertNull($rows[$assign->name]['url']);

        $rows = array_column($this->export($course, (int)$teacher->id)['rows'], null, 'name');
        $this->assertNotNull($rows[$assign->name]['url']);
    }

    /**
     * Outcome items are left out of the table, also for teachers: outcomes have their own report tab.
     */
    public function test_outcomes_left_out(): void {
        set_config('enableoutcomes', 1);
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'mtubebs']);
        $assign = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Brief']);
        $scale = $gen->create_scale(['courseid' => $course->id]);
        $outcome = $gen->create_grade_outcome([
            'courseid' => $course->id,
            'shortname' => 'ux',
            'fullname' => 'Explain UX',
            'scaleid' => $scale->id,
        ]);
        $gen->create_grade_item([
            'courseid' => $course->id,
            'itemname' => 'Brief outcome',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'itemnumber' => 1000,
            'outcomeid' => $outcome->id,
            'gradetype' => GRADE_TYPE_SCALE,
            'scaleid' => $scale->id,
        ]);
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');

        foreach ([$student, $teacher] as $viewer) {
            $this->setUser($viewer);
            $names = array_column($this->export($course, (int)$student->id)['rows'], 'name');
            $this->assertContains('Brief', $names);
            $this->assertNotContains('Brief outcome', $names);
        }
    }

    /**
     * An ungraded item shows the weight it will have once graded; earned weight needs a grade.
     */
    public function test_weight_of_ungraded_items(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'mtubebs']);
        $graded = $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Graded', 'grademax' => 30]);
        $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Ungraded', 'grademax' => 10]);
        $student = $gen->create_and_enrol($course, 'student');
        \grade_item::fetch(['id' => $graded->id])->update_final_grade($student->id, 15, 'test');
        $this->setUser($student);

        $rows = array_column($this->export($course, (int)$student->id)['rows'], null, 'name');
        // 15/30 earns half of the 75% the item is worth, though only non-empty grades count in the total for now.
        $this->assertSame('37.5%', $rows['Graded']['weight']);
        $this->assertSame('75%', $rows['Graded']['weightmax']['value']);
        $this->assertNull($rows['Ungraded']['weight']);
        $this->assertSame('25%', $rows['Ungraded']['weightmax']['value']);
        $this->assertNull($rows['Ungraded']['weightnote']);
    }

    /**
     * Possible points show only for grades displayed as points.
     */
    public function test_grademax_only_for_points(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'mtubebs']);
        $scale = $gen->create_scale(['courseid' => $course->id, 'scale' => 'Poor,Good,Great']);
        $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Points', 'grademax' => 20]);
        $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Letter', 'grademax' => 20,
            'display' => GRADE_DISPLAY_TYPE_LETTER]);
        $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Scale', 'gradetype' => GRADE_TYPE_SCALE,
            'scaleid' => $scale->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($student);

        $rows = array_column($this->export($course, (int)$student->id)['rows'], null, 'name');
        $expected = ['value' => '20', 'label' => get_string('gradetablegrademax', 'format_mtubebs', '20')];
        $this->assertSame($expected, $rows['Points']['grademax']);
        $this->assertNull($rows['Letter']['grademax']);
        $this->assertNull($rows['Scale']['grademax']);
    }

    /**
     * A course without grade items says so.
     */
    public function test_without_items(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mtubebs']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->assertFalse($this->export($course, (int)$student->id)['hasitems']);
    }

    /**
     * The course option switches the user report between the core table and the custom one.
     */
    public function test_report_html_follows_the_option(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'mtubebs']);
        $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Participation']);
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($student);

        // Core by default.
        $this->assertFalse(grade_report::uses_custom_table($course));
        $html = grade_report::get_report_html((int)$course->id)['html'];
        $this->assertStringContainsString('user-grade', $html);
        $this->assertStringNotContainsString('mtubebs-gradetable', $html);

        $this->set_style($course, grade_report::STYLE_CUSTOM);
        $this->assertTrue(grade_report::uses_custom_table($course));
        $report = grade_report::get_report_html((int)$course->id);
        $this->assertStringContainsString('mtubebs-gradetable', $report['html']);
        $this->assertStringContainsString('Participation', $report['html']);
        $this->assertStringNotContainsString('user-grade', $report['html']);
        $this->assertSame('user-report-' . $student->id, $report['reportcontainerid']);

        // Courses in other formats keep the core table.
        $other = $gen->create_course(['format' => 'topics']);
        $this->assertFalse(grade_report::uses_custom_table($other));
    }

    /**
     * Sets the user grade report style of a course.
     *
     * @param \stdClass $course
     * @param string $style
     */
    protected function set_style(\stdClass $course, string $style): void {
        $options = json_decode($course->formatoptionstext ?? '', true) ?: [];
        $options['gradetablestyle'] = $style;
        course_get_format($course)->update_course_format_options((object)[
            'formatoptionstext' => json_encode($options),
        ]);
        \core_courseformat\base::reset_course_cache($course->id);
    }

    /**
     * Exports the grade table of a user.
     *
     * @param \stdClass $course
     * @param int $userid
     * @param bool $viewasuser
     * @return array
     */
    protected function export(\stdClass $course, int $userid, bool $viewasuser = false): array {
        global $PAGE;
        return (new grade_table(get_course($course->id), $userid, $viewasuser))->export_for_template($PAGE->get_renderer('core'));
    }
}
