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
 * Fullscreen toggle for MooTube Basic modals with persisted user preference.
 *
 * @module     format_mtubebs/features/modal_fullscreen
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import $ from 'jquery';
import {disposeTooltip} from '../ui';

// Shared with MooTube on purpose, so a user's choice holds in both formats.
const STORAGE_PREFIX = 'format_mtube.modal.fullscreen.';

/** Applied to modals that expose a fullscreen toggle (used for responsive layout CSS). */
const MTUBEBS_MODAL_FULLSCREEN_CLASS = 'mtubebs-modal-fullscreen-capable';

/** @type {Readonly<Record<string, string>>} localStorage keys per modal type. */
export const STORAGE_KEYS = Object.freeze({
    grade: 'grade',
});

/**
 * @param {string} storageKey
 * @return {boolean}
 */
const readStored = (storageKey) => {
    if (!storageKey) {
        return false;
    }
    try {
        return window.localStorage.getItem(STORAGE_PREFIX + storageKey) === '1';
    } catch (e) {
        return false;
    }
};

/**
 * @param {string} storageKey
 * @param {boolean} enabled
 */
const writeStored = (storageKey, enabled) => {
    if (!storageKey) {
        return;
    }
    try {
        window.localStorage.setItem(STORAGE_PREFIX + storageKey, enabled ? '1' : '0');
    } catch (e) {
        // Ignore quota / private browsing.
    }
};

/**
 * Fullscreen toggle button markup (enter + exit).
 *
 * @param {string} expandLabel Expand control label.
 * @param {string} narrowLabel Exit fullscreen control label.
 * @return {string}
 */
const getFullscreenButtonsHtml = (expandLabel, narrowLabel) => `<button type="button"
        class="btn-icon icons-collapse-expand btn p-0 enterfullscreen"
        aria-label="${expandLabel}"
        title="${expandLabel}">
    <i class="fa fa-expand" aria-hidden="true"></i>
</button>
<button type="button"
        class="btn-icon icons-collapse-expand btn p-0 exitfullscreen"
        aria-label="${narrowLabel}"
        title="${narrowLabel}">
    <i class="fa fa-compress" aria-hidden="true"></i>
</button>`;

/**
 * Header action buttons wrapper used by report-style modals.
 *
 * @param {string} actionsHtml Button markup.
 * @return {string}
 */
export const wrapModalHeaderActions = (actionsHtml) =>
    `<div class="d-flex align-items-center flex-shrink-0 gap-2 mtubebs-modal-header-actions">${actionsHtml}</div>`;

/**
 * Load lang strings and return fullscreen toggle button markup.
 *
 * @return {Promise<string>}
 */
export const renderFullscreenButtons = async() => {
    const [expandLabel, narrowLabel] = await Promise.all([
        getString('expand', 'format_mtubebs'),
        getString('narrow', 'format_mtubebs'),
    ]);
    return getFullscreenButtonsHtml(expandLabel, narrowLabel);
};

/**
 * Apply or clear fullscreen on a modal root.
 *
 * @param {JQuery} root Modal root.
 * @param {boolean} enabled Whether fullscreen is active.
 * @param {string} [storageKey] Persist preference when provided.
 */
const setModalFullscreen = (root, enabled, storageKey) => {
    root.find('.enterfullscreen, .exitfullscreen').each(function() {
        disposeTooltip($(this));
    });
    root.toggleClass('fullscreen', enabled);
    if (storageKey) {
        writeStored(storageKey, enabled);
    }
};

/**
 * Restore persisted fullscreen state on a modal root.
 *
 * @param {JQuery} root Modal root.
 * @param {string} storageKey localStorage key.
 */
const restoreModalFullscreen = (root, storageKey) => {
    if (readStored(storageKey)) {
        root.addClass('fullscreen');
    }
};

/**
 * Wire fullscreen toggle buttons and restore saved state.
 *
 * @param {JQuery} root Modal root.
 * @param {string} storageKey localStorage key.
 */
export const bindModalFullscreen = (root, storageKey) => {
    root.addClass(MTUBEBS_MODAL_FULLSCREEN_CLASS);
    restoreModalFullscreen(root, storageKey);

    root.off('click.mtubebsModalFullscreen');
    root.on('click.mtubebsModalFullscreen', '.enterfullscreen', (event) => {
        event.preventDefault();
        event.stopPropagation();
        setModalFullscreen(root, true, storageKey);
    });
    root.on('click.mtubebsModalFullscreen', '.exitfullscreen', (event) => {
        event.preventDefault();
        event.stopPropagation();
        setModalFullscreen(root, false, storageKey);
    });
};

/**
 * Empty a modal header without destroying the node that names the dialog.
 *
 * core/modal points the dialog's aria-labelledby at the <h5> inside .modal-header
 * (lib/templates/modal.mustache), so emptying that header outright leaves the
 * reference dangling and the dialog announces with no accessible name. Keep the
 * title node and hide it visually instead.
 *
 * @param {JQuery} root Modal root, as returned by modal.getRoot().
 * @return {JQuery} The emptied header, ready for custom content.
 */
export const emptyModalHeader = (root) => {
    const header = root.find('.modal-header');
    const dialog = root.is('[aria-labelledby]') ? root : root.find('[aria-labelledby]').first();
    const titleid = dialog.attr('aria-labelledby');
    const title = titleid ? header.find('[id="' + titleid + '"]') : $();

    header.empty();
    if (title.length) {
        header.append(title.addClass('visually-hidden'));
    }

    return header;
};
