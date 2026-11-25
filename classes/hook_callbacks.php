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

/**
 * Class hook_callbacks
 *
 * @package    format_mtubebs
 * @copyright  2024 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Load AMD for MooTube Basic plugin admin settings pages.
     *
     * Must not run from settings.php because that file is included while the admin tree is building.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function init_plugin_admin_settings(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;

        $allowedpagetypes = [
            'admin-setting-format_mtubebs',
            'admin-setting-formatsettings',
            'admin-setting-upgradesettings',
        ];
        if (!in_array($PAGE->pagetype, $allowedpagetypes, true)) {
            return;
        }

        $PAGE->requires->js_call_amd('format_mtubebs/edit_form_helper', 'init', []);
    }

    /**
     * Send the user to the course page where the format shows its sections, and mark distraction-free pages.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function redirect_to_course_page(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            // Do nothing during installation or upgrade.
            return;
        }

        if (!$PAGE->course || $PAGE->course->format !== 'mtubebs') {
            return;
        }

        if (self::is_distraction_free_page()) {
            $PAGE->add_body_class('mtubebs-distraction-free');
            // The title of these pages is the course name.
            if (!$PAGE->cm) {
                $PAGE->add_body_class('mtubebs-distraction-free-nomod');
            }
        }

        $force = optional_param('forceopen', 0, PARAM_INT);

        // When editing, we prefer showing all sections than separate sections.
        // So we are redirecting to the course page with an anchor to the target section.
        if ($force == 0 && $PAGE->user_is_editing()) {
            if ($PAGE->bodyid == 'page-course-view-mtubebs') {
                $section = optional_param('section', -1, PARAM_INT);
                if ($section >= 0) {
                    $url = new \moodle_url('/course/view.php', ['id' => $PAGE->course->id]);
                    $url->set_anchor('section-' . $section);
                    $PAGE->set_url($url);
                    redirect($url);
                }
            }
            return;
        }
        // The course page shows a section itself when it gets a sectionid parameter.
        if ($PAGE->bodyid == 'page-course-view-section-mtubebs') {
            $id = optional_param('id', 0, PARAM_INT);
            // Redirect to the course page.
            redirect(new \moodle_url('/course/view.php', ['id' => $PAGE->course->id, 'sectionid' => $id]));
        }
    }

    /**
     * Add the "Back to section" link to activity pages, and the way out of distraction-free pages.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function render_activity_nav(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }

        $ismtubebs = $PAGE->course && $PAGE->course->format === 'mtubebs';
        $distractionfree = $ismtubebs && self::is_distraction_free_page();

        // Display navigation buttons when viewing a module.
        if ($ismtubebs && $PAGE->cm && ($PAGE->pagelayout === 'incourse' || $distractionfree)) {
            // The section and overview buttons belong to the activity's main page. Every other page of the
            // activity only links back to it. Compare the body id rather than the script, because a module
            // can serve a sub-page from view.php (assign's grading table sets its own page type).
            $isviewpage = $PAGE->bodyid === "page-mod-{$PAGE->cm->modname}-view";
            $backhtml = '';

            if (!$isviewpage) {
                $backhtml = '<a href="'
                    . new \moodle_url("/mod/{$PAGE->cm->modname}/view.php", ['id' => $PAGE->cm->id])
                    . '" class="btn btn-subtle-primary btn-sm mb-2" type="button">
                    <i class="fa fa-chevron-left me-2"></i>
                    ' . get_string('backto', 'format_mtubebs', format_string($PAGE->cm->name)) . '
                </a>';
            } else {
                // Show back to course button with target section.
                $sectionname = get_section_name($PAGE->course, $PAGE->cm->sectionnum);
                $options = course_get_format($PAGE->course)->get_format_options();
                // Distraction-free mode covers the navbar, so this link is the way out.
                $backtosection = (isset($options['showbacktosection']) && $options['showbacktosection'] != 0)
                    || $distractionfree ? 1 : 0;
                if ($backtosection) {
                    // Check if user can view the section.
                    $section = get_fast_modinfo($PAGE->course)->get_section_info_by_id($PAGE->cm->sectionid);
                    if (!$section || $section->uservisible == 0) {
                        // Back to course page.
                        $backhtml .= '<a href="' . new \moodle_url('/course/view.php', [
                            'id' => $PAGE->course->id,
                        ]) . '" class="btn btn-subtle-primary btn-sm mb-2" type="button">
                        <i class="fa fa-chevron-left me-2"></i>'
                            . get_string('backto', 'format_mtubebs', get_string('course'))
                            . '</a>';
                    } else {
                        $backhtml .= '<a href="' . new \moodle_url('/course/view.php', [
                            'id' => $PAGE->course->id,
                            'sectionid' => $PAGE->cm->sectionid,
                            'highlight' => $PAGE->cm->id,
                        ]) . '" class="btn btn-subtle-primary btn-sm mb-2" type="button">
                    <i class="fa fa-chevron-left me-2"></i>'
                            . get_string('backto', 'format_mtubebs', format_string($sectionname))
                            . '</a>';
                    }
                }
            }

            self::add_activity_nav($hook, $backhtml);
        } else if ($distractionfree) {
            // Other pages of the course have no navbar left to get back with.
            $backhtml = '<a href="' . new \moodle_url('/course/view.php', [
                'id' => $PAGE->course->id,
            ]) . '" class="btn btn-subtle-primary btn-sm mb-2" type="button">
            <i class="fa fa-chevron-left me-2"></i>'
                . get_string('backto', 'format_mtubebs', get_string('course'))
                . '</a>';
            self::add_activity_nav($hook, self::add_course_menu($hook) . $backhtml);
        }
    }

    /**
     * Add the course menu, which replaces the page's secondary navigation, as an off-canvas panel.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return string The button that opens the panel, empty if the page has no menu
     */
    protected static function add_course_menu(\core\hook\output\before_footer_html_generation $hook): string {
        global $OUTPUT, $PAGE;

        $context = \context_course::instance($PAGE->course->id);
        $options = course_get_format($PAGE->course)->get_format_options();
        if (!empty($options['hidemenuforlearners']) && !has_capability('moodle/course:manageactivities', $context)) {
            return '';
        }

        $items = $PAGE->has_secondary_navigation() ? self::export_menu_items($PAGE->secondarynav->children) : [];
        // A lone item is the course link, which the back button already is.
        if (count($items) < 2) {
            return '';
        }

        $hook->add_html($OUTPUT->render_from_template('format_mtubebs/course_menu', [
            'title' => format_string($PAGE->course->fullname, true, ['context' => $context]),
            'items' => $items,
        ]));

        // Bootstrap adds its backdrop to the body, so the panel has to be there to sit above it.
        $PAGE->requires->js_amd_inline('document.body.append(document.getElementById("mtubebs-course-menu"));');

        $label = s(get_string('togglesidebar', 'format_mtubebs'));
        return '<button type="button" class="btn btn-icon icons-collapse-expand mtubebs-course-menu-toggle"'
            . ' data-bs-toggle="offcanvas" data-bs-target="#mtubebs-course-menu" aria-controls="mtubebs-course-menu"'
            . ' title="' . $label . '" aria-label="' . $label . '">'
            . '<i class="fa fa-bars" aria-hidden="true"></i></button>';
    }

    /**
     * Export navigation nodes for the course menu.
     *
     * @param iterable $nodes
     * @return array
     */
    protected static function export_menu_items(iterable $nodes): array {
        $items = [];
        foreach ($nodes as $node) {
            $url = $node->action();
            // Other children, like the profile under Participants, only serve the breadcrumb.
            $children = $node->showchildreninsubmenu ? self::export_menu_items($node->children) : [];
            if (!$url && !$children) {
                continue;
            }
            $items[] = [
                'text' => (string) $node->text,
                'url' => $url ? $url->out(false) : '',
                'active' => (bool) $node->isactive,
                'haschildren' => !empty($children),
                'children' => $children,
            ];
        }
        return $items;
    }

    /**
     * Add the navigation bar above the page content.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @param string $backhtml
     */
    protected static function add_activity_nav(
        \core\hook\output\before_footer_html_generation $hook,
        string $backhtml
    ): void {
        global $PAGE;

        if ($backhtml === '') {
            return;
        }

        $html = '<div id="activity-nav" class="mx-3 d-none d-flex justify-content-between align-items-center gap-2">'
            . '<div class="activity-nav-back">' . $backhtml . '</div>'
            . '<div class="activity-nav-actions"></div>'
            . '</div>';
        $hook->add_html($html);

        // Move #activity-nav to the body in distraction-free mode, to the top of #page-header otherwise.
        $PAGE->requires->js_amd_inline('
            const widget = document.querySelector("#activity-nav");
            const distractionfree = document.body.classList.contains("mtubebs-distraction-free");
            const target = window.self === window.top
                ? (distractionfree ? document.body : document.querySelector("#page-header")) : null;
            if (target) {
                target.prepend(widget);
                widget.classList.remove("d-none", "mx-3");
                // As on the course page, the navbar is out of reach in distraction-free mode.
                if (distractionfree) {
                    const editswitch = document.querySelector(".editmode-switch-form");
                    const usermenu = document.querySelector(".usermenu");
                    if (editswitch || usermenu) {
                        // Themes style the menu through its navbar, so it needs one around it.
                        const navbar = document.createElement("div");
                        navbar.className = "mtubebs-nav-usermenu navbar fixed-top";
                        navbar.append(...[editswitch, usermenu].filter(Boolean));
                        widget.querySelector(".activity-nav-actions").append(navbar);
                    }
                    // Moove has these tabs outside #page, where the full-window page would cover them.
                    const tabs = document.querySelector("#page-wrapper > .secondary-navigation");
                    if (tabs) {
                        document.querySelector("#topofscroll")?.prepend(tabs);
                    }
                }
            } else {
                widget.remove();
            }
        ');
    }

    /**
     * Whether this page of a MooTube Basic course fills the window in distraction-free mode.
     *
     * The course page has its own mode, but other pages share its layout, such as the learning outcomes page.
     * The enrolment page is left out: someone not enrolled has no course page to go back to, and needs the navbar.
     *
     * @return bool
     */
    protected static function is_distraction_free_page(): bool {
        global $PAGE;

        if (
            !in_array($PAGE->pagelayout, ['incourse', 'standard', 'report', 'admin', 'mypublic', 'course'], true)
            || str_starts_with($PAGE->pagetype, 'course-view-')
            || $PAGE->pagetype === 'enrol-index'
            || optional_param('embed', 0, PARAM_INT)
        ) {
            return false;
        }
        $options = course_get_format($PAGE->course)->get_format_options();
        return !empty($options['distractionfreemode'])
            && self::is_distraction_free_user(\context_course::instance($PAGE->course->id));
    }

    /**
     * Whether distraction-free mode applies to the current user.
     *
     * Never while editing. Otherwise it is for those who can't edit the course's activities, or everyone
     * if the site says so.
     *
     * @param \context_course $context
     * @return bool
     */
    public static function is_distraction_free_user(\context_course $context): bool {
        global $PAGE;

        if ($PAGE->user_is_editing()) {
            return false;
        }
        return get_config('format_mtubebs', 'distractionfreeforall')
            || !has_capability('moodle/course:manageactivities', $context);
    }
}
