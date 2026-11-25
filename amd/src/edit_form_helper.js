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
 * Course format settings dynamic form modal and save handling.
 *
 * @module     format_mtubebs/edit_form_helper
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {getString} from 'core/str';
import DynamicForm from 'core_form/dynamicform';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import SortableList from 'core/sortable_list';
import {add as addToast} from 'core/toast';
import {animateModal, elems} from './ui';
import IconPicker from './features/iconpicker-modal';
import {emptyModalHeader} from 'format_mtubebs/features/modal_fullscreen';

/**
 * Sync checked course contacts from the editor UI into the hidden form field.
 *
 * @param {JQuery} $modal Modal root element.
 */
const syncCourseContacts = ($modal) => {
    const contacts = [];
    $modal.find('#coursecontacts-list .coursecontacts-row').each(function() {
        const $row = $(this);
        const $check = $row.find('.coursecontacts-check');
        if (!$check.is(':checked')) {
            return;
        }
        contacts.push({
            userid: parseInt($check.data('userid'), 10),
            description: $row.find('.coursecontacts-description').val().trim(),
        });
    });
    $modal.find('[name="coursecontacts"]').val(JSON.stringify(contacts));
};

/**
 * Initialise the sortable course contacts editor inside the format options modal.
 *
 * @param {JQuery} $modal Modal root element.
 */
const initCourseContactsEditor = ($modal) => {
    const listEl = $modal.find('#coursecontacts-list')[0];
    if (!listEl || listEl.dataset.sortableInitialized) {
        return;
    }
    listEl.dataset.sortableInitialized = '1';

    new SortableList(listEl, {
        moveHandlerSelector: '.drag-handle',
    });

    listEl.addEventListener(SortableList.EVENTS.DROP, () => {
        syncCourseContacts($modal);
    });

    $modal.on('change.coursecontacts', '.coursecontacts-check', function() {
        const $row = $(this).closest('.coursecontacts-row');
        $row.find('.coursecontacts-description').prop('disabled', !$(this).is(':checked'));
        syncCourseContacts($modal);
    });

    $modal.on('input.coursecontacts', '.coursecontacts-description', () => {
        syncCourseContacts($modal);
    });

    syncCourseContacts($modal);
};

export const init = async() => {
    // Load the icon picker CSS.
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = M.cfg.wwwroot + '/course/format/mtubebs/libraries/iconpicker/iconpicker-1.5.0.css';
    document.head.appendChild(link);

    $(document).on('click', 'button[name="formatoptions"]', async function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        let origin = $(this).attr('data-origin');
        let args;
        if (origin == 'coursepage') {
            args = $('#formatoptions').val() || '{}';
            elems.pageprogress.show();
        } else if (origin == 'settingspage') {
            args = $('[name="s_format_mtubebs_formatoptions"]').val() || '{}';
        } else {
            args = $('input[name="formatoptionstext"]').val() || '{}';
        }
        args = JSON.parse(args);
        args.id = M.cfg.courseId;
        args.contextid = M.cfg.courseContextId;
        args.origin = origin;
        let formatoptionstext = args.formatoptionstext;
        delete args.formatoptionstext;
        const formatlinks = (args) => {
            if (typeof args.links === 'object') {
                let links = [];
                args.links.forEach(function(l) {
                    let link = '';
                    link += l.label + '|';
                    link += l.icon + '|';
                    link += l.url;
                    links.push(link);
                });
                args.links = links.join('\n');
            }
            return args;
        };
        args = formatlinks(args);

        const modal = await Modal.create({
            body: `<form id="formatoptions-form"></form>`,
            large: true,
            show: false,
            removeOnClose: true,
            isVerticallyCentered: true,
            returnElement: $('body'),
            footer: `<button class="btn btn-primary" type="submit" data-action="save">${await getString('save')}</button>`,
        });

        let root = modal.getRoot();
        emptyModalHeader(root)
            .append(`<div class="d-flex align-items-center w-100">
                        <div class="d-flex align-items-center w-100">
                            <h5 class="mb-0 flex-grow-1">${await getString('formatoptions', 'format_mtubebs')}</h5>
                        </div>
                        <button class="btn-icon icons-collapse-expand btn ms-2 p-0 justify-content-center"
                         data-action="import" data-bs-toggle="tooltip" data-bs-placement="top"
                         title="${await getString('import')}">
                         <i class="fa fa-upload"></i></button>
                         <button class="btn-icon icons-collapse-expand btn ms-2 p-0 justify-content-center"
                          data-action="export" data-bs-toggle="tooltip" data-bs-placement="top"
                         title="${await getString('export', 'format_mtubebs')}">
                         <i class="fa fa-download"></i></button>
                        <button class="btn-icon icons-collapse-expand btn ms-2 p-0 justify-content-center"
                         data-action="hide" data-bs-toggle="tooltip" data-bs-placement="top"
                          title="${await getString('close', 'format_mtubebs')}">
                        <i class="fa fa-times"></i></button>
                    </div>`);

        // The core/modal module shows a spinner for ModalForm because it hands setBody a pending
        // promise. DynamicForm renders into a container we own, and its load() leaves that
        // container untouched until the web service returns - so on a form this size the
        // dialogue would otherwise open empty. updateForm() calls replaceNodeContents(),
        // which clears this placeholder when the real form arrives.
        const formcontainer = root.find('#formatoptions-form');
        formcontainer.html(`<div class="d-flex justify-content-center align-items-center py-5">
            <div class="spinner-border text-danger" role="status">
                <span class="visually-hidden">${await getString('loading', 'admin')}</span>
            </div>
        </div>`);

        const form = new DynamicForm(formcontainer[0], 'format_mtubebs\\form\\formatoptions_form');
        form.load(args);

        modal.show();

        form.addEventListener(form.events.FORM_SUBMITTED, async(e) => {
            e.stopImmediatePropagation();
            animateModal(root, 'out');
            modal.hide();

            // Remove announcement sessionstorage.
            sessionStorage.removeItem('hideannouncement-' + M.cfg.courseId);
            if (e.detail.origin == 'coursepage') {
                addToast(await getString('formatsettingssavedrefreshing', 'format_mtubebs'), {type: 'default'});
                // Refresh the page.
                setTimeout(function() {
                    window.location.reload();
                }, 500);
                return;
            }
            if (origin == 'settingspage') {
                $('[name="s_format_mtubebs_formatoptions"]').val(JSON.stringify(e.detail));
            } else {
                $('input[name="formatoptionstext"]').val(JSON.stringify(e.detail));
            }
            elems.pageprogress.hide();
        });

        // Apply custom fields after the form is detected.
        let interval;
        const intervalfunction = async() => {
            if ($('.modal.show form input').length > 0) {
                clearInterval(interval);
                $('.modal.show').addClass('course-format-options-form');
                const $modal = $('.modal.show.course-format-options-form');
                // Populate custom nodes.
                let $customnodegroup = $('#customnodegroup');
                let links = root.find('[name="links"]').val();
                if (links) {
                    links = links.split('\n');
                    // Clone the current node and add it after the current node.
                    links = links.filter(function(link) {
                        return link.trim() !== '';
                    });
                    let $current = $customnodegroup.find('.input-group').last();
                    for (let i = 0; i < links.length - 1; i++) {
                        $current.clone().appendTo($customnodegroup);
                    }
                    for (let i = 0; i < links.length; i++) {
                        let link = links[i].split('|');
                        let $inputgroup = $customnodegroup.find('.input-group').eq(i);
                        $inputgroup.find('input[name="node-name"]').val(link[0]);
                        $inputgroup.find('input[name="node-icon"]').val(link[1] || 'far fa-circle').trigger('change');
                        const textarea = document.createElement('textarea');
                        textarea.innerHTML = link[2];
                        const url = textarea.value;
                        $inputgroup.find('input[name="node-url"]').val(url);
                    }
                }

                initCourseContactsEditor($modal);
            }
        };

        let startforminterval = async() => {
            interval = setInterval(intervalfunction, 100);
        };

        root.on(ModalEvents.shown, async(e) => {
            elems.pageprogress.hide();
            e.stopImmediatePropagation();
            animateModal(root, 'in');
            startforminterval();
            const updateLinks = async() => {
                let links = [];
                $(document).find('#customnodegroup .input-group').each(function() {
                    let $this = $(this);

                    if ($this.find('input').val().trim() === '') {
                        return;
                    }
                    let link = '';
                    link += $this.find('input[name="node-name"]').val().trim() + '|';
                    link += $this.find('input[name="node-icon"]').val().trim() + '|';
                    link += $this.find('input[name="node-url"]').val().trim() || 'far fa-circle';
                    links.push(link);
                });
                let modal = $('#customnodegroup').closest('.modal');
                modal.find('[name="links"]').val(links.join('\n'));
            };

            $(document).off('click', '.add-node');
            $(document).on('click', '.add-node', async function() {
                // Clone the current node and add it after the current node.
                let $current = $(this).closest('.input-group');
                let $clone = $current.clone();
                $clone.find('input').val('');
                $clone.insertAfter($current);
                $clone.find('[name="node-icon"]').trigger('change');
            });

            $(document).off('click', '.remove-node');
            $(document).on('click', '.remove-node', async function() {
                // Remove the current node.
                let $current = $(this).closest('.input-group');
                // If this is the last node, do nothing.
                if ($current.is(':last-child') && $current.prev().length === 0) {
                    // Clear the input.
                    $current.find('input').val('');
                    return;
                }
                $current.remove();
                updateLinks();
            });

            $(document).off('change', '#customnodegroup input[name="node-icon"]');
            $(document).on('change', '#customnodegroup input[name="node-icon"]', async function() {
                $(this).closest('.input-group').find('.input-group-text i').removeClass();
                let iconclass = $(this).val().trim();
                if (iconclass == '') {
                    iconclass = 'far fa-circle';
                }
                $(this).closest('.input-group').find('.input-group-text i').addClass(iconclass + ' fa-fw');
                updateLinks();
            });

            $(document).off('input', '#customnodegroup input');
            $(document).on('input', '#customnodegroup input', async function() {
                updateLinks();
            });
        });

        root.on(ModalEvents.hidden, function() {
            elems.pageprogress.hide();
            // Remove tooltip.
            $('.tooltip').remove();
            root.off('.coursecontacts');
            modal.destroy();
        });

        // Submit the form.
        root.find('[data-action="save"]').on('click', function(e) {
            elems.pageprogress.show();
            e.preventDefault();
            e.stopImmediatePropagation();
            syncCourseContacts(root);
            const event = form.trigger(form.events.SUBMIT_BUTTON_PRESSED);
            if (!event.defaultPrevented) {
                root.hide();
                form.submitFormAjax();
            }
        });

        form.addEventListener(form.events.SERVER_VALIDATION_ERROR, (e) => {
            elems.pageprogress.hide();
            e.stopImmediatePropagation();
            intervalfunction(); // Restore the interval.
            root.show();
            animateModal(root, 'in');

        });

        form.addEventListener(form.events.CLIENT_VALIDATION_ERROR, (e) => {
            elems.pageprogress.hide();
            e.stopImmediatePropagation();
            root.show();
            animateModal(root, 'in');
        });

        // Add import/export button.
        root.find('[data-action="export"]').on('click', function() {
            // Create a new json file for formatoptionstext.
            const json = formatoptionstext;
            const filename = 'Course format options export.json';
            const blob = new Blob([json], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            URL.revokeObjectURL(url);
        });

        root.find('[data-action="import"]').on('click', function() {
            const $input = $('<input type="file" accept=".json">');
            $input.on('change', function() {
                const file = $input[0].files[0];
                const reader = new FileReader();
                reader.onload = async function() {
                    const json = reader.result;
                    try {
                        const data = JSON.parse(json);
                        data.id = args.id;
                        data.contextid = args.contextid;
                        data.origin = args.origin;
                        data.import = 1;
                        await form.load(data);
                        intervalfunction();
                    } catch (e) {
                        addToast(await getString('errorimporting', 'format_mtubebs'), {
                            type: 'danger',
                        });
                    }
                };
                reader.readAsText(file);
            });
            $input.trigger('click');
        });
    });

    await IconPicker.Init({
        jsonUrl: M.cfg.wwwroot + "/course/format/mtubebs/libraries/iconpicker/iconpicker-7.json"
    });

    IconPicker.Run('.iconpicker', function(e, button) {
        $(button).find('i')
            .attr('class', 'fa-fw')
            .addClass(e);
        $(button).closest('.input-group').find('input#node-icon').val(e);
        $(button).closest('.input-group').find('input#node-icon').trigger('change');
    });
};
