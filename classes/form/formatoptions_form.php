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

use format_mtubebs\local\grade_report;

/**
 * Class formatoptions_form
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formatoptions_form extends \core_form\dynamic_form {
    /**
     * Returns form context
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $contextid = $this->optional_param('contextid', null, PARAM_INT);
        return \context::instance_by_id($contextid, MUST_EXIST);
    }

    /**
     * Checks access for dynamic submission
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/course:update', $this->get_context_for_dynamic_submission());
    }

    /**
     * Sets data for dynamic submission
     */
    public function set_data_for_dynamic_submission(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/mtubebs/lib.php');
        $data = new \stdClass();
        $data->id = $this->optional_param('id', 1, PARAM_INT);
        $data->contextid = $this->optional_param('contextid', null, PARAM_INT);
        $formatoptions = \format_mtubebs_get_default_options();
        if ($this->optional_param('import', 0, PARAM_INT) == 1) {
            $existingoptions = null;
        } else {
            $existingoptions = \course_get_format($data->id)->get_format_options();
        }
        $boolfields = $formatoptions['boolean'];
        $intfields = $formatoptions['int'];
        $textfields = $formatoptions['text'];
        $rawfields = $formatoptions['raw'];
        foreach ($boolfields as $field => $default) {
            $data->$field = isset($existingoptions[$field])
                ? $existingoptions[$field] : $this->optional_param($field, $default, PARAM_BOOL);
        }
        foreach ($intfields as $field => $default) {
            $data->$field = isset($existingoptions[$field])
                ? $existingoptions[$field] : $this->optional_param($field, $default, PARAM_INT);
        }
        foreach ($textfields as $field => $default) {
            $data->$field = isset($existingoptions[$field])
                ? $existingoptions[$field] : $this->optional_param($field, $default, PARAM_TEXT);
        }
        foreach ($rawfields as $field => $default) {
            $data->$field = isset($existingoptions[$field])
                ? $existingoptions[$field] : $this->optional_param($field, $default, PARAM_RAW);
        }

        $data->origin = $this->optional_param('origin', 'coursepage', PARAM_TEXT);

        if (property_exists($data, 'coursecontacts')) {
            $data->coursecontacts = json_encode(format_mtubebs_normalize_coursecontacts($data->coursecontacts));
        }
        // The date-time selector expects an int timestamp (0 = disabled). Format options are
        // stored as strings, so an empty value arrives as '' which breaks date conversion under PHP 8.
        $data->announcementshowuntil = (int) $data->announcementshowuntil;

        $this->set_data($data);
    }

    /**
     * Process dynamic submission
     *
     * @return \stdClass
     */
    public function process_dynamic_submission() {
        $fromform = $this->get_data();
        $courseid = $fromform->id;

        if (property_exists($fromform, 'coursecontacts')) {
            $decoded = json_decode($fromform->coursecontacts, true);
            $fromform->coursecontacts = format_mtubebs_normalize_coursecontacts($decoded);
        }
        foreach ($fromform as $key => $unused) {
            if (strpos((string) $key, 'mform_') === 0) {
                unset($fromform->$key);
            }
        }
        unset($fromform->id);
        unset($fromform->contextid);
        if ($fromform->origin == 'coursepage') {
            // Update the format options.
            $format = course_get_format($courseid);
            $format->update_course_format_options([
                'formatoptionstext' => json_encode($fromform),
            ]);
        } else if ($fromform->origin == 'settingspage') {
            // Update the format options.
            set_config('formatoptions', json_encode($fromform), 'format_mtubebs');
        }
        return $fromform;
    }

    /**
     * Defines form elements
     */
    public function definition() {
        global $CFG, $OUTPUT;
        $mform = &$this->_form;
        $mform->addElement('hidden', 'contextid', null);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'id', 1);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'origin', null);
        $mform->setType('origin', PARAM_TEXT);

        // Course info.
        $mform->addElement('header', 'courseinfoheader', get_string('courseinfo', 'format_mtubebs'));
        $this->add_section_description('courseinfoheader_desc');
        $group = [];
        $group[] = $mform->createElement(
            'advcheckbox',
            'collapsedonstart',
            '',
            get_string('collapsedonstart', 'format_mtubebs'),
            ['group' => 1],
            [0, 1]
        );
        $mform->addGroup($group, 'courseinfogroup0', '', '', false);

        $group = [];
        $groupkeys = [
            'showcourseimage' => 'courseimage',
            'showcoursecontacts' => 'coursecontacts',
        ];

        foreach ($groupkeys as $key => $label) {
            $group[] = $mform->createElement(
                'advcheckbox',
                $key,
                '',
                get_string($label, 'format_mtubebs'),
                ['group' => 1],
                [0, 1]
            );
        };

        $mform->addGroup(
            $group,
            'courseinfogroup',
            '<b>' . get_string('courseinfoelements', 'format_mtubebs') . '</b>',
            '&nbsp;',
            false
        );

        if ($this->optional_param('origin', null, PARAM_TEXT) != 'settingspage' && $this->optional_param('id', 1, PARAM_INT) != 1) {
            $mform->addElement(
                'static',
                'coursecontactseditor',
                '<b>' . get_string('coursecontacts', 'format_mtubebs') . '</b>',
                ''
            );
            $mform->hideIf('coursecontactseditor', 'showcoursecontacts', 'eq', 0);

            $mform->addElement('hidden', 'coursecontacts');
            $mform->setType('coursecontacts', PARAM_RAW);
            $mform->hideIf('coursecontacts', 'showcoursecontacts', 'eq', 0);
        }

        $mform->addElement('text', 'coursecontactlabel', '<b>' . get_string('coursecontactlabel', 'format_mtubebs') . '</b>');
        $mform->setType('coursecontactlabel', PARAM_TEXT);
        $mform->setDefault('coursecontactlabel', get_string('taughtby', 'format_mtubebs'));
        $mform->hideIf('coursecontactlabel', 'showcoursecontacts', 'eq', 0);

        if ($this->optional_param('origin', null, PARAM_TEXT) != 'settingspage') {
            $mform->addElement('text', 'coursevideo', '<b>' . get_string('coursevideo', 'format_mtubebs') . '</b>');
            $mform->setType('coursevideo', PARAM_TEXT);
            $mform->hideIf('coursevideo', 'showcourseimage', 'eq', 0);
        }
        // Section navigation.
        $mform->addElement('header', 'sectionnavheader', get_string('sectionnav', 'format_mtubebs'));
        $this->add_section_description('sectionnavheader_desc');
        // Open by default.
        $mform->setExpanded('sectionnavheader', true);

        $group = [];
        $groupkeys = [
            'useshortname' => 'useshortname',
            'showsectionnumber' => 'showsectionnumber',
            'showsearch' => 'showsearch',
            'stickynav' => 'stickynav',
        ];

        foreach ($groupkeys as $key => $label) {
            $group[] = $mform->createElement(
                'advcheckbox',
                $key,
                '',
                get_string($label, 'format_mtubebs'),
                ['group' => 1],
                [0, 1]
            );
        };
        $group[] = $mform->createElement('html', '<div class="w-100 mt-3"></div>');
        $group[] = $mform->createElement('html', '<b class="w-25">' . get_string('sectionlabel', 'format_mtubebs') . '</b>');
        $sectionlabelkeys = [
            'section',
            'part',
            'unit',
            'module',
            'season',
            'topic',
            'week',
            'session',
            'course',
            'category',
            'chapter',
            'stage',
            'step',
        ];
        $sectionlabels = [];
        foreach ($sectionlabelkeys as $key) {
            $sectionlabels[$key] = get_string($key, 'format_mtubebs');
        }
        // Sort the sectionlabels alphabetically.
        asort($sectionlabels);
        $group[] = $mform->createElement('select', 'sectionlabel', '', $sectionlabels);
        $mform->addGroup($group, 'sectionnavgroup', '', '&nbsp;', false);

        $mform->addElement(
            'advcheckbox',
            'resumesection',
            '<b>' . get_string('resumesection', 'format_mtubebs') . '</b>',
            get_string('yes'),
            ['group' => 1],
            [0, 1],
        );
        $mform->addHelpButton('resumesection', 'resumesection', 'format_mtubebs');

        // Current section.
        $mform->addElement('text', 'currentsectionlabel', '<b>' . get_string('currentsectionlabel', 'format_mtubebs') . '</b>');
        $mform->setType('currentsectionlabel', PARAM_TEXT);
        $mform->addElement(
            'advcheckbox',
            'pincurrentsection',
            '<b>' . get_string('currentsection', 'format_mtubebs') . '</b>',
            get_string('pintop', 'format_mtubebs'),
            ['group' => 1],
            [0, 1],
        );

        // Activity page.
        $mform->addElement('header', 'activitypageheader', get_string('activitypage', 'format_mtubebs'));
        $this->add_section_description('activitypageheader_desc');
        $group = [];
        $keys = ['showcourseindex', 'showactivitynav', 'showbacktosection'];
        if ($CFG->branch < 503) {
            // Core has no activity navigation in the sticky footer before Moodle 5.3.
            $keys = ['showcourseindex', 'showbacktosection'];
        }
        foreach ($keys as $key) {
            $group[] = $mform->createElement(
                'advcheckbox',
                $key,
                '',
                get_string($key, 'format_mtubebs'),
                ['group' => 1],
                [0, 1]
            );
        };
        $mform->addGroup($group, 'activitypagegroup', '', '&nbsp;', false);

        // Side navigation.
        $mform->addElement('header', 'sidenav', get_string('sidenav', 'format_mtubebs'));
        $this->add_section_description('sidenav_desc');
        $group = [];
        $groupkeys = [
            'shownews' => 'news',
            'showgradenav' => 'grade',
            'showtimeline' => 'timeline',
            'showbadge' => 'badge',
            'showcompletion' => 'completion',
        ];
        foreach ($groupkeys as $key => $label) {
            $group[] = $mform->createElement(
                'advcheckbox',
                $key,
                '',
                get_string($label, 'format_mtubebs'),
                ['group' => 1],
                [0, 1]
            );
        };

        $mform->addGroup($group, 'sidenavgroup', '<b>' . get_string('sidenavnode', 'format_mtubebs') . '</b>', '&nbsp;', false);

        $mform->addElement(
            'select',
            'gradetablestyle',
            '<b>' . get_string('gradetablestyle', 'format_mtubebs') . '</b>',
            [
                grade_report::STYLE_CORE => get_string('gradetablestylecore', 'format_mtubebs'),
                grade_report::STYLE_CUSTOM => get_string('gradetablestylecustom', 'format_mtubebs'),
            ],
        );
        $mform->setType('gradetablestyle', PARAM_ALPHA);
        $mform->setDefault('gradetablestyle', grade_report::STYLE_CORE);
        $mform->addHelpButton('gradetablestyle', 'gradetablestyle', 'format_mtubebs');
        $mform->hideIf('gradetablestyle', 'showgradenav', 'notchecked');

        $nodegroup = $OUTPUT->render_from_template('format_mtubebs/customnodegroup', []);
        $mform->addElement('html', $nodegroup);

        $mform->addElement('hidden', 'links');
        $mform->setType('links', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'showlabels', '', get_string('showlabels', 'format_mtubebs'), ['group' => 1], [0, 1]);
        $mform->addElement('advcheckbox', 'hideresize', '', get_string('hideresize', 'format_mtubebs'), ['group' => 1], [0, 1]);
        $mform->addElement(
            'advcheckbox',
            'hidemenuforlearners',
            '',
            get_string('hidemenuforlearners', 'format_mtubebs'),
            ['group' => 1],
            [0, 1]
        );

        // Announcements section.
        $mform->addElement('header', 'announcementsheader', get_string('announcements', 'format_mtubebs'));
        $this->add_section_description('announcementsheader_desc');
        // Title, short description, link, show until.
        $mform->addElement('text', 'announcementtitle', '<b>' . get_string('title', 'format_mtubebs') . '</b>');
        $mform->setType('announcementtitle', PARAM_TEXT);
        $mform->addElement('text', 'announcementdesc', '<b>' . get_string('shortdescription', 'format_mtubebs') . '</b>');
        $mform->setType('announcementdesc', PARAM_TEXT);
        // Link.
        $mform->addElement('text', 'announcementlink', '<b>' . get_string('link', 'format_mtubebs') . '</b>');
        $mform->setType('announcementlink', PARAM_TEXT);
        $mform->addRule(
            'announcementlink',
            get_string('invalidurl', 'format_mtubebs'),
            'regex',
            '/^(https?:\/\/)?([a-z0-9-]+\.)+[a-z]{2,}(\/[^\s]*)?$/i',
            'client'
        );
        $mform->addElement(
            'date_time_selector',
            'announcementshowuntil',
            '<b>' . get_string('showuntil', 'format_mtubebs') . '</b>',
            ['optional' => true]
        );
        $mform->setType('announcementshowuntil', PARAM_RAW);

        // Close header.
        $mform->closeHeaderBefore('distractionfreemode');
        // Other settings.
        // Distraction free mode.
        $mform->addElement(
            'advcheckbox',
            'distractionfreemode',
            '<b>' . get_string('distractionfreemode', 'format_mtubebs') . '</b>',
            get_string('yes'),
            ['group' => 1],
            [0, 1]
        );

        $this->set_display_vertical();
    }

    /**
     * Called after data is set on the form.
     */
    public function definition_after_data() {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/mtubebs/lib.php');

        $mform = $this->_form;
        parent::definition_after_data();

        if (
            $this->optional_param('origin', null, PARAM_TEXT) == 'settingspage'
            || $this->optional_param('id', 1, PARAM_INT) == 1
            || !$mform->elementExists('coursecontactseditor')
        ) {
            return;
        }

        $contactsraw = $mform->getElementValue('coursecontacts');
        if (is_array($contactsraw)) {
            $contactsraw = reset($contactsraw);
        }

        $editorhtml = format_mtubebs_render_coursecontacts_editor(
            $this->get_context_for_dynamic_submission(),
            $contactsraw
        );
        $mform->getElement('coursecontactseditor')->setText($editorhtml);
    }

    /**
     * Validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/mtubebs/lib.php');

        $errors = [];
        $contacts = [];
        if (!empty($data['coursecontacts'])) {
            $decoded = json_decode($data['coursecontacts'], true);
            $contacts = format_mtubebs_normalize_coursecontacts($decoded);
        }
        if (
            $data['showcoursecontacts'] == 1
            && empty($contacts) && $data['origin'] != 'settingspage' && $data['id'] != 1
        ) {
            $message = get_string('youmustselectacontactoruncheck', 'format_mtubebs');
            $errors['coursecontacts'] = $message;
            $errors['coursecontactseditor'] = $message;
        }

        return $errors;
    }

    /**
     * Get page url for dynamic submission
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/course/edit.php', [
            'id' => $this->optional_param('id', null, PARAM_INT),
            "contextid" => $this->optional_param("contextid", null, PARAM_INT),
        ]);
    }

    /**
     * Add a muted section description below a collapsible header.
     *
     * @param string $stringkey Lang string key for the description.
     */
    private function add_section_description(string $stringkey): void {
        $mform = $this->_form;
        $mform->addElement(
            'html',
            '<p class="mtubebs-format-section-description mb-2 mt-3"><i class="fa fa-lightbulb-o me-2 fa-lg"></i>'
             . get_string($stringkey, 'format_mtubebs') . '</p>'
        );
    }
}
