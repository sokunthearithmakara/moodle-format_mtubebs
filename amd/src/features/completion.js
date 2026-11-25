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
 * Activity completion criteria modal loaded via iframe.
 *
 * @module     format_mtubebs/features/completion
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import ModalFactory from 'core/modal';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';
import Templates from 'core/templates';
import {animateModal} from '../ui';
import {registerModalType, syncModalUrl, clearModalUrl} from 'format_mtubebs/modal_launcher';
import {emptyModalHeader} from './modal_fullscreen';

// Toggle the completion popup.
export const init = async() => {
    $(document).on('click', '#completion-toggle', async function(e) {
        e.preventDefault();
        const url = $(this).attr('data-url');
        const reportmodal = await ModalFactory.create({
            body: `<div class="completion-wrapper">${await Templates.render('format_mtubebs/backgroundloading')}
            </div>`,
            title: await getString('completion', 'format_mtubebs'),
            large: true,
            show: false,
            removeOnClose: true,
            isVerticallyCentered: true,
            scrollable: false,
        });

        let reportroot = reportmodal.getRoot();
        reportroot.addClass('reportmodal-mtubebs');
        emptyModalHeader(reportroot)
            .append(`<div class="d-flex align-items-center w-100">
                        <div class="timeline-input-wrapper d-flex align-items-center w-100">
                            <h5 class="mb-0 flex-grow-1">${await getString('completion', 'format_mtubebs')}</h5>
                        </div>
                        <button class="btn-icon icons-collapse-expand btn ms-2 p-0" type="button"
                         data-refresh="completion-toggle"
                         data-action="hide"
                         aria-label="${await getString('refresh', 'format_mtubebs')}">
                         <i class="fa fa-refresh"></i></button>
                        <button class="btn-icon icons-collapse-expand btn ms-2 p-0" type="button" data-action="hide">
                        <i class="fa fa-times"></i></button>
                    </div>`);
        reportroot.find('.modal-body').addClass('p-0');
        reportroot.find('.modal-dialog').addClass('modal-xl').removeClass('modal-lg');
        reportroot.on(ModalEvents.shown, async function() {
            animateModal(reportroot, 'in');
            syncModalUrl({modal: 'completion'});

            reportroot.find('.completion-wrapper').append(`<iframe src="${url}" frameborder="0"
                 allowfullscreen class="d-none"></iframe>`);

            const iframe = reportroot.find('iframe');
            iframe.off('load').on('load', async function() {
                const iframedoc = iframe[0].contentDocument;
                const iframebody = iframedoc.querySelector('body #region-main-box');
                reportroot.find('.completion-wrapper').html(iframebody);
                reportroot.find('.modal-body').removeClass('p-0');
            });
        });

        reportroot.on(ModalEvents.hidden, function() {
            animateModal(reportroot, 'out');
            clearModalUrl();
            reportmodal.destroy();
        });

        reportmodal.show();
    });

    registerModalType('completion', {
        isAvailable: () => Boolean(document.getElementById('completion-toggle')),
        launch: () => {
            const toggle = document.getElementById('completion-toggle');
            if (!toggle) {
                return false;
            }
            toggle.click();
            return true;
        },
    });
};