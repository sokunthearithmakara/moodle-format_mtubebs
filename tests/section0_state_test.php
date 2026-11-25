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

use format_mtubebs\external\get_section_html;

/**
 * Tests when the page is told to reload section 0.
 *
 * @package    format_mtubebs
 * @category   test
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mtubebs\external\get_section_html
 */
final class section0_state_test extends \advanced_testcase {
    /**
     * The state stays the same between sections and after a completion that section 0 does not depend on.
     * It changes when a completion unlocks an activity of section 0, also one of its subsection.
     *
     * @param bool $insubsection Whether the activity that is unlocked is in a subsection of section 0.
     * @dataProvider state_provider
     */
    public function test_state(bool $insubsection): void {
        global $CFG;
        $CFG->enableavailability = 1;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'mtubebs', 'numsections' => 2, 'enablecompletion' => 1]);
        $sectionnum = 0;
        if ($insubsection) {
            $subsection = $generator->create_module('subsection', ['course' => $course->id, 'section' => 0]);
            $sectionnum = get_fast_modinfo($course)->get_section_info_by_component('mod_subsection', $subsection->id)->section;
        }
        $key = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $other = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $generator->create_module('page', [
            'course' => $course->id,
            'section' => $sectionnum,
            'availability' => json_encode(\core_availability\tree::get_root_json([
                \availability_completion\condition::get_json($key->cmid, COMPLETION_COMPLETE),
            ])),
        ]);
        $this->setUser($generator->create_and_enrol($course, 'student'));

        $first = $this->get_state($course->id, 1);
        $this->assertNotEmpty($first);
        $this->assertSame($first, $this->get_state($course->id, 1));
        $this->assertSame($first, $this->get_state($course->id, 2));

        $this->complete($course, $other->cmid);
        $this->assertSame($first, $this->get_state($course->id, 1));

        $this->complete($course, $key->cmid);
        $unlocked = $this->get_state($course->id, 1);
        $this->assertNotSame($first, $unlocked);
        $this->assertSame($unlocked, $this->get_state($course->id, 2));
    }

    /**
     * Data provider for test_state.
     *
     * @return array[]
     */
    public static function state_provider(): array {
        return ['in section 0' => [false], 'in a subsection' => [true]];
    }

    /**
     * Complete an activity as the current user.
     *
     * @param \stdClass $course
     * @param int $cmid
     */
    protected function complete(\stdClass $course, int $cmid): void {
        (new \completion_info($course))->update_state(get_fast_modinfo($course)->get_cm($cmid), COMPLETION_COMPLETE);
    }

    /**
     * The state that comes with a section.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @return string
     */
    protected function get_state(int $courseid, int $sectionnum): string {
        get_fast_modinfo($courseid, 0, true);
        $result = get_section_html::execute(json_encode(['courseid' => $courseid, 'sectionnum' => $sectionnum]));
        return json_decode($result['data'])->section0state;
    }
}
