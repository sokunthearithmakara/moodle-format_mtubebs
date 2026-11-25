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
 * Shared toast-based error reporting for MooTube Basic AMD modules.
 *
 * @module     format_mtubebs/error_toast
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

/** @type {string} Webservice error code that should use a warning toast. */
const WARNING_ERROR_CODE = 'invalidparameter';

/**
 * Show a toast for AJAX and runtime errors instead of the debug exception modal.
 *
 * @param {Object|Error|string} error Rejection object, Error, or message string.
 * @returns {Promise<void>}
 */
export const notifyError = async(error) => {
    if (typeof error === 'string') {
        await addToast(error, {type: 'danger'});
        return;
    }

    if (error?.errorcode === 'servicerequireslogin') {
        return;
    }

    // JQuery AJAX transport failures (e.g. POST too large) reject with jqXHR, not a Moodle exception.
    if (error && typeof error === 'object' && typeof error.status === 'number') {
        const responseText = (error.responseText || '').trim();
        if (responseText) {
            try {
                const parsed = JSON.parse(responseText);
                const first = Array.isArray(parsed) ? parsed[0] : parsed;
                if (first?.exception?.message) {
                    await addToast(first.exception.message, {type: 'danger'});
                    return;
                }
            } catch {
                // Not JSON — fall through.
            }
        }
        if (error.status === 413) {
            await addToast(await getString('courseimageuploaderror', 'format_mtubebs'), {type: 'danger'});
            return;
        }
    }

    const message = (error?.message || '').trim();
    if (message) {
        const unresolved = message.match(/^([a-z]\w*)\/(\w+)$/i);
        if (unresolved) {
            const [, component, errorcode] = unresolved;
            for (const langcomponent of [component, 'error', 'core']) {
                try {
                    await addToast(await getString(errorcode, langcomponent), {type: 'danger'});
                    return;
                } catch {
                    // Try the next language component.
                }
            }
        }

        const type = error?.errorcode === WARNING_ERROR_CODE ? 'warning' : 'danger';
        await addToast(message, {type});
        return;
    }

    const errorcode = error?.errorcode;
    if (errorcode) {
        for (const langcomponent of ['error', error?.module, 'core']) {
            if (!langcomponent) {
                continue;
            }
            try {
                await addToast(await getString(errorcode, langcomponent), {type: 'danger'});
                return;
            } catch {
                // Try the next language component.
            }
        }
    }

    await addToast(await getString('error'), {type: 'danger'});
};
