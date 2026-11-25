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

namespace format_mtubebs\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once("$CFG->libdir/formslib.php");
require_once("$CFG->dirroot/course/editsection_form.php");
/**
 * Class editmtubebssection
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_mtubebssection extends \editsection_form {
    /**
     * Hides the section options that do not apply to the section.
     *
     * @return void
     * @throws coding_exception
     */
    public function definition(): void {
        parent::definition();

        $mform = $this->_form;

        $mform->addElement('hidden', 'isdelegated', $this->_customdata['isdelegated'] ?? 0);
        $mform->setType('isdelegated', PARAM_INT);
        $mform->addElement('hidden', 'issection0', $this->_customdata['issection0'] ?? 0);
        $mform->setType('issection0', PARAM_INT);

        $mform->hideIf('shortname', 'isdelegated', 'eq', 1);
        $mform->hideIf('showcollapsed', 'isdelegated', 'neq', 1);

        // Section 0 is the course info panel: only its name and description apply.
        foreach (format_mtubebs_section0_unused_options() as $key) {
            $mform->hideIf($key, 'issection0', 'eq', 1);
        }
    }
}
