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
 * Shared UI helpers, DOM references, section rendering, and layout behaviour.
 *
 * @module     format_mtubebs/ui
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {getString} from 'core/str';
import ModalFactory from 'core/modal';
import ModalEvents from 'core/modal_events';
import {callWebservice} from 'format_mtubebs/ajax_retry';
import {notifyFilterContentUpdated as notifyFilter} from 'core_filters/events';
import {notifyError} from 'format_mtubebs/error_toast';
import {dispatchEvent} from 'core/event_dispatcher';
import Templates from 'core/templates';
import Route from './route';
import {collectSecondaryNavLinks, renderSecondaryNavDropdown} from './secondary_nav';

/** Tooltip triggers only — exclude other Bootstrap toggles (e.g. dropdown) that may also have title. */
const TOOLTIP_TRIGGER_SELECTOR = '[data-bs-toggle="tooltip"], [title]:not([data-bs-toggle])';

export const elems = {
    body: $('body'),
    contentwrapper: $('#content-wrapper'),
    sidemenuwrapper: $('#sidemenu-wrapper'),
    mainwrapper: $('#main-wrapper'),
    headerwrapper: $('#section-header-wrapper'),
    header: $('#section-header'),
    pageprogress: $('.page-progress'),
    usermenu: $('.usermenu'),
    backdrop: $('#container-backdrop'),
    page: $('#page'),
    sidemenu: $('#sidemenu'),
    sidebarwrapper: $('#sidebar-wrapper'),
    sectioncontent: $('#section-content[data-region="main-page"]'),
};

export let formatoptions = JSON.parse($('#formatoptions').val() || '{}');
// Convert formatoptions "0" to ''.
for (let key in formatoptions) {
    if (formatoptions[key] === '0') {
        formatoptions[key] = '';
    }
}

if (!window.MTubeBs) {
    window.MTubeBs = {};
}

const activateTooltips = (target) => {
    if (!target) {
        return;
    }
    // First destroy all tooltips.
    target.find(TOOLTIP_TRIGGER_SELECTOR).each(function() {
        disposeTooltip($(this));
    });
    target.find(TOOLTIP_TRIGGER_SELECTOR).tooltip({
        trigger: 'hover',
        placement: 'top',
        html: true,
    });
};

/**
 * Hide and dispose a Bootstrap tooltip and remove any orphaned tip element.
 *
 * @param {JQuery} $element Tooltip trigger element.
 */
export const disposeTooltip = ($element) => {
    if (!$element?.length) {
        return;
    }

    $element.each(function() {
        if (!this.hasAttribute('data-bs-original-title')) {
            // Never had a tooltip.
            return;
        }
        try {
            // The path of the Tooltip class differs between Moodle versions, the jQuery plugin does not.
            // Do not call hide() before dispose — hide queues an async callback that reads
            // _activeTrigger after dispose() has already set it to null.
            $(this).tooltip('dispose');
        } catch (e) {
            // No tooltip to dispose.
        }
    });

    document.querySelectorAll('body > .tooltip').forEach((tip) => {
        tip.remove();
    });
};

let tooltipClickCleanupBound = false;

/**
 * Dismiss tooltips before click handlers hide or move the trigger (capture phase).
 */
const bindTooltipClickCleanup = () => {
    if (tooltipClickCleanupBound) {
        return;
    }
    tooltipClickCleanupBound = true;

    document.addEventListener('mousedown', (event) => {
        const trigger = event.target.closest?.(TOOLTIP_TRIGGER_SELECTOR);
        if (!trigger) {
            return;
        }
        disposeTooltip($(trigger));
    }, true);
};

/** Must match the transition duration on #sidemenu-wrapper in styles.css. */
const SIDEINFO_SLIDE_MS = 300;

/**
 * Put the course info panel into its collapsed state.
 *
 * The slide is a CSS transform, not an animated `left`. transform is unaffected by the
 * `left: 60px !important` that pins the panel under 1200px and by `position: sticky`
 * above it, so one mechanism covers every width - and nothing has to override the
 * panel's position for the duration of the animation any more.
 *
 * @param {Boolean} animated Slide out, or jump straight to collapsed.
 * @param {Function} [done] Called once collapsed.
 */
const collapseSideInfo = (animated, done) => {
    const panel = elems.sidemenuwrapper;

    if (!animated) {
        panel.addClass('mtubebs-collapsed hide');
        if (done) {
            done();
        }
        return;
    }

    panel.addClass('mtubebs-collapsed');
    setTimeout(() => {
        panel.addClass('hide');
        if (done) {
            done();
        }
    }, SIDEINFO_SLIDE_MS);
};

/**
 * Slide the course info panel back in.
 */
const expandSideInfo = () => {
    const panel = elems.sidemenuwrapper;
    panel.removeClass('hide');

    // Force a reflow between dropping display:none and dropping the transform, so the
    // browser has a start state to transition from rather than jumping to the end.
    if (panel[0]) {
        void panel[0].offsetWidth;
    }

    panel.removeClass('mtubebs-collapsed');
};

const init = async(nodes) => {
    initModalEnhancements();
    bindTooltipClickCleanup();

    elems.page = $(nodes[0]);

    // Get the sideinfo state from local storage.
    let sideinfo = localStorage.getItem('sideinfo-' + M.cfg.courseId);
    if (sideinfo == 'false') {
        collapseSideInfo(false);
    } else if (sideinfo == 'true') {
        elems.sidemenuwrapper.removeClass('hide mtubebs-collapsed');
    }

    // The activity icon follows the link of the activity name.
    $(document).on('click', 'li.activity .activity-icon', function(e) {
        const link = $(this).closest('li.activity').find('.activityname a.aalink')[0];
        link?.dispatchEvent(new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            view: window,
            ctrlKey: e.ctrlKey,
            metaKey: e.metaKey,
            shiftKey: e.shiftKey,
        }));
    });

    // Move #content-wrapper to the top child of the #page.
    elems.contentwrapper.detach().prependTo(nodes[0]);

    const secondaryLinks = collectSecondaryNavLinks($(nodes[1]));
    $('#secondarymenu-side ul').html(renderSecondaryNavDropdown(secondaryLinks));
    $(nodes[1]).hide();

    // Move .drawer-toggles to before #topofscroll.
    $(nodes[2]).detach().insertAfter('#content-wrapper');

    // Settle the panel before the page is revealed. Under 1200px this panel
    // is a full-height fixed overlay with a backdrop, so collapsing it after mtubebs-ready
    // means it is briefly painted over the course - the flash this block avoids.
    if ($(window).width() < 1200) {
        collapseSideInfo(false);
    }

    // Show #content-wrapper.
    elems.body.addClass('theme-' + M.cfg.theme + ' mtubebs-ready');

    // Detach the edit mode switch and usermenu if in distraction-free mode.
    if (document.getElementById('distraction-free-mode')) {
        $('.usermenu-wrapper').empty();
        $('.editmode-switch-form').first().detach().appendTo('.usermenu-wrapper');
        elems.usermenu.first().detach().appendTo('.usermenu-wrapper');
    }

    // Handle resize button.
    $(document).on('click', '#expand-toggle', function(e) {
        e.preventDefault();
        $('body').toggleClass('isexpanded');
    });

    // Handle secondary menu toggle.
    $(document).on('click', '#secondarymenu-toggle', function(e) {
        e.preventDefault();
        let $secondmenu = $('#secondarymenu-side');
        if ($secondmenu.hasClass('show')) {
            $secondmenu.removeClass('show');
            elems.backdrop.hide();
        } else {
            $secondmenu.addClass('show');
            elems.backdrop.show();

            $(document).off('click.secondarymenu');
            $(document).one('click.secondarymenu', function() {
                $secondmenu.removeClass('show');
                elems.backdrop.hide();
            });
        }
    });

    // Get margin-top of #page.
    let $page = $(nodes[0]);
    let rec = $page[0].getBoundingClientRect();
    let margintop = rec.top + window.pageYOffset;
    // Set height of #content-wrapper to 100vh - margin-top.
    elems.contentwrapper.css('height', 'calc(100dvh - ' + margintop + 'px)');

    // Resize observer to get the margin-top of #page.
    // Throttle resize observer callback to avoid frequent changes.
    let resizeTimeout;
    let resizeObserver = new ResizeObserver(function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            let rec = $page[0].getBoundingClientRect();
            let margintop = rec.top + window.pageYOffset;
            // Set height of #content-wrapper to 100vh - margin-top.
            elems.contentwrapper.css('height', 'calc(100dvh - ' + margintop + 'px)');
            if (window.innerWidth < 1200) {
                elems.sidemenuwrapper.css({
                    'top': margintop + 'px',
                    'height': 'calc(100dvh - ' + margintop + 'px)',
                });
            }
        }, 100);
    });
    resizeObserver.observe(elems.page[0]);
    resizeObserver.observe(elems.header[0]);
    resizeObserver.observe(elems.contentwrapper[0]);

    // Settle the panel when the window is resized.
    $(window).on('resize', function() {
        if ($(window).width() < 1200) {
            collapseSideInfo(false);
        }
        elems.backdrop.hide();
    });

    // Toggle tooltip on sidebar if labels are hidden.
    if (formatoptions.showlabels == 0) {
        setTimeout(() => {
            try {
                elems.sidebarwrapper.find('.btn-icon').tooltip({
                    placement: 'right',
                });
            } catch (e) {
                // Do nothing.
            }
        }, 2000);
    }

    // Course video.
    let coursevideomodal, coursevideoplayer;
    $(document).on('click', '#sidemenu .video-wrapper, #header-v .video-wrapper', async function(e) {
        e.preventDefault();
        let url = $(this).attr('data-url');
        let type = $(this).attr('data-type');
        if (coursevideomodal) {
            coursevideomodal.show();
            return;
        }
        const closeLabel = await getString('close', 'format_mtubebs');
        coursevideomodal = await ModalFactory.create({
            body: `<div class="w-100 coursevideomodal-mtubebs position-relative">
                <button type="button"
                        class="coursevideo-modal-close btn btn-light btn-sm"
                        data-action="hide"
                        title="${closeLabel}"
                        aria-label="${closeLabel}">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
                <div id="video-info-wrapper" class="w-100 loader"></div>
            </div>`,
            large: true,
            show: true,
            removeOnClose: false,
            isVerticallyCentered: true,
        });
        let coursevideoroot = coursevideomodal.getRoot();
        coursevideoroot.find('.modal-body').addClass('p-0');
        coursevideoroot.find('.modal-dialog').addClass('modal-xl').removeClass('modal-lg');
        coursevideoroot.on(ModalEvents.shown, async function() {
            coursevideoroot.addClass('jelly-anim');
            setTimeout(() => {
                coursevideoroot.removeClass('jelly-anim');
            }, 500);

            coursevideoroot.find('.modal-body').addClass('p-0');
            await new Promise((resolve) => {
                require(['mod_interactivevideo/player/' + type], function(VP) {
                    resolve(VP);
                });
            }).then(async(VP) => {
                coursevideoplayer = new VP();
                let id = 'video-info-' + new Date().getTime();
                if (type == 'html5video') {
                    coursevideoroot.find('#video-info-wrapper')
                        .html(`<video id="${id}" class="w-100" controls></video>`);
                } else {
                    coursevideoroot.find('#video-info-wrapper')
                        .html('<div id="' + id + '" class="w-100"></div>');
                }
                return await coursevideoplayer.getInfo(url, id);
            });
        });
        coursevideoroot.on(ModalEvents.hidden, function() {
            if (coursevideoplayer) {
                try {
                    coursevideoplayer.pause();
                } catch (e) {
                    // Do nothing.
                }
            }
        });
    });

    $(document).on('click', '#sideinfo-toggle', async function(e) {
        e.preventDefault();
        if (window.innerWidth < 768) {
            const closeLabel = await getString('close', 'format_mtubebs');
            let $menu = elems.sidemenuwrapper.clone();
            $menu.removeClass('hide mtubebs-collapsed')
                .attr('style', '')
                .addClass('position-relative');
            $menu.find('#sideinfo-toggle').remove();
            $menu.find('#sidemenu').prepend(
                `<button type="button"
                        class="sidemenu-modal-close btn btn-icon icons-collapse-expand"
                        data-action="hide"
                        aria-label="${closeLabel}">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>`
            );
            let modal = await ModalFactory.create({
                body: $menu,
                removeOnClose: true,
            });
            let modalroot = modal.getRoot();
            modalroot.find('.modal-header').remove();
            modalroot.find('.modal-body').addClass('p-0');
            modalroot.on(ModalEvents.hidden, function() {
                animateModal(modalroot, 'out');
            });
            modal.show();
            return;
        }
        if (!elems.sidemenuwrapper.hasClass('hide')) {
            localStorage.setItem('sideinfo-' + M.cfg.courseId, 'false');
            collapseSideInfo(true, function() {
                elems.sidebarwrapper.find('#sideinfo-toggle').addClass('active');
                setTimeout(() => {
                    elems.sidebarwrapper.find('#sideinfo-toggle').removeClass('active');
                }, 1000);
            });
            elems.backdrop.hide();
        } else {
            // Scroll to the top.
            elems.mainwrapper.animate({scrollTop: 0}, 300);
            localStorage.setItem('sideinfo-' + M.cfg.courseId, 'true');
            expandSideInfo();
            elems.backdrop.show();
        }
    });

    $(document).on('section:changed', async function() {
        setTimeout(() => {
            activateTooltips(elems.headerwrapper);
        }, 300);
    });

    elems.backdrop.on('click', function(e) {
        e.preventDefault();
        $('#secondarymenu-side').removeClass('show');
        collapseSideInfo(false);
        $(this).hide();
    });

    $(document).on('click', '#refresh-section', function(e) {
        e.preventDefault();
        elems.pageprogress.show();
        window.MTubeBs.updatesection = false;
        renderSectionContent(window.MTubeBs.activesection, false);
    });

    $(document).on('click', '#previous-section, #next-section', function(e) {
        e.preventDefault();
        window.MTubeBs.updatesection = false;
        let target = $(this).attr('data-target');
        elems.header.find(`button[data-section="${target}"]`).first().trigger('click');
    });

    $(document).on('click', '#more-toggle', function(e) {
        e.preventDefault();
        $('#more-menu').fadeToggle(300);
        // Hide on click outside and not the more-toggle
        $(document).one('click.more', function(e) {
            if ($(e.target).closest('#more-toggle').length) {
                return;
            }
            $('#more-menu').fadeOut(300);
        });
    });

    $(document).on('click', '.modal [data-refresh]', function() {
        const target = $(this).attr('data-refresh');
        $(`#${target}`).trigger('click');
    });

    // Store collapsed sections in local storage.
    let collapsedstate = localStorage.getItem('mtubecollapsedsections-' + M.cfg.courseId) || '[]';
    collapsedstate = JSON.parse(collapsedstate);
    $(document).on('section:change', async function() {
        // Get the collapsed sections.
        elems.mainwrapper.find('[data-for="sectiontoggler"]').each(function() {
            let section = $(this);
            let href = section.attr('href');
            if (section.hasClass('collapsed')) {
                collapsedstate.push(href);
            } else {
                collapsedstate = collapsedstate.filter(x => x != href);
            }
        });
        collapsedstate = [...new Set(collapsedstate)];
        localStorage.setItem('mtubecollapsedsections-' + M.cfg.courseId, JSON.stringify(collapsedstate));
    });

    $(document).on('section:changed', async function() {
        elems.mainwrapper.find('[data-for="sectiontoggler"]').each(function() {
            let section = $(this);
            let href = section.attr('href');
            if (collapsedstate.includes(href)) {
                section.addClass('collapsed');
                $(href).removeClass('show');
            }
        });
    });

    // History event listener.
    window.addEventListener('popstate', async function(e) {
        // Hide any modals.
        const prev = e.state?.prev;
        if (!prev) {
            return;
        }
        elems.body.find('.modal.show [data-action="hide"]').trigger('click');

        const url = new URL(prev);
        const courseid = url.searchParams.get('id');
        const sectionid = url.searchParams.get('sectionid');
        if (courseid) {
            // Go to the section.
            const sections = window.MTubeBs.validsections;
            const sectioninfo = sectionid ? sections.find(x => x.id == sectionid) : sections[0];
            renderSectionContent({
                sectionid: sectionid ?? sectioninfo?.id,
                section: sectioninfo?.section || 0,
            });
        }
    });

    // Coming back from an activity may restore this page from the browser cache, with the old completion.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted && window.MTubeBs.activesection) {
            renderSectionContent(window.MTubeBs.activesection, true);
        }
    });
};

const delay = (s) => {
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve();
        }, s);
    });
};

const renderSectionNav = async(sectionid) => {
    let validsections = window.MTubeBs.validsections;
    let sectionmenu = await Templates.render('format_mtubebs/sectionmenu-dropdown', {
        sections: validsections,
        options: formatoptions,
    });

    let thissection = validsections.find(x => x.id == sectionid);
    if (!thissection) {
        thissection = {
            section: -1,
            id: -1,
        };
    }

    const $rendered = $(sectionmenu);
    const dropdownNavRoot = elems.header.find('#section-menu > .btn-group');
    if (dropdownNavRoot.length) {
        const $menu = dropdownNavRoot.find('ul.section-menu-dropdown');
        const $newMenu = $rendered.find('ul.section-menu-dropdown');
        if ($menu.length && $newMenu.length) {
            $menu.html($newMenu.html());
        }
    } else {
        elems.header.html(sectionmenu);
    }

    elems.header.find('[data-sectionid="' + thissection.id + '"]').addClass('active');
    elems.header.find('#section-menu-toggle-list')
        .text(formatoptions.sectionlabelplural)
        .removeClass('active');

    if (thissection.section != -1) {
        // Handle the #next-section and #previous-section buttons.
        let currentindex = validsections.findIndex(x => x.section == thissection.section);
        if (validsections[currentindex + 1]) {
            elems.header.find('#next-section').prop('disabled', false)
                .attr('data-target', validsections[currentindex + 1].section)
                .attr('title', '<small>' + await getString('nextto', 'format_mtubebs',
                    await getString(formatoptions.sectionlabel, 'format_mtubebs'))
                    + '</small><br>' + validsections[currentindex + 1].name);
        } else {
            elems.header.find('#next-section').prop('disabled', true)
                .removeAttr('title');
        }
        if (validsections[currentindex - 1]) {
            elems.header.find('#previous-section').prop('disabled', false)
                .attr('data-target', validsections[currentindex - 1].section)
                .attr('title', '<small>' + await getString('previousto', 'format_mtubebs',
                    await getString(formatoptions.sectionlabel, 'format_mtubebs'))
                    + '</small><br>' + validsections[currentindex - 1].name);
        } else {
            elems.header.find('#previous-section').prop('disabled', true)
                .removeAttr('title');
        }
    }
};

/**
 * Show a section.
 *
 * @param {Object} sectioninfo
 * @param {Number|String} [sectioninfo.sectionid] Section id.
 * @param {Number|String} [sectioninfo.section] Section number.
 * @param {Number|String} [sectioninfo.cmid] Show the section of this activity instead.
 * @param {Boolean} [forceupdate] Refresh in place, without animation or a new address.
 * @return {Promise<Boolean>} Whether there was something to show.
 */
export const renderSectionContent = async(sectioninfo, forceupdate = false) => {
    let {section, sectionid} = sectioninfo;
    let courseid = M.cfg.courseId;
    if (forceupdate) {
        window.MTubeBs.updatesection = true;
    }
    sectioninfo.updatesection = window.MTubeBs.updatesection;
    dispatchEvent('section:change', sectioninfo);

    // Get section html.
    let content;
    try {
        content = await callWebservice([{
            methodname: 'format_mtubebs_get_section_html',
            args: {
                data: JSON.stringify({
                    courseid: courseid,
                    sectionid: sectionid,
                    sectionnum: section,
                    cmid: sectioninfo.cmid,
                }),
            },
        }]);
    } catch (e) {
        notifyError(e);
        window.MTubeBs.updatesection = false;
        elems.pageprogress.hide();
        return false;
    }

    content = JSON.parse(content.data || '{}');
    refreshAffectedSidemenu(content.section0state);

    if (sectioninfo.cmid) {
        if (!content.sectionid) {
            return false;
        }
        sectionid = content.sectionid;
        section = content.sectionnum;
        delete sectioninfo.cmid;
    }

    // Render header.
    let validsections = content.sections.filter(x => x.section != 0 && !x.isdelegated);
    window.MTubeBs.validsections = validsections;

    await renderSectionNav(sectionid);

    let thissection = validsections.find(x => x.id == sectionid);
    sectioninfo.sectionid = thissection?.id ?? -1;
    sectioninfo.section = thissection?.section ?? -1;
    window.MTubeBs.activesection = sectioninfo;
    // Replace the section content.
    elems.sectioncontent.attr('data-section', thissection?.section ?? -1);

    elems.sectioncontent.animate({opacity: 0}, window.MTubeBs.updatesection ? 0 : 300, function() {
        // Section header.
        $('.sectionheader-card').remove();
        elems.sectioncontent.before(content.sectionhead);

        $(this).html(content.html);
        $(this).animate({opacity: 1}, window.MTubeBs.updatesection ? 0 : 300);
        notifyFilter($(this));
        $(this).show();
        dispatchEvent('section:changed', window.MTubeBs.activesection);
    });

    // Update the url to include section=section.
    if (!window.MTubeBs.updatesection) {
        const url = new URL(M.cfg.wwwroot + '/course/view.php?id=' + courseid);
        url.searchParams.delete('section');
        url.searchParams.set('sectionid', section == -1 ? 0 : sectionid);
        window.MTubeBs.activesection.url = url.toString();
        window.MTubeBs.activesection.title
            = `${await getString(formatoptions.sectionlabel, 'format_mtubebs')}: ${validsections
                .find(x => x.section == section)?.name || section}`;
        Route.updateBrowser(window.MTubeBs.activesection.title, window.MTubeBs.activesection.url, false);
    }

    $('#iframecontent').removeClass('d-none');
    elems.pageprogress.hide();
    window.MTubeBs.updatesection = false;

    require(['core_courseformat/local/content', 'core/showmore', 'format_mtubebs/manual_completion_toggle'],
        function(component, showmore, toggle) {
            component.init('#page', {}, null);
            showmore.init();
            toggle.init();
        });

    return true;
};

const getSidemenu = async() => {
    let sidemenu;
    try {
        sidemenu = await callWebservice([{
            methodname: 'format_mtubebs_get_content_html',
            args: {
                data: JSON.stringify({
                    courseid: M.cfg.courseId,
                }),
            },
        }]);
    } catch (e) {
        notifyError(e);
        return;
    }

    if (!sidemenu?.data) {
        return;
    }

    // Replace the section 0 with the new one.
    const {html} = JSON.parse(sidemenu.data);
    elems.sidemenu.find('li#section-0').replaceWith(html);
    notifyFilter(elems.sidemenu);
};

/** Delay before refreshing sidemenu after navigation (ms). */
const SIDEMENU_REFRESH_DEFER_MS = 800;

/** What the user has access to and has completed in section 0, as last reported along with a section. */
let section0state = null;

/** Pending deferred sidemenu refresh timer. */
let sidemenuRefreshTimer = null;

/**
 * Refresh the sidemenu shortly, once for several requests in a row.
 */
const scheduleSidemenuRefresh = () => {
    if (sidemenuRefreshTimer !== null) {
        clearTimeout(sidemenuRefreshTimer);
    }

    sidemenuRefreshTimer = setTimeout(() => {
        sidemenuRefreshTimer = null;
        getSidemenu();
    }, SIDEMENU_REFRESH_DEFER_MS);
};

/**
 * Refresh the sidemenu when something in the main section affects it, such as a completion that unlocks an activity.
 * It is otherwise left alone, so that a video that plays in it goes on.
 *
 * @param {String} state The state of section 0 now.
 */
const refreshAffectedSidemenu = (state) => {
    if (section0state !== null && section0state !== state) {
        scheduleSidemenuRefresh();
    }
    section0state = state;
};

const DENY_ANIMATION_MS = 500;

/**
 * Subtle jelly zoom when a modal opens, closes, or rejects an outside click.
 *
 * @param {JQuery} root Modal root element.
 * @param {string} type Animation direction: in|out|deny.
 */
export const animateModal = (root, type) => {
    const $root = $(root);
    if (type === 'in') {
        if ($root.hasClass('jelly-anim')) {
            return;
        }
        $root.addClass('jelly-anim');
        setTimeout(() => {
            $root.removeClass('jelly-anim');
        }, DENY_ANIMATION_MS);
        $root.find('.modal-dialog').css('opacity', 1);
    } else if (type === 'out') {
        if ($root.hasClass('jelly-anim-out')) {
            return;
        }
        $root.addClass('jelly-anim-out');
        setTimeout(() => {
            $root.removeClass('jelly-anim-out');
        }, DENY_ANIMATION_MS);
        $root.find('.modal-dialog').css('opacity', 0);
    } else if (type === 'deny') {
        if ($root.hasClass('jelly-anim-deny')) {
            return;
        }
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
        $root.addClass('jelly-anim-deny mtubebs-modal-outside-deny');
        setTimeout(() => {
            $root.removeClass('jelly-anim-deny mtubebs-modal-outside-deny');
        }, DENY_ANIMATION_MS);
    }
};

let mtubebsModalEnhancementsBound = false;

/**
 * Apply MooTube Basic-wide modal behaviour: jelly show animation and no backdrop dismiss.
 */
const initModalEnhancements = () => {
    if (mtubebsModalEnhancementsBound || !document.body.classList.contains('format-mtubebs')) {
        return;
    }
    mtubebsModalEnhancementsBound = true;

    $(document).on(`${ModalEvents.outsideClick}.mtubebs`, '.modal', function(event) {
        event.preventDefault();
        animateModal($(this), 'deny');
    });

    $(document).on(`${ModalEvents.shown}.mtubebs`, '.modal', function() {
        animateModal($(this), 'in');
    });
};

const UI = {
    init,
    animateModal,
    disposeTooltip,
    scheduleSidemenuRefresh,
    elems,
    formatoptions,
    delay,
    renderSectionContent,
};

export default UI;
