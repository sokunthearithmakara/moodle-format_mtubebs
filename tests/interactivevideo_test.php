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
 * Tests that an interactive video keeps its own course page settings.
 *
 * @package    format_mtubebs
 * @category   test
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mtubebs\output\courseformat\content\section\cmitem
 */
final class interactivevideo_test extends \advanced_testcase {
    /**
     * The launch mode and the card width come from the activity.
     *
     * @param int $launchinpopup Whether the activity launches on the course page.
     * @param string $cardsize Card width of the activity.
     * @dataProvider settings_provider
     */
    public function test_settings_are_kept(int $launchinpopup, string $cardsize): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'mtubebs', 'numsections' => 1]);
        $iv = $this->getDataGenerator()->create_module('interactivevideo', ['course' => $course->id, 'section' => 1]);
        $DB->set_field('interactivevideo', 'displayoptions', json_encode([
            'displayinline' => 1,
            'showposterimage' => 1,
            'launchinpopup' => $launchinpopup,
            'cardsize' => $cardsize,
        ]), ['id' => $iv->id]);
        rebuild_course_cache($course->id, true);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        $format = course_get_format($course);
        $cm = get_fast_modinfo($course)->get_cm($iv->cmid);
        $class = $format->get_output_classname('content\\section\\cmitem');
        $item = new $class($format, $cm->get_section_info(), $cm);
        $html = $format->get_renderer($PAGE)->render($item);

        $this->assertInstanceOf(output\courseformat\content\section\cmitem::class, $item);
        $this->assertSame((bool) $launchinpopup, str_contains($html, 'launch-interactivevideo'));
        $this->assertStringContainsString('card-' . $cardsize, $html);
    }

    /**
     * Data provider for test_settings_are_kept.
     *
     * @return array[]
     */
    public static function settings_provider(): array {
        return [
            'on the course page, 50%' => [1, 'medium'],
            'own page, 50%' => [0, 'medium'],
            'on the course page, 25%' => [1, 'tiny'],
            'own page, 100%' => [0, 'large'],
        ];
    }
}
