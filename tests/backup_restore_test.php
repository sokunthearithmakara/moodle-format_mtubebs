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

/**
 * Tests for backing up and restoring a course.
 *
 * @package    format_mtubebs
 * @category   test
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mtubebs
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * The options and the section options come along.
     */
    public function test_options_survive(): void {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/format/mtubebs/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'format' => 'mtubebs',
            'numsections' => 2,
            'formatoptionstext' => json_encode(['sectionlabel' => 'chapter', 'showcourseindex' => 0, 'showactivitynav' => 0]),
        ]);
        $section = get_fast_modinfo($course)->get_section_info(2);
        course_get_format($course)->update_section_format_options(
            ['id' => $section->id, 'shortname' => 'Two', 'hidesectionheader' => 1]
        );

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        \core_courseformat\base::reset_course_cache($newcourseid);
        $format = course_get_format($newcourseid);
        $options = $format->get_format_options();
        $this->assertSame('mtubebs', $format->get_format());
        $this->assertSame('chapter', $options['sectionlabel']);
        $this->assertEquals(0, $options['showcourseindex']);
        $this->assertEquals(0, $options['showactivitynav']);

        $newsection = get_fast_modinfo($newcourseid)->get_section_info(2);
        $this->assertSame('Two', $newsection->shortname);
        $this->assertEquals(1, $newsection->hidesectionheader);
    }
}
