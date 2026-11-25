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

use format_mtubebs\local\resume;

/**
 * Tests for the last visited section a learner resumes into.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mtubebs\local\resume
 */
final class resume_test extends \advanced_testcase {
    /**
     * Start every test with an empty course option cache.
     *
     * The cache is request-scoped in production, but a static survives between test
     * methods, and resetAfterTest() restarts the id sequences - so the next test builds
     * a course with the very same id and would read the previous test's answer back.
     */
    protected function setUp(): void {
        parent::setUp();
        resume::reset_cache();
    }

    /**
     * Turn the course option on or off and forget anything cached about it.
     *
     * @param \stdClass $course Course to change.
     * @param int $value 1 to enable, 0 to disable.
     */
    protected function set_resume_option(\stdClass $course, int $value): void {
        $format = course_get_format($course);
        $options = json_decode($course->formatoptionstext ?? '', true) ?: [];
        $options['resumesection'] = $value;
        $format->update_course_format_options((object) [
            'formatoptionstext' => json_encode($options),
        ]);

        \core_courseformat\base::reset_course_cache($course->id);
        resume::reset_cache();
    }

    /**
     * Build a course with sections and a student sitting in it.
     *
     * @param int $numsections Sections to create beyond section 0.
     * @return array{0: \stdClass, 1: \stdClass} Course and student.
     */
    protected function course_with_student(int $numsections = 3): array {
        $course = $this->getDataGenerator()->create_course([
            'format' => 'mtubebs',
            'numsections' => $numsections,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        return [$course, $student];
    }

    /**
     * Nothing is offered while the course has not asked for the behaviour.
     */
    public function test_recall_is_null_when_the_option_is_off(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $this->setUser($student);

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(2);

        // Store a value directly, so the only thing under test is the option.
        set_user_preference(resume::PREFIX . $course->id, $section->id, $student->id);

        $this->assertNull(resume::recall((int) $course->id));
    }

    /**
     * A section the learner was on and can still see comes back.
     */
    public function test_remember_then_recall_round_trips(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $this->set_resume_option($course, 1);
        $this->setUser($student);

        $section = get_fast_modinfo($course)->get_section_info(2);
        resume::remember((int) $course->id, $section);

        $this->assertSame((int) $section->id, resume::recall((int) $course->id));
    }

    /**
     * Nothing is stored for a course that never asked for it.
     */
    public function test_remember_stores_nothing_when_the_option_is_off(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $this->setUser($student);

        $section = get_fast_modinfo($course)->get_section_info(2);
        resume::remember((int) $course->id, $section);

        $this->assertNull(get_user_preferences(resume::PREFIX . $course->id, null, $student->id));
    }

    /**
     * Section 0 is the course info panel, not somewhere to come back to.
     */
    public function test_remember_ignores_section_zero(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $this->set_resume_option($course, 1);
        $this->setUser($student);

        $section = get_fast_modinfo($course)->get_section_info(0);
        resume::remember((int) $course->id, $section);

        $this->assertNull(get_user_preferences(resume::PREFIX . $course->id, null, $student->id));
    }

    /**
     * A section hidden since the last visit is discarded rather than reopened.
     */
    public function test_recall_drops_a_section_the_learner_can_no_longer_see(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $this->set_resume_option($course, 1);

        $this->setUser($student);
        $section = get_fast_modinfo($course)->get_section_info(2);
        resume::remember((int) $course->id, $section);
        $this->assertSame((int) $section->id, resume::recall((int) $course->id));

        // The teacher hides it while the learner is away.
        $this->setAdminUser();
        $section = get_fast_modinfo($course)->get_section_info(2);
        \core_courseformat\formatactions::section($course)->update($section, ['visible' => 0]);

        $this->setUser($student);
        $this->assertNull(resume::recall((int) $course->id));
    }

    /**
     * A section deleted since the last visit is discarded.
     */
    public function test_recall_drops_a_deleted_section(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $this->set_resume_option($course, 1);

        $this->setUser($student);
        $section = get_fast_modinfo($course)->get_section_info(2);
        resume::remember((int) $course->id, $section);

        $this->setAdminUser();
        course_delete_section($course, 2, true);

        $this->setUser($student);
        $this->assertNull(resume::recall((int) $course->id));
    }

    /**
     * An id left over from another course never resolves here.
     */
    public function test_recall_drops_a_section_from_another_course(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $other = $this->getDataGenerator()->create_course(['format' => 'mtubebs', 'numsections' => 3]);
        $this->set_resume_option($course, 1);

        $othersection = get_fast_modinfo($other)->get_section_info(2);
        set_user_preference(resume::PREFIX . $course->id, $othersection->id, $student->id);

        $this->setUser($student);
        $this->assertNull(resume::recall((int) $course->id));
    }

    /**
     * Deleting the course takes its stored resume points with it.
     */
    public function test_course_deletion_removes_the_preference(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $student] = $this->course_with_student();
        $this->set_resume_option($course, 1);
        $this->setUser($student);

        $section = get_fast_modinfo($course)->get_section_info(2);
        resume::remember((int) $course->id, $section);

        $name = resume::PREFIX . $course->id;
        $this->assertTrue($DB->record_exists('user_preferences', ['name' => $name, 'userid' => $student->id]));

        $this->setAdminUser();
        delete_course($course->id, false);

        $this->assertFalse($DB->record_exists('user_preferences', ['name' => $name]));
    }
}
