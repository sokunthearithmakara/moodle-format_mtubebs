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
 * Primary MooTube Basic course view bootstrap and section routing.
 *
 * @module     format_mtubebs/main
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {getString} from 'core/str';
import UI from './ui';
import Route from './route';
import ModalLauncher from './modal_launcher';

const elems = UI.elems;

/** @type {string} Manual completion toggle buttons the format renders. */
const MANUAL_TOGGLE_SELECTOR = 'button[data-action="toggle-manual-completion-mtubebs"]';

/**
 * Re-render every manual completion toggle of an activity except the one just toggled.
 *
 * @param {number|string} cmid Course module id.
 * @param {boolean} completed New completion state.
 * @param {HTMLElement|null} clicked The button that was toggled (already re-rendered).
 */
const syncOtherManualToggles = async(cmid, completed, clicked) => {
    const others = [...document.querySelectorAll(`${MANUAL_TOGGLE_SELECTOR}[data-cmid="${cmid}"]`)]
        .filter((button) => button !== clicked && !button.contains(clicked) && !clicked?.contains(button));
    for (const button of others) {
        const {html, js} = await Templates.renderForPromise('format_mtubebs/completion_manual', {
            cmid,
            activityname: button.getAttribute('data-activityname'),
            overallcomplete: completed,
            overallincomplete: !completed,
            istrackeduser: true,
            withavailability: button.getAttribute('data-hasavailability') || 0,
            normalbutton: !button.classList.contains('btn-sm'),
        });
        await Templates.replaceNode(button, html, js);
    }
};

/**
 * Point out the activity the learner comes back from.
 *
 * @param {Number|String} cmid Course module id.
 */
const highlightActivity = (cmid) => {
    const activity = elems.mainwrapper.find(`li.activity[data-id="${cmid}"]`);
    if (!activity.length) {
        return;
    }
    activity.addClass('active');
    setTimeout(() => activity.removeClass('active'), 1000);
    activity[0].scrollIntoView({block: 'nearest'});
};

/**
 * Point the edit switch and the role links at the section on screen.
 *
 * @param {Number} courseid
 */
const updateSectionUrls = (courseid) => {
    const sectionid = window.MTubeBs.activesection.sectionid;
    const courseurl = M.cfg.wwwroot + '/course/view.php?id=' + courseid;
    const editurl = sectionid > 0 ? M.cfg.wwwroot + '/course/section.php?id=' + sectionid : courseurl;
    const editswitch = $('.editmode-switch-form');
    editswitch.find('input[name="pageurl"]').val(editurl);
    editswitch.find('input[name="setmode"]').attr('data-pageurl', editurl);

    const returnurl = new URL(courseurl);
    if (sectionid > 0) {
        returnurl.searchParams.set('sectionid', sectionid);
    }
    $('a.dropdown-item[href*="switchrole.php"], #switchrole-toggle').each(function() {
        const url = new URL(this.href);
        url.searchParams.set('returnurl', returnurl.toString());
        this.href = url.toString();
    });
};

/**
 * Show the section the page opens on.
 *
 * @param {Number} resumesectionid The section this learner last visited, 0 for none.
 */
const showInitialSection = async(resumesectionid) => {
    const subsections = JSON.parse($('#subsections').val());
    const findButton = (attribute, value) => elems.header.find(`button[data-${attribute}="${value}"]`).first();
    const show = (button) => UI.renderSectionContent({
        sectionid: button.attr('data-sectionid'),
        section: button.attr('data-section'),
    });

    // A link to an activity on the course page: show the section it is in.
    const hash = window.location.hash;
    if (/^#module-\d+$/.test(hash)) {
        const cmid = hash.replace('#module-', '');
        history.replaceState(null, null, ' ');
        $(document).one('section:changed.module', () => highlightActivity(cmid));
        if (await UI.renderSectionContent({cmid})) {
            return;
        }
        $(document).off('section:changed.module');
    }

    const params = new URL(window.location.href).searchParams;
    const sectionid = parseInt(params.get('sectionid'), 10);
    if (sectionid) {
        let button = findButton('sectionid', sectionid);
        if (!button.length) {
            button = findButton('sectionid', subsections.find(x => x.id == sectionid)?.parentid);
        }
        if (button.length) {
            show(button);
            return;
        }
    }

    let section = parseInt(params.get('section'), 10) || 0;
    section = subsections.find(x => x.num == section)?.parentnum ?? section;

    let button = section ? findButton('section', section) : $();
    if (!section) {
        // The section the teacher highlighted, then where this learner left off: what the course wants
        // shown outranks a personal history.
        button = elems.header.find('button[data-sectionid].current').first();
        if (!button.length && resumesectionid) {
            button = findButton('sectionid', resumesectionid);
        }
    }
    if (!button.length) {
        button = elems.header.find('button[data-sectionid]').first();
    }
    if (button.length) {
        show(button);
    } else {
        elems.pageprogress.hide();
    }
};

export const init = async(courseid, coursename, canedit, nodes, resumesectionid = 0) => {
    const formatoptions = UI.formatoptions;
    let highlight = new URLSearchParams(window.location.search).get('highlight');
    let launchConsumed = false;
    // Capture launch intent now: section render rewrites the address bar before the
    // deferred launcher runs, so we must read the original URL up front.
    const initialLaunchParams = ModalLauncher.parseLaunchParams(window.location.search);

    // Removing limitedwidth class from the body.
    elems.body.removeClass('limitedwidth');

    // Announcement.
    if (formatoptions.announcementdesc != '') {
        // Remove announcement if sessionstorage is set.
        if (sessionStorage.getItem('hideannouncement-' + M.cfg.courseId) == 'true') {
            $('#announcement-wrapper').remove();
        }
        // Remove announcement on close.
        $('#close-announcement').on('click', function() {
            sessionStorage.setItem('hideannouncement-' + M.cfg.courseId, 'true');
            $('#announcement-wrapper').remove();
        });
    }

    window.MTubeBs = {
        ...window.MTubeBs,
        activesection: null,
        updatesection: false, // Whether refreshing the section.
        validsections: [],
        canedit,
    };

    UI.init(nodes);

    if (formatoptions.showsearch == 1) {
        const {searchmodal} = await import('format_mtubebs/features/search');
        searchmodal();
    }

    if (formatoptions.showtimeline == 1) {
        const timelinemodal = await import('format_mtubebs/features/timeline');
        timelinemodal.init();
    }

    if (formatoptions.showcompletion == 1 && !canedit) {
        const completionmodal = await import('format_mtubebs/features/completion');
        completionmodal.init();
    }

    if (formatoptions.showgradenav == 1 && !canedit) {
        const gradenavmodal = await import('format_mtubebs/features/grade');
        gradenavmodal.init();
    }

    // This is where we switch the section.
    // window.MTubeBs.updatesection == true indicates that the section has been updated after changes (e.g. completion).
    // window.MTubeBs.updatesection == false indicates a fresh section load
    // (e.g. switching from a section or clicking on the refresh button).
    $(document).on('click', '#section-header [data-sectionid]', async function(e) {
        e.preventDefault();
        elems.pageprogress.show();

        const section = $(this).attr('data-section');
        const sectionid = $(this).attr('data-sectionid');

        UI.renderSectionContent({section, sectionid});

        $(document).off('section:changed.' + sectionid);
        $(document).one('section:changed.' + sectionid, async function() {
            // Take care of the history.
            const url = new URL(M.cfg.wwwroot + '/course/view.php?id=' + courseid);
            let doctitle = await getString('course') + ': ' + coursename;
            if (sectionid > 0) {
                url.searchParams.set('sectionid', sectionid);
                const name = window.MTubeBs.validsections.find(x => x.section == section)?.name || section;
                doctitle = await getString(formatoptions.sectionlabel, 'format_mtubebs') + ': ' + name;
            }
            Route.updateBrowser(doctitle, url.toString(), true);
            elems.pageprogress.hide();
        });
    });

    $(document).on('section:change', function(e) {
        elems.header.find('[data-sectionid].active').removeClass('active');
        elems.header.find(`[data-sectionid="${e.detail.sectionid}"]`).addClass('active');
    });

    $(document).on('section:changed', async function(e) {
        e.preventDefault();
        setTimeout(function() {
            window.MTubeBs.updatesection = false;
        }, 5000);
        if (!e.detail) {
            return;
        }

        elems.body.attr('data-sectionid', window.MTubeBs.activesection.sectionid || 0);
        updateSectionUrls(courseid);

        if (e.detail.updatesection) {
            return;
        }

        elems.sectioncontent.show();

        if (highlight) {
            highlightActivity(highlight);
            highlight = null;
        }

        // Run after the section is in place. initialLaunchParams survives the section URL rewrite.
        if (!launchConsumed) {
            launchConsumed = true;
            await UI.delay(500);
            await ModalLauncher.processLaunchFromUrl({
                courseid,
                canedit,
                formatoptions,
                params: initialLaunchParams,
            });
        }
    });

    $(document).on('core_course:manualcompletiontoggled core_course:manualcompletiontoggled-mtubebs', async function(e) {
        // If the activity has availability, refresh the content.
        if (e.detail.hasAvailability == 1) {
            UI.renderSectionContent(window.MTubeBs.activesection, true);
        }

        // The same activity can have more than one toggle on the page: keep the
        // ones that were not clicked in step with the new state.
        await syncOtherManualToggles(e.detail.cmid, e.detail.completed, e.target);
    });

    // Interactive Video only updates its own card when its popup closes, which it reports more than once.
    let popupRefreshTimer = null;
    let popupInSidemenu = false;
    $(document).on(ModalEvents.hidden, '#playermodal', function() {
        // It leaves this behind when the popup is closed with the Back button of the browser.
        elems.body.removeClass('overflow-hidden');
        // A video of the sidemenu: its card needs an update too.
        const src = $(this).find('iframe#ivplayer').attr('src');
        const cmid = src ? new URL(src, window.location.href).searchParams.get('id') : 0;
        popupInSidemenu ||= elems.sidemenu.find(`li.activity[data-id="${cmid}"]`).length > 0;
        clearTimeout(popupRefreshTimer);
        popupRefreshTimer = setTimeout(() => {
            const section = window.MTubeBs.activesection;
            if (!section || $('#playermodal').hasClass('show')) {
                return;
            }
            // It does not always restore these, such as when it is closed before the player is ready.
            if (section.url) {
                Route.updateBrowser(section.title, section.url, false);
            }
            UI.renderSectionContent(section, true);
            if (popupInSidemenu) {
                popupInSidemenu = false;
                UI.scheduleSidemenuRefresh();
            }
        }, 1000);
    });

    $(function() {
        elems.body.find('#iframecontent').removeClass('d-none');
        elems.pageprogress.show();
        $('li.activity a.text-decoration-none').removeClass('text-decoration-none');
        showInitialSection(resumesectionid);
    });
};
