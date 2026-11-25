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
 * Displays the course
 *
 * @package    format_mtubebs
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

// Retrieve course format option fields and add them to the $course object.
$format = core_courseformat\base::instance($course);
$course = $format->get_course();
$context = context_course::instance($course->id);
$canedit = has_capability('moodle/course:manageactivities', $context);

if (!$PAGE->user_is_editing()) {
    $isenrolled = is_enrolled($context);
    $isguestuser = isguestuser($USER);
    $canusesidenav = !$isguestuser && ($isenrolled || $canedit);
    // Get course image.
    $courseimage = format_mtubebs_get_course_overview_image_url($course);

    if (!$courseimage) {
        $courseimage = $OUTPUT->get_generated_image_for_id($course->id);
    }

    $course->image = $courseimage;

    $formatoptions = $format->get_format_options();

    $distractionfree = $formatoptions['distractionfreemode'] == 1
        && \format_mtubebs\hook_callbacks::is_distraction_free_user($context);
    if ($distractionfree) {
        echo '<div id="distraction-free-mode" class="d-none"></div>';
        $primary = new core\navigation\output\primary($PAGE);
        $primarymenu = $primary->export_for_template();
    }

    // Make sure section 0 is created.
    course_create_sections_if_missing($course, [
        0,
        1,
    ]);

    $renderer = $format->get_renderer($PAGE);

    // Setup the format base instance.
    if (!empty($displaysection)) {
        $format->set_sectionnum($displaysection);
    }

    $modinfo = get_fast_modinfo($course);

    // Sections of the first section menu, refreshed by the page JavaScript.
    $sections = [];
    foreach (format_mtubebs_get_sectioninfo_sequence($course, $formatoptions) as $section) {
        // Skip the general section and subsections.
        if ($section->section == 0 || $section->is_delegated()) {
            continue;
        }

        if (!$format->is_section_visible($section)) {
            continue;
        }

        $sections[] = [
            'id' => $section->id,
            'section' => $section->section,
            'name' => get_section_name($course->id, $section),
            'shortname' => $section->shortname ? format_string($section->shortname) : get_section_name($course->id, $section),
            'restricted' => !$section->available && !empty($section->availableinfo),
            'visible' => $section->visible == 1,
            'current' => ($section->section == $course->marker),
            'showsectionnumber' => $formatoptions['showsectionnumber'] == 1,
        ];
    }

    // Get all subsections.
    $subsections = $modinfo->get_instances_of('subsection');
    $subsections = array_map(function ($subsection) {
        $parentid = $subsection->section;
        $parentnum = $subsection->sectionnum;
        $thissection = \mod_subsection\manager::create_from_coursemodule($subsection)->get_delegated_section_info();

        return [
            'id' => $thissection->id,
            'num' => $thissection->section,
            'parentid' => $parentid,
            'parentnum' => $parentnum,
        ];
    }, $subsections);
    $subsections = array_values($subsections);

    $section = $modinfo->get_section_info(0);
    $outputclass = $format->get_output_classname('content\\section');
    $widget = new $outputclass($format, $section);

    if ($course->newsitems && $formatoptions['shownews'] == 1 && $canusesidenav) {
        require_once($CFG->dirroot . '/mod/forum/lib.php');   // We'll need this.

        if (!$forum = forum_get_course_forum($course->id, 'news')) {
            $formatoptions['shownews'] = 0;
        } else {
            if (empty($modinfo->instances['forum'][$forum->id])) {
                $formatoptions['shownews'] = 0;
            } else {
                $cm = $modinfo->instances['forum'][$forum->id];

                if (!$cm->uservisible && !$canedit) {
                    $formatoptions['shownews'] = 0;
                }

                $modcontext = context_module::instance($cm->id);

                // User must have perms to view discussions in that forum.
                if (!has_capability('mod/forum:viewdiscussion', $modcontext)) {
                    $formatoptions['shownews'] = 0;
                }

                // First work out whether we can post to this group and if so, include a link.
                $newsurl = new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
            }
        }
    } else {
        $formatoptions['shownews'] = 0;
    }

    $formatoptions['links'] = explode("\n", $formatoptions['links']);
    // Remove empty lines.
    $formatoptions['links'] = array_filter($formatoptions['links']);
    // Turn links into array.
    $formatoptions['links'] = array_map(function ($link) use ($course, $USER) {
        $link = explode('|', $link);
        $formattedlink = $link[2];
        // Replace {{userid}} with current user id.
        $formattedlink = str_replace('{{userid}}', $USER->id, $formattedlink);
        // Replace {{courseid}} with current course id.
        $formattedlink = str_replace('{{courseid}}', $course->id, $formattedlink);
        return [
            'label' => $link[0],
            'icon' => $link[1],
            'url' => $formattedlink,
        ];
    }, $formatoptions['links']);

    $course->coursetitle = format_string($course->fullname, true, ['context' => $context]);

    if (empty($formatoptions['showgradenav']) || !$canusesidenav) {
        $formatoptions['showgradenav'] = 0;
    } else if (!$canedit && (!$course->showgrades || !has_capability('moodle/grade:view', $context))) {
        $formatoptions['showgradenav'] = 0;
    }

    $formatoptions['showtimeline'] = $canusesidenav ? $formatoptions['showtimeline'] : 0;

    // The course menu (secondary navigation) can be kept for teachers only.
    $formatoptions['hidemenu'] = !empty($formatoptions['hidemenuforlearners']) && !$canedit;

    $teacherhtml = '';
    if ($formatoptions['showcoursecontacts'] == 1) {
        $contacts = format_mtubebs_normalize_coursecontacts($formatoptions['coursecontacts'] ?? []);
        if (!empty($contacts)) {
            $contactdata = [];
            foreach ($contacts as $entry) {
                $uid = $entry['userid'];
                if ($uid <= 0) {
                    continue;
                }
                // Get the user.
                $user = \core_user::get_user($uid);
                if (!$user) {
                    continue;
                }
                if (!is_enrolled($context, $user)) {
                    continue;
                }
                $contactdata[] = [
                    'email' => $user->email,
                    'name' => fullname($user),
                    'description' => $entry['description'] ?? '',
                    'profile' => $OUTPUT->user_picture($user, [
                        'size' => 100,
                        'link' => true,
                        'courseid' => $course->id,
                    ]),
                    'url' => (new \moodle_url('/user/view.php', [
                        'id' => $user->id,
                        'course' => $course->id,
                    ]))->out(false),
                ];
            }
            if (empty($contactdata)) {
                $formatoptions['showcoursecontacts'] = 0;
            } else {
                $teacherhtml = $OUTPUT->render_from_template('format_mtubebs/coursecontact', [
                    'contactdata' => $contactdata,
                    'isguestuser' => $isguestuser,
                ]);
            }
        }
    }

    $coursevideo = $formatoptions['coursevideo'];
    if ($coursevideo) {
        if ($videotype = interactivevideo_get_type_from_url($coursevideo)) {
            $course->video = $coursevideo;
            $course->videotype = $videotype;
        }
    }

    $content = $format->get_output_classname('content');
    $contentwidget = new $content($format);
    $contenthtml = $renderer->render($contentwidget);

    $formatoptions['sectionlabelplural'] = get_string($formatoptions['sectionlabel'] . '-plural', 'format_mtubebs');

    // Get student role.
    $studentrole = get_config('core', 'gradebookroles');
    if (!empty($studentrole)) {
        $switchroleurl = new moodle_url('/course/switchrole.php', [
            'id' => $course->id,
            'switchrole' => is_role_switched($course->id) ? 0 : $studentrole[0],
            'sesskey' => sesskey(),
            'returnurl' => (new moodle_url('/course/view.php', [
                'id' => $course->id,
            ]))->out(false),
        ]);
        $switchroleurl = $switchroleurl->out(false);
    } else {
        $switchroleurl = '';
    }

    if ($formatoptions['announcementdesc'] != '') {
        if ($formatoptions['announcementshowuntil'] > 0 && $formatoptions['announcementshowuntil'] < time()) {
            $formatoptions['announcementdesc'] = '';
        }
        if ($formatoptions['announcementdesc'] != '') {
            $formatoptions['announcementdesc'] = format_string($formatoptions['announcementdesc']);
            $formatoptions['announcementtitle'] = $formatoptions['announcementtitle'] ?
                format_string($formatoptions['announcementtitle']) : '';
        }
    }

    echo $OUTPUT->render_from_template('format_mtubebs/main', [
        'isroleswitched' => is_role_switched($course->id),
        'course' => $course,
        'content' => $renderer->render($widget) . $contenthtml,
        'teacherhtml' => $teacherhtml,
        'canedit' => $canedit,
        'distractionfree' => $distractionfree,
        'options' => $formatoptions,
        'newsurl' => isset($newsurl) ? $newsurl->out(false) : '',
        'sections' => $sections,
        'wwwroot' => $CFG->wwwroot,
        'user' => $USER,
        'caneditsettings' => has_capability('moodle/course:update', $context),
        'caneditimage' => has_all_capabilities(['moodle/course:update', 'moodle/course:changesummary'], $context),
        'primarymenu' => isset($primarymenu) ? $primarymenu['user'] : '',
        'switchroleurl' => $switchroleurl,
        'isguestuser' => $isguestuser,
    ]);

    echo '<div style="display:none" aria-hidden="true">'
        . '<textarea id="formatoptions" aria-hidden="true" aria-label="' . s(get_string('formatoptions', 'format_mtubebs')) . '">'
        . json_encode($formatoptions) . '</textarea>'
        . '<textarea id="subsections" aria-hidden="true" aria-label="' . s(get_string('subsections', 'format_mtubebs')) . '">'
        . json_encode($subsections) . '</textarea>'
        . '</div>';

    // The section to reopen for a returning learner, already validated against what
    // they may currently see. Passed as its own argument rather than folded into the
    // format options blob above: this is per-user state for this one request, not a
    // setting the course stores.
    $resumesectionid = \format_mtubebs\local\resume::recall((int) $course->id);
    $PAGE->requires->js_call_amd(
        'format_mtubebs/main',
        'init',
        [
            $course->id,
            format_string($course->fullname, true, ['context' => $context]),
            $canedit, // Can edit.
            [
                get_config('format_mtubebs', 'pagenode') ?: '#page',
                get_config('format_mtubebs', 'secondarymenunode') ?: '.secondary-navigation',
                get_config('format_mtubebs', 'drawertoggles') ?: '.drawer-toggles',
                get_config('format_mtubebs', 'topofscrollnode') ?: '#topofscroll, #region-main-box',
            ],
            $resumesectionid,
        ]
    );

    if (has_capability('moodle/course:update', $context)) {
        $PAGE->requires->js_call_amd(
            'format_mtubebs/edit_form_helper',
            'init',
            []
        );
        $PAGE->requires->js_call_amd(
            'format_mtubebs/features/course_image',
            'init',
            [$course->id]
        );
    }
} else {
    // Make sure section 0 is created.
    course_create_sections_if_missing($course, 0);

    $renderer = $format->get_renderer($PAGE);

    // Setup the format base instance.
    if (!empty($displaysection)) {
        $format->set_sectionnum($displaysection);
    }
    // Output course content.
    $outputclass = 'format_mtubebs\\output\\courseformat\\content';
    $widget = new $outputclass($format);
    echo $renderer->render($widget);
}
