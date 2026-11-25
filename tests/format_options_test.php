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
 * Tests for format options nobody saved and options of other formats.
 *
 * @package    format_mtubebs
 * @category   test
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mtubebs
 */
final class format_options_test extends \advanced_testcase {
    /**
     * The format options fall back to the defaults when what is saved is not a list of options.
     */
    public function test_options_never_saved(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'mtubebs']);
        $conditions = ['courseid' => $course->id, 'format' => 'mtubebs', 'sectionid' => 0, 'name' => 'formatoptionstext'];
        $DB->delete_records('course_format_options', $conditions);
        $DB->insert_record('course_format_options', $conditions + ['value' => '0']);
        \core_courseformat\base::reset_course_cache($course->id);

        $options = course_get_format($course->id)->get_format_options();

        foreach (array_keys(format_mtubebs_get_default_format_options()) as $name) {
            $this->assertArrayHasKey($name, $options);
        }
    }

    /**
     * Options that the format does not have, such as those of an export of another format, are left out.
     */
    public function test_unknown_options_are_left_out(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'mtubebs',
            'formatoptionstext' => json_encode(['sectionnav' => 'card', 'primarycolor' => '#ff0000', 'showsearch' => 0]),
        ]);

        $options = course_get_format($course->id)->get_format_options();

        $this->assertArrayNotHasKey('sectionnav', $options);
        $this->assertArrayNotHasKey('primarycolor', $options);
        $this->assertEquals(0, $options['showsearch']);
    }
}
