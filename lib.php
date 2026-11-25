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

/**
 * format_mtubebs plugin implementation
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/lib.php');
require_once($CFG->dirroot . '/course/format/topics/lib.php');

/**
 * Section format options that do not apply to section 0.
 *
 * This format renders section 0 as the course info panel: it is never navigated to. Only its
 * name and description are meaningful, so the section edit form hides these keys for section 0.
 *
 * @return string[]
 */
function format_mtubebs_section0_unused_options(): array {
    return [
        'shortname',
        'showcollapsed',
        'hidesectionheader',
        'hidesectiondescription',
    ];
}

/**
 * Give an AJAX request the course page URL of what it renders.
 *
 * Core's course-format output (content\section reads $PAGE->url for the expandsection
 * parameter) expects a page URL, and lib/ajax/service.php never sets one; without this every
 * section render from a web service raises a "did not call $PAGE->set_url()" notice.
 *
 * @param int $courseid
 * @param int|null $sectionid Section being rendered, when there is one.
 */
function format_mtubebs_set_ajax_page_url(int $courseid, ?int $sectionid = null): void {
    global $PAGE;

    if ($PAGE->has_set_url()) {
        return;
    }
    $params = ['id' => $courseid];
    if ($sectionid) {
        $params['sectionid'] = $sectionid;
    }
    $PAGE->set_url('/course/view.php', $params);
}

/**
 * Course format main class
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_mtubebs extends format_topics {
    /**
     * Whether the course index is available on the current page.
     *
     * The course page has its own section navigation, so it only needs the index while editing. Activity
     * pages show it when the course option says so.
     *
     * @return bool
     */
    public function uses_course_index() {
        global $PAGE;

        if ($PAGE->user_is_editing()) {
            return true;
        }
        return $PAGE->cm && !empty($this->get_format_options()['showcourseindex']);
    }

    /**
     * Activity pages can have core's activity navigation in the sticky footer.
     *
     * This method is static, so it cannot read the course option: see {@see self::page_set_cm()}.
     *
     * @return bool
     */
    public static function uses_linear_navigation(): bool {
        return true;
    }

    /**
     * Turn the activity navigation of an activity page off when the course option says so.
     *
     * @param moodle_page $page
     */
    public function page_set_cm(moodle_page $page) {
        parent::page_set_cm($page);
        if (empty($this->get_format_options()['showactivitynav'])) {
            $page->set_show_navigation_footer(false);
        }
    }

    /**
     * Returns the default section name for the topics course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, it will consistently return the name 'newsection', disregarding the specific section number.
     *
     * @param int|stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        $section = $this->get_section($section);
        $formatoptions = $this->get_format_options();
        if (isset($formatoptions['sectionlabel'])) {
            $sectionlabel = $formatoptions['sectionlabel'];
        } else {
            $sectionlabel = 'section';
        }
        if ($section->sectionnum == 0) {
            return get_string('section0name', 'format_mtubebs');
        }

        return get_string('newsection', 'format_mtubebs', [
            'sectionname' => get_string($sectionlabel, 'format_mtubebs'),
            'sectionnumber' => $section->sectionnum,
        ]);
    }

    /**
     * Returns the name for the highlighted section.
     *
     * @return string The name for the highlighted section based on the given course format.
     */
    public function get_section_highlighted_name(): string {
        if ($currentlabel = $this->get_format_options()['currentsectionlabel']) {
            return format_string($currentlabel);
        }
        return get_string('current', 'format_mtubebs');
    }

    /**
     * The URL to use for the specified course (with section).
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'pagesectionid' (int) the section ID of the page to display (null or 0 for course main page)
     *     'navigation' (bool) if true and section not empty, the function returns section page; otherwise, it returns course page.
     *     'sr' (int) used by course formats to specify to which section to return (deprecated since Moodle 5.3)
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        $course = $this->get_course();
        if (array_key_exists('pagesectionid', $options)) {
            $pagesectionid = $options['pagesectionid'] ?? null;
            $pagesection = $pagesectionid
                ? $this->get_modinfo()->get_section_info_by_id((int) $pagesectionid, IGNORE_MISSING)
                : null;
            if ($pagesection) {
                return new moodle_url('/course/section.php', ['id' => $pagesection->id]);
            }
            return new moodle_url('/course/view.php', ['id' => $course->id]);
        }
        if (array_key_exists('sr', $options) && !is_null($options['sr'])) {
            $sectionno = $options['sr'];
        } else if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ((!empty($options['navigation']) || array_key_exists('sr', $options)) && $sectionno !== null) {
            // Display section on separate page.
            $sectioninfo = $this->get_section($sectionno);
            return new moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
        }

        return new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    /**
     * The section edit form
     *
     * @param string $action
     * @param array $customdata
     * @return edit_mtubebssection
     */
    public function editsection_form($action, $customdata = []) {
        $customdata['isdelegated'] = $customdata['cs']->is_delegated() ? 1 : 0;
        // Section 0 is the course info panel. It still uses this form, so that the format
        // options that do not apply to it are hidden the same way they are for subsections.
        $customdata['issection0'] = $customdata['cs']->section == 0 ? 1 : 0;

        if (!array_key_exists('course', $customdata)) {
            $customdata['course'] = $this->get_course();
        }

        return new \format_mtubebs\form\edit_mtubebssection($action, $customdata);
    }

    /**
     * Prepares values of course or section format options before storing them in DB
     *
     * If an option has invalid value it is not returned
     *
     * @param array $rawdata associative array of the proposed course/section format options
     * @param int|null $sectionid null if it is course format option
     * @return array array of options that have valid values
     */
    protected function validate_format_options(array $rawdata, ?int $sectionid = null): array {
        if (!$sectionid) {
            $allformatoptions = $this->course_format_options(true);
        } else {
            $allformatoptions = $this->section_format_options(true);
        }
        $data = array_intersect_key($rawdata, $allformatoptions);
        foreach ($data as $key => $value) {
            $option = $allformatoptions[$key] + ['type' => PARAM_RAW, 'element_type' => null, 'element_attributes' => [[]]];
            expand_value($data, $data, $option, $key);
        }
        return $data;
    }

    /**
     * Remove the data this format stores outside the course tables.
     *
     * The parent only knows about its own section preferences, so the resume point this
     * plugin stores per course would otherwise outlive the course it describes and only
     * ever be cleared by a privacy request.
     */
    public function delete_format_data() {
        global $DB;

        parent::delete_format_data();

        $course = $this->get_course();

        $DB->delete_records('user_preferences', ['name' => \format_mtubebs\local\resume::PREFIX . (int) $course->id]);
    }

    /**
     * Definitions of the additional options that this course format uses for course.
     *
     * Topics format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        global $PAGE;
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'formatoptionstext' => [
                    'default' => get_config('format_mtubebs', 'formatoptions'),
                    'type' => PARAM_RAW,
                ],
            ];
        }
        if ($foreditform) {
            $courseformatoptionsedit = [
                'formatoptions' => [
                    'label' => '<i class="fa fa-cog fa-fw"></i> ' . new lang_string('editformatoptions', 'format_mtubebs'),
                    'element_type' => 'button',
                    'element_attributes' => [
                        get_string('editformatoptions', 'format_mtubebs'),
                    ],
                ],
                'formatoptionstext' => [
                    'label' => '',
                    'element_type' => 'hidden',
                    'element_attributes' => [],
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
            $PAGE->requires->js_call_amd('format_mtubebs/edit_form_helper', 'init', []);
        }

        return $courseformatoptions;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Add section type to section edit form.
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function section_format_options($foreditform = false) {
        $editoptions = [];

        $fields = [
            'shortname' => ['', PARAM_TEXT],
            'showcollapsed' => [0, PARAM_INT],
            'hidesectionheader' => [0, PARAM_INT],
            'hidesectiondescription' => [0, PARAM_INT],
        ];
        foreach ($fields as $key => [$default, $type]) {
            $editoptions[$key] = [
                'default' => $default,
                'type' => $type,
            ];
        }

        if ($foreditform) {
            $editformoptions = [
                'shortname' => [
                    'label' => get_string('shortname', 'format_mtubebs'),
                    'element_type' => 'text',
                ],
                'hidesectionheader' => [
                    'label' => get_string('hidesectionheader', 'format_mtubebs'),
                    'element_type' => 'advcheckbox',
                    // 4th addElement() arg is the text after the box, not HTML attributes.
                    'element_attributes' => [''],
                ],
                'hidesectiondescription' => [
                    'label' => get_string('hidesectiondescription', 'format_mtubebs'),
                    'element_type' => 'advcheckbox',
                    'element_attributes' => [''],
                ],
                'showcollapsed' => [
                    'label' => get_string('collapsible', 'format_mtubebs'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            1 => get_string('collapsedbydefault', 'format_mtubebs'),
                            0 => get_string('expandedbydefault', 'format_mtubebs'),
                            -1 => get_string('nevercollapsed', 'format_mtubebs'),
                        ],
                    ],
                ],
            ];

            $editoptions = array_merge_recursive($editoptions, $editformoptions);
        }

        return $editoptions;
    }

    /**
     * Returns the course display mode.
     *
     * @return int
     */
    public function get_course_display(): int {
        return COURSE_DISPLAY_SINGLEPAGE;
    }

    /**
     * Get format options
     *
     * @param int|stdClass|section_info $section Section object from database or just field course_sections.section
     * @return array of options
     */
    public function get_format_options($section = null) {
        if ($section !== null) {
            return parent::get_format_options($section);
        }
        $defaultoptions = format_mtubebs_get_default_format_options();
        $options = parent::get_format_options($section)['formatoptionstext'];
        $options = json_decode($options, true);
        if (!is_array($options)) {
            // Nothing saved yet, neither in the course nor in the site settings.
            $options = [];
        }

        // Only the options this format has, with the default of those that are not saved.
        $options = array_intersect_key($options, $defaultoptions);
        foreach ($defaultoptions as $key => $value) {
            if (!isset($options[$key])) {
                $options[$key] = $value;
            }
        }

        $context = context_course::instance($this->get_course()->id);
        $formatoptions = ['context' => $context];

        if (isset($options['coursecontactlabel'])) {
            $options['coursecontactlabel'] = format_string($options['coursecontactlabel'], true, $formatoptions);
        }
        if (isset($options['links'])) {
            $options['links'] = format_string($options['links'], true, $formatoptions);
        }
        if (isset($options['currentsectionlabel'])) {
            $options['currentsectionlabel'] = format_string($options['currentsectionlabel'], true, $formatoptions);
        }
        if (isset($options['coursecontacts'])) {
            $options['coursecontacts'] = format_mtubebs_normalize_coursecontacts($options['coursecontacts']);
        }
        $options['formatoptionstext'] = parent::get_format_options($section)['formatoptionstext'];
        return format_mtubebs_scalarize_format_options($options);
    }

    /**
     * Return format options for external functions.
     *
     * @return array Scalar format options.
     */
    public function get_config_for_external() {
        return $this->get_format_options();
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place.
 *
 * @package format_mtubebs
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return inplace_editable
 */
function format_mtubebs_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'mtubebs'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Allowed extensions for course overview image uploads.
 *
 * @return string[]
 */
function format_mtubebs_course_image_allowed_extensions(): array {
    return ['gif', 'jpe', 'jpeg', 'jpg', 'png', 'svg', 'svgz', 'webp'];
}

/**
 * Resolve the stored course overview image file, including non-GD formats such as SVG.
 *
 * @param int $courseid Course id.
 * @return stored_file|null
 */
function format_mtubebs_get_course_overview_image_stored_file(int $courseid): ?stored_file {
    $context = context_course::instance($courseid);
    $fs = get_file_storage();
    $allowed = format_mtubebs_course_image_allowed_extensions();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder', false);

    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }

        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            continue;
        }

        return $file;
    }

    return null;
}

/**
 * Resolve the course image URL, including non-GD overview files such as SVG.
 *
 * @param stdClass|int $course Course record or course id.
 * @return string|null Pluginfile URL or null when no overview image exists.
 */
function format_mtubebs_get_course_overview_image_url($course): ?string {
    $courseid = is_object($course) ? (int) $course->id : (int) $course;

    $file = format_mtubebs_get_course_overview_image_stored_file($courseid);
    if ($file) {
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            null,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    $courseimage = \core_course\external\course_summary_exporter::get_course_image((object) ['id' => $courseid]);

    return $courseimage ?: null;
}

/**
 * Convert a format option value to a scalar suitable for external APIs.
 *
 * @param mixed $value Format option value.
 * @return string|int|float
 */
function format_mtubebs_scalarize_format_option($value) {
    if ($value === null) {
        return '';
    }
    if (is_array($value) || is_object($value)) {
        return json_encode($value);
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return $value;
}

/**
 * Ensure all format option values are scalars for webservice responses.
 *
 * @param array $options Decoded format options.
 * @return array Scalar format options.
 */
function format_mtubebs_scalarize_format_options(array $options): array {
    $scalarized = [];
    foreach ($options as $key => $value) {
        if (strpos((string) $key, 'mform_') === 0) {
            continue;
        }
        $scalarized[$key] = format_mtubebs_scalarize_format_option($value);
    }
    return $scalarized;
}

/**
 * Normalize course contacts from legacy or current storage formats.
 *
 * @param mixed $contacts Raw value from formatoptionstext.
 * @return array List of ['userid' => int, 'description' => string] in display order.
 */
function format_mtubebs_normalize_coursecontacts($contacts): array {
    if ($contacts === null || $contacts === '' || $contacts === []) {
        return [];
    }

    if (is_string($contacts)) {
        $decoded = json_decode($contacts, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return format_mtubebs_normalize_coursecontacts($decoded);
        }
        $contacts = array_map('trim', explode(',', $contacts));
    }

    if (!is_array($contacts)) {
        return [];
    }

    $normalized = [];
    $seen = [];

    foreach ($contacts as $entry) {
        if (is_array($entry) && array_key_exists('userid', $entry)) {
            $userid = (int) $entry['userid'];
            if ($userid <= 0 || isset($seen[$userid])) {
                continue;
            }
            $seen[$userid] = true;
            $normalized[] = [
                'userid' => $userid,
                'description' => isset($entry['description']) ? (string) $entry['description'] : '',
            ];
        } else if (is_numeric($entry)) {
            $userid = (int) $entry;
            if ($userid <= 0 || isset($seen[$userid])) {
                continue;
            }
            $seen[$userid] = true;
            $normalized[] = [
                'userid' => $userid,
                'description' => '',
            ];
        }
    }

    return $normalized;
}

/**
 * Build course contacts editor rows for the format options form.
 *
 * @param context $context Course context.
 * @param mixed $savedcontacts Saved course contacts value.
 * @return array Row data for the coursecontacts-editor template.
 */
function format_mtubebs_get_coursecontacts_editor_rows(context $context, $savedcontacts): array {
    $savedcontacts = format_mtubebs_normalize_coursecontacts($savedcontacts);
    $users = get_enrolled_users($context, 'moodle/course:manageactivities');
    $rows = [];
    $used = [];

    foreach ($savedcontacts as $contact) {
        $userid = $contact['userid'];
        if (!isset($users[$userid])) {
            continue;
        }
        $rows[] = [
            'userid' => $userid,
            'fullname' => fullname($users[$userid]),
            'checked' => true,
            'description' => $contact['description'],
        ];
        $used[$userid] = true;
    }

    $remaining = [];
    foreach ($users as $userid => $user) {
        if (isset($used[$userid])) {
            continue;
        }
        $remaining[$userid] = fullname($user);
    }
    asort($remaining, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($remaining as $userid => $fullname) {
        $rows[] = [
            'userid' => $userid,
            'fullname' => $fullname,
            'checked' => false,
            'description' => '',
        ];
    }

    return $rows;
}

/**
 * Render the course contacts editor HTML for the format options form.
 *
 * @param context $context Course context.
 * @param mixed $savedcontacts Saved course contacts value.
 * @return string Rendered HTML.
 */
function format_mtubebs_render_coursecontacts_editor(context $context, $savedcontacts): string {
    global $OUTPUT;

    return $OUTPUT->render_from_template('format_mtubebs/coursecontacts-editor', [
        'rows' => format_mtubebs_get_coursecontacts_editor_rows($context, $savedcontacts),
    ]);
}

/**
 * Get the default format options for the mtubebs course format.
 *
 * @return array The default format options as an associative array.
 */
function format_mtubebs_get_default_format_options(): array {
    $data = new stdClass();
    $fields = format_mtubebs_get_default_options();
    foreach ($fields['text'] as $key => $value) {
        $data->$key = $value;
    }
    foreach ($fields['boolean'] as $key => $value) {
        $data->$key = $value;
    }
    foreach ($fields['int'] as $key => $value) {
        $data->$key = $value;
    }
    foreach ($fields['raw'] as $key => $value) {
        $data->$key = $value ? explode(',', $value) : [];
    }
    return (array)$data;
}

/**
 * Get default options for format_mtubebs
 *
 * @return array
 */
function format_mtubebs_get_default_options(): array {
    return [
        'boolean' => [
            'shownews' => 1,
            'showtimeline' => 1,
            'showbadge' => 0,
            'showcompletion' => 0,
            'showsectionnumber' => 1,
            'showcourseimage' => 1,
            'showcoursecontacts' => 1,
            'showsearch' => 1,
            'distractionfreemode' => 0,
            'collapsedonstart' => 0,
            'showbacktosection' => 1,
            'showcourseindex' => 1,
            'showactivitynav' => 1,
            'showgradenav' => 1,
            'pincurrentsection' => 0,
            'resumesection' => 0,
            'showlabels' => 1,
            'hideresize' => 1,
            'hidemenuforlearners' => 0,
            'stickynav' => 1,
        ],
        'int' => [
            'useshortname' => 0,
        ],
        'text' => [
            'links' => '',
            'sectionlabel' => 'section',
            'currentsectionlabel' => get_string('current', 'format_mtubebs'),
            'coursevideo' => '',
            'coursecontactlabel' => get_string('taughtby', 'format_mtubebs'),
            'origin' => '',
            'announcementtitle' => '',
            'announcementdesc' => '',
            'announcementlink' => '',
            'gradetablestyle' => 'core',
        ],
        'raw' => [
            'coursecontacts' => null,
            'announcementshowuntil' => null,
        ],
    ];
}

/**
 * Get the sections in the order they are shown, the highlighted one first when it is pinned.
 *
 * @param stdClass $course The course object.
 * @param array $formatoptions The course format options.
 * @return section_info[]
 */
function format_mtubebs_get_sectioninfo_sequence($course, $formatoptions) {
    $sections = get_fast_modinfo($course)->get_section_info_all();
    $marker = (int) $course->marker;
    if (empty($formatoptions['pincurrentsection']) || !isset($sections[$marker])) {
        return array_values($sections);
    }
    $current = $sections[$marker];
    unset($sections[$marker]);
    return [$current, ...array_values($sections)];
}
