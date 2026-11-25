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
 * Grade report modal.
 *
 * @module     format_mtubebs/features/grade
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {callWebservice} from 'format_mtubebs/ajax_retry';
import ModalFactory from 'core/modal';
import ModalEvents from 'core/modal_events';
import Pending from 'core/pending';
import {notifyError} from 'format_mtubebs/error_toast';
import {getString} from 'core/str';
import Templates from 'core/templates';
import {init as initCategoryToggle} from 'gradereport_user/gradecategorytoggle';
import {
    emptyModalHeader,
    bindModalFullscreen,
    renderFullscreenButtons,
    STORAGE_KEYS,
    wrapModalHeaderActions,
} from './modal_fullscreen';
import {findGradeReportContainer, printGradeReport} from './grade_report_print';
import {animateModal} from '../ui';
import {registerModalType, syncModalUrl, clearModalUrl} from 'format_mtubebs/modal_launcher';

const WRAPPER_CLASS = 'grade-wrapper';
const REFRESH_ACTION = 'refresh-grade-report';
const PRINT_ACTION = 'print-grade-report';

/**
 * Parse a grade report URL into course and user ids.
 *
 * @param {String} url Grade report URL
 * @return {{courseid: number, userid: number}|null}
 */
const parseGradeReportUrl = (url) => {
    if (!url) {
        return null;
    }

    try {
        const parsed = new URL(url, window.location.origin);
        const courseid = parseInt(parsed.searchParams.get('id'), 10);
        const userid = parseInt(parsed.searchParams.get('userid') || '0', 10);

        if (!courseid) {
            return null;
        }

        return {
            courseid,
            userid: Number.isNaN(userid) ? 0 : userid,
        };
    } catch (error) {
        return null;
    }
};

/**
 * Fetch grade report HTML from the server.
 *
 * @param {Number} courseid
 * @param {Number} userid
 * @return {Promise<Object>}
 */
const fetchGradeReport = async(courseid, userid) => {
    const pending = new Pending('format_mtubebs/grade:fetch');
    try {
        return await callWebservice([{
            methodname: 'format_mtubebs_get_user_grade_report',
            args: {
                courseid,
                userid,
            },
        }]);
    } finally {
        pending.resolve();
    }
};

/**
 * Apply grade report body classes to the content wrapper (not the modal root).
 *
 * @param {JQuery} content Content wrapper inside the grade modal
 * @param {String} bodyclasses Space-separated class names
 */
const applyBodyClasses = (content, bodyclasses) => {
    const existing = (content.data('mtubebsGradeBodyClasses') || '').split(/\s+/).filter(Boolean);
    existing.forEach((className) => {
        content.removeClass(className);
    });

    const classes = (bodyclasses || '').split(/\s+/).filter(Boolean);
    classes.forEach((className) => {
        content.addClass(className);
    });
    content.data('mtubebsGradeBodyClasses', classes.join(' '));
};

/**
 * Render grade report content into the modal wrapper.
 *
 * @param {JQuery} wrapper Content wrapper
 * @param {Object} response Ajax response
 */
const renderGradeReport = (wrapper, response) => {
    const contentClass = 'mtubebs-grade-report-content';
    wrapper.html(`<div class="${contentClass}"></div>`);
    const content = wrapper.find(`.${contentClass}`);
    content.html(response.html || '');
    applyBodyClasses(content, response.bodyclasses);

    // Only the core user report has collapsible categories; the custom grade table has none.
    if (response.reportcontainerid && content[0]?.querySelector('.user-grade')) {
        initCategoryToggle(response.reportcontainerid);
    }
};

/**
 * Open the grade report modal and load the report via Ajax.
 *
 * @param {String} url Grade report URL
 * @return {Promise<void>}
 */
const showGradeReport = async(url) => {
    const params = parseGradeReportUrl(url);
    if (!params) {
        return;
    }

    const loadingHtml = await Templates.render('format_mtubebs/backgroundloading', {});
    const title = await getString('grade', 'format_mtubebs');
    const refreshLabel = await getString('refresh', 'format_mtubebs');
    const printLabel = await getString('userreportprintgrade', 'format_mtubebs');
    const fullscreenButtons = await renderFullscreenButtons();

    const modal = await ModalFactory.create({
        body: `<div class="${WRAPPER_CLASS}">${loadingHtml}</div>`,
        title,
        large: true,
        show: false,
        removeOnClose: true,
        isVerticallyCentered: true,
        scrollable: false,
    });

    const root = modal.getRoot();
    root.addClass('reportmodal-mtubebs');
    emptyModalHeader(root)
        .append(`<div class="d-flex align-items-center w-100 gap-3">
            <div class="timeline-input-wrapper d-flex align-items-center flex-grow-1 min-width-0">
                <h5 class="mb-0 flex-grow-1">${title}</h5>
            </div>
            ${wrapModalHeaderActions(`<button class="btn-icon icons-collapse-expand btn p-0" type="button"
             data-action="${PRINT_ACTION}"
             aria-label="${printLabel}"
             title="${printLabel}"
             disabled>
             <i class="fa fa-print" aria-hidden="true"></i></button>
            <button class="btn-icon icons-collapse-expand btn p-0" type="button"
             data-action="${REFRESH_ACTION}"
             aria-label="${refreshLabel}"
             title="${refreshLabel}">
             <i class="fa fa-refresh" aria-hidden="true"></i></button>
            ${fullscreenButtons}
            <button class="btn-icon icons-collapse-expand btn p-0" type="button" data-action="hide"
             aria-label="${await getString('close', 'format_mtubebs')}"
             title="${await getString('close', 'format_mtubebs')}">
            <i class="fa fa-times" aria-hidden="true"></i></button>`)}
        </div>`);
    root.find('.modal-body').addClass('p-0');
    root.find('.modal-dialog').addClass('modal-xl').removeClass('modal-lg');
    bindModalFullscreen(root, STORAGE_KEYS.grade);

    const setPrintEnabled = (enabled) => {
        root.find(`[data-action="${PRINT_ACTION}"]`).prop('disabled', !enabled);
    };

    const loadContent = async() => {
        const wrapper = root.find(`.${WRAPPER_CLASS}`);
        wrapper.html(await Templates.render('format_mtubebs/backgroundloading', {}));
        setPrintEnabled(false);

        try {
            const response = await fetchGradeReport(params.courseid, params.userid);
            renderGradeReport(wrapper, response);
            setPrintEnabled(Boolean(findGradeReportContainer(root[0])));
        } catch (error) {
            wrapper.empty();
            notifyError(error);
        }
    };

    root.on(ModalEvents.shown, async function() {
        animateModal(root, 'in');
        syncModalUrl({modal: 'grade', userid: params.userid || 0});
        await loadContent();
    });

    root.on('click', `[data-action="${REFRESH_ACTION}"]`, async function(event) {
        event.preventDefault();
        event.stopPropagation();
        await loadContent();
    });

    root.on('click', `[data-action="${PRINT_ACTION}"]`, function(event) {
        event.preventDefault();
        event.stopPropagation();
        printGradeReport(findGradeReportContainer(root[0]), {title});
    });

    root.on(ModalEvents.hidden, function() {
        animateModal(root, 'out');
        clearModalUrl();
        modal.destroy();
    });

    modal.show();
};

/**
 * Toggle grade report.
 */
export const init = async() => {
    $(document).on('click', '#grade-toggle', async function(e) {
        e.preventDefault();
        await showGradeReport($(this).attr('data-url'));
    });

    registerModalType('grade', {
        isAvailable: () => Boolean(document.getElementById('grade-toggle')),
        launch: async(params, ctx) => {
            if (params.userid) {
                const url = `${M.cfg.wwwroot}/grade/report/user/index.php?id=${ctx.courseid}&userid=${params.userid}`;
                await showGradeReport(url);
                return true;
            }
            const toggle = document.getElementById('grade-toggle');
            if (!toggle) {
                return false;
            }
            toggle.click();
            return true;
        },
    });
};
