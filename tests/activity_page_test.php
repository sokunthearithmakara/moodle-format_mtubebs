<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace format_mtubebs;

use core_courseformat\local\linearnavigationsettings;

/**
 * Tests for the activity page options: course index and activity navigation.
 *
 * @package    format_mtubebs
 * @category   test
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mtubebs
 */
final class activity_page_test extends \advanced_testcase {
    /**
     * Create a course with an activity, and format options if given.
     *
     * @param array|null $options Course format options to save, null to leave them unsaved.
     * @return \cm_info
     */
    protected function create_activity(?array $options): \cm_info {
        $record = ['format' => 'mtubebs', 'numsections' => 1];
        if ($options !== null) {
            $record['formatoptionstext'] = json_encode($options);
        }
        $course = $this->getDataGenerator()->create_course($record);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        return get_fast_modinfo($course)->get_cm($page->cmid);
    }

    /**
     * Open the page of an activity.
     *
     * @param \cm_info $cm
     * @return \moodle_page
     */
    protected function open_activity(\cm_info $cm): \moodle_page {
        global $PAGE;

        $PAGE = new \moodle_page();
        $PAGE->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $PAGE->set_cm($cm);
        return $PAGE;
    }

    /**
     * Each option turns its own core feature on and off.
     *
     * @param array|null $options Saved options, null when the course never saved any.
     * @param bool $index Whether the course index is expected.
     * @param bool $navigation Whether the activity navigation is expected.
     * @dataProvider options_provider
     */
    public function test_activity_page_options(?array $options, bool $index, bool $navigation): void {
        $this->resetAfterTest();
        $cm = $this->create_activity($options);
        $this->setUser($this->getDataGenerator()->create_and_enrol($cm->get_course(), 'student'));

        $page = $this->open_activity($cm);

        $this->assertSame($index, course_get_format($cm->get_course())->uses_course_index());
        if (!class_exists(linearnavigationsettings::class)) {
            // Core has this navigation from Moodle 5.3.
            return;
        }
        $this->assertSame($navigation, linearnavigationsettings::show_navigation_footer($page));
    }

    /**
     * Data provider for test_activity_page_options.
     *
     * @return array[]
     */
    public static function options_provider(): array {
        return [
            'never saved' => [null, true, true],
            'both on' => [['showcourseindex' => 1, 'showactivitynav' => 1], true, true],
            'index only' => [['showcourseindex' => 1, 'showactivitynav' => 0], true, false],
            'navigation only' => [['showcourseindex' => 0, 'showactivitynav' => 1], false, true],
            'both off' => [['showcourseindex' => 0, 'showactivitynav' => 0], false, false],
        ];
    }

    /**
     * The course page has its own navigation, so its index only shows while editing.
     */
    public function test_course_page_has_the_index_only_while_editing(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $course = $this->create_activity(['showcourseindex' => 1])->get_course();
        $this->setAdminUser();
        $PAGE = new \moodle_page();
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $USER->editing = 0;
        $this->assertFalse(course_get_format($course)->uses_course_index());
        $USER->editing = 1;
        $this->assertTrue(course_get_format($course)->uses_course_index());
    }
}
