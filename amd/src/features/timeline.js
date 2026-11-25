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
 * Course timeline modal showing filtered calendar events.
 *
 * @module     format_mtubebs/features/timeline
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import ModalFactory from 'core/modal';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Ajax from 'core/ajax';
import {getString} from 'core/str';
import {animateModal} from '../ui';
import {notifyError} from 'format_mtubebs/error_toast';
import {registerModalType, syncModalUrl, clearModalUrl} from 'format_mtubebs/modal_launcher';
import {emptyModalHeader} from './modal_fullscreen';

/** @type {Object|null} Reused timeline modal instance. */
let timelinemodal = null;

/** @type {jQuery|null} Reused timeline modal root. */
let timelineroot = null;

/** @type {Object|null} Cached UI strings. */
let timelineStrings = null;

/** @type {Object<number, Object>} Cached calendar events keyed by course id. */
const eventsCache = {};

/**
 * Load timeline strings once.
 *
 * @return {Promise<Object>}
 */
const loadTimelineStrings = async() => {
    if (timelineStrings) {
        return timelineStrings;
    }
    const strs = ['all', 'upcoming', 'overdue', 'deadline', 'refresh', 'youhavenoevents'];
    const stringValues = await Promise.all(strs.map(args => getString(args, 'format_mtubebs')));
    timelineStrings = {};
    strs.forEach((name, i) => {
        timelineStrings[name] = stringValues[i];
    });
    return timelineStrings;
};

/**
 * Fetch calendar events for a course, using the in-memory cache unless forced.
 *
 * @param {number} courseid Course id.
 * @param {boolean} [force=false] Skip cache and refetch.
 * @return {Promise<Object>}
 */
const fetchTimelineEvents = async(courseid, force = false) => {
    if (!force && eventsCache[courseid]) {
        return eventsCache[courseid];
    }
    const data = await Ajax.call([{
        methodname: 'core_calendar_get_action_events_by_course',
        args: {
            courseid,
            timesortfrom: 0,
            limitnum: 50,
        },
    }])[0];
    eventsCache[courseid] = data;
    return data;
};

/**
 * Render timeline event lists into the modal body.
 *
 * @param {jQuery} root Modal root.
 * @param {Object} strings UI strings.
 * @param {Object} data Calendar events response.
 * @return {Promise<void>}
 */
const renderTimelineContent = async(root, strings, data) => {
    const wrapper = root.find('.timeline-wrapper');
    if (!data.events.length) {
        wrapper.html(`<div class="d-flex justify-content-center align-items-center
                         h-100 w-100 flex-column">
                         <i class="fa fa-calendar-alt fa-5x my-3"></i>
                        ${strings.youhavenoevents}
                    </div>`);
        return;
    }

    let overduedata = [];
    let duedata = [];
    data.events = data.events.map(x => {
        x.duedate = new Date(x.timesort * 1000).toISOString().split('T')[0];
        return x;
    });
    let overdueevents = data.events.filter(x => x.overdue === true);
    let overduedates = overdueevents.map(x => x.duedate);
    overduedates = [...new Set(overduedates)];
    overduedates.forEach(function(duedate) {
        let events = overdueevents.filter(x => x.duedate === duedate);
        overduedata.push({
            events: events,
            dayTimestamp: new Date(duedate).getTime() / 1000,
        });
    });

    let dueevents = data.events.filter(x => x.overdue === false);
    let duedates = dueevents.map(x => x.duedate);
    duedates = [...new Set(duedates)];
    duedates.forEach(function(duedate) {
        let events = dueevents.filter(x => x.duedate === duedate);
        duedata.push({
            events: events,
            dayTimestamp: new Date(duedate).getTime() / 1000,
        });
    });

    let $items = '';
    let renderers = [];
    if (overduedata.length > 0) {
        renderers.push({
            eventsbyday: overduedata,
            overdue: true,
        });
    }
    if (duedata.length > 0) {
        renderers.push({
            eventsbyday: duedata,
            overdue: false,
        });
    }
    let renderersValues = await Promise.all(renderers.map(
        args => Templates.render('format_mtubebs/event-list-content', args)));
    $items += renderersValues[0] || '';
    $items += renderersValues[1] || '';

    wrapper.html($items);
    const timelinestate = localStorage.getItem('timelinestate-' + M.cfg.courseId) || 'all';
    $('#timeline-dropdown .dropdown-item[data-type="' + timelinestate + '"]').trigger('click');
};

/**
 * Create the timeline modal shell once.
 *
 * @param {Object} strings UI strings.
 * @return {Promise<void>}
 */
const ensureTimelineModal = async(strings) => {
    if (timelinemodal) {
        return;
    }

    timelinemodal = await ModalFactory.create({
        body: await Templates.render('format_mtubebs/backgroundloading'),
        show: false,
        removeOnClose: false,
        isVerticallyCentered: true,
    });

    timelineroot = timelinemodal.getRoot();
    timelineroot.attr('id', 'timeline-wrapper');
    timelineroot.find('.modal-body').addClass('timeline-wrapper');
    emptyModalHeader(timelineroot)
        .append(`<div class="d-flex align-items-center w-100">
                        <div class="timeline-input-wrapper d-flex align-items-center w-100">
                            <h5 class="mb-0 flex-grow-1">${strings.deadline}</h5>
                            <div class="dropdown" id="timeline-dropdown">
                                <button class="btn-icon icons-collapse-expand btn ms-2" type="button"
                                 data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-filter"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item px-2" href="#" data-type="all">
                                ${strings.all}</a></li>
                                <li><a class="dropdown-item px-2 active" href="#" data-type="upcoming">
                                ${strings.upcoming}</a></li>
                                <li><a class="dropdown-item px-2" href="#" data-type="overdue">
                                ${strings.overdue}</a></li>
                                </div>
                            </div>
                        </div>
                        <button class="btn-icon icons-collapse-expand btn ms-2 p-0"
                        type="button" data-refresh="timeline-toggle"
                         data-action="hide"
                         aria-label="${strings.refresh}">
                         <i class="fa fa-refresh"></i></button>
                        <button class="btn-icon icons-collapse-expand btn ms-2 p-0" type="button" data-action="hide">
                        <i class="fa fa-times"></i></button>
                    </div>`);

    timelineroot.on(ModalEvents.shown, function() {
        animateModal(timelineroot, 'in');
        syncModalUrl({modal: 'timeline'});
    });

    timelineroot.on(ModalEvents.hidden, function() {
        animateModal(timelineroot, 'out');
        clearModalUrl();
    });
};

// //////////////////////////////////////////////////////////////////////////////////////////////
// Timeline.
// //////////////////////////////////////////////////////////////////////////////////////////////
const init = async() => {
    $(document).on('click', '#timeline-wrapper [data-refresh="timeline-toggle"]', function() {
        delete eventsCache[M.cfg.courseId];
        $('#timeline-toggle').data('forceRefresh', true);
    });

    $(document).on('click', '#timeline-toggle', async function(e) {
        e.preventDefault();
        const forceRefresh = Boolean($(this).data('forceRefresh'));
        $(this).removeData('forceRefresh');

        const strings = await loadTimelineStrings();
        await ensureTimelineModal(strings);

        if (timelineroot.hasClass('show') && !forceRefresh) {
            return;
        }

        timelinemodal.show();

        if (forceRefresh) {
            timelineroot.find('.timeline-wrapper').html(await Templates.render('format_mtubebs/backgroundloading'));
        }

        try {
            const data = await fetchTimelineEvents(M.cfg.courseId, forceRefresh);
            await renderTimelineContent(timelineroot, strings, data);
        } catch (error) {
            // Core's service breaks for a user who is not enrolled, who has no events to show.
            await renderTimelineContent(timelineroot, strings, {events: []});
            if (error?.errorcode !== 'invalidresponse') {
                notifyError(error);
            }
        }
    });

    $(document).on('click', '#timeline-dropdown .dropdown-item', async function(e) {
        e.preventDefault();
        $('#timeline-dropdown .dropdown-item').removeClass('active');
        $(this).addClass('active');
        localStorage.setItem('timelinestate-' + M.cfg.courseId, $(this).attr('data-type'));
        let type = $(this).attr('data-type');
        if (type === 'all') {
            $('[data-region="event-list-wrapper"]').removeClass('d-none');
        } else if (type === 'upcoming') {
            $('[data-region="event-list-wrapper"]').addClass('d-none');
            $('[data-region="event-list-wrapper"][data-overdue="false"]').removeClass('d-none');
        } else if (type === 'overdue') {
            $('[data-region="event-list-wrapper"]').addClass('d-none');
            $('[data-region="event-list-wrapper"][data-overdue="true"]').removeClass('d-none');
        }
    });

    registerModalType('timeline', {
        isAvailable: () => Boolean(document.getElementById('timeline-toggle')),
        launch: () => {
            const toggle = document.getElementById('timeline-toggle');
            if (!toggle) {
                return false;
            }
            toggle.click();
            return true;
        },
    });
};

export default {
    init,
};
