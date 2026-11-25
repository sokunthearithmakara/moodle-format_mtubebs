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
 * Registry-based modal launcher driven by course view URL parameters.
 *
 * Reads `modal` and `userid` from the URL and opens the matching
 * MooTube Basic modal. Feature modules register their modal type during init, so new
 * features can integrate without touching the bootstrap.
 *
 * @module     format_mtubebs/modal_launcher
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import {notifyError} from 'format_mtubebs/error_toast';

/** @type {Map<string, {launch: Function, isAvailable: (Function|null)}>} */
const registry = new Map();

/** @type {string[]} Launch-only params stripped from the URL after dispatch. */
const LAUNCH_PARAMS = ['modal', 'userid'];

/**
 * Register a modal type that can be opened from a URL.
 *
 * @param {string} type Modal type key (matches the `modal` URL value).
 * @param {Object} definition Handler definition.
 * @param {function(Object, Object): (boolean|Promise<boolean>)} definition.launch
 *        Opens the modal. Receives normalized params and the launch context.
 *        Should return false (or throw) when it could not open.
 * @param {function(Object): boolean} [definition.isAvailable]
 *        Optional gate. When omitted, the type is considered available once registered.
 */
export const registerModalType = (type, definition) => {
    if (!type || typeof definition?.launch !== 'function') {
        return;
    }
    registry.set(type, {
        launch: definition.launch,
        isAvailable: typeof definition.isAvailable === 'function' ? definition.isAvailable : null,
    });
};

/**
 * Parse launch parameters from a URLSearchParams (or query string).
 *
 * @param {URLSearchParams|string} [search] Defaults to the current location.
 * @return {{modal: string, userid: number}|null}
 */
export const parseLaunchParams = (search = window.location.search) => {
    const params = search instanceof URLSearchParams ? search : new URLSearchParams(search);

    const modal = (params.get('modal') || '').trim();

    if (!modal) {
        return null;
    }

    const userid = parseInt(params.get('userid') || '0', 10);

    return {
        modal,
        userid: Number.isNaN(userid) ? 0 : userid,
    };
};

/**
 * Strip launch-only parameters from a URL.
 *
 * @param {URL} url URL to mutate.
 */
const stripLaunchParams = (url) => {
    LAUNCH_PARAMS.forEach((name) => url.searchParams.delete(name));
};

/**
 * Remove launch params from the address bar without reloading.
 */
const cleanUrl = () => {
    const url = new URL(window.location.href);
    stripLaunchParams(url);
    window.history.replaceState(window.history.state, '', url);
};

/**
 * Reflect an open modal in the address bar so the URL is shareable.
 *
 * Uses replaceState (no new history entry). Pass only the params relevant to the
 * modal; omitted launch params are removed.
 *
 * @param {Object} params Launch params to write.
 * @param {String} [params.modal] Modal type key.
 * @param {Number|String} [params.userid] Target user id.
 */
export const syncModalUrl = ({modal = '', userid = 0} = {}) => {
    const url = new URL(window.location.href);
    stripLaunchParams(url);
    if (modal) {
        url.searchParams.set('modal', modal);
    }
    if (userid) {
        url.searchParams.set('userid', userid);
    }
    window.history.replaceState(window.history.state, '', url);
};

/**
 * Remove modal launch params from the address bar (e.g. when a modal closes).
 */
export const clearModalUrl = () => {
    cleanUrl();
};

/**
 * Read launch intent from the URL and open the matching modal.
 *
 * On a successful launch the params are left in place so the opened modal can keep
 * the URL shareable; on failure they are stripped to avoid reopening on refresh/back.
 *
 * @param {Object} ctx Launch context ({courseid, canedit, formatoptions}).
 * @param {Object} [ctx.params] Pre-parsed launch params. Pass this when the address
 *        bar may be rewritten before dispatch (e.g. after a section render). When
 *        omitted, the current URL is parsed.
 * @return {Promise<boolean>} True when a modal was opened.
 */
export const processLaunchFromUrl = async(ctx = {}) => {
    const params = ctx.params !== undefined ? ctx.params : parseLaunchParams();
    if (!params) {
        return false;
    }

    const handler = registry.get(params.modal);

    try {
        if (!handler) {
            cleanUrl();
            await notifyError(await getString('modallaunchunknown', 'format_mtubebs', params.modal));
            return false;
        }

        if (handler.isAvailable && !handler.isAvailable(ctx)) {
            cleanUrl();
            await notifyError(await getString('modallaunchunavailable', 'format_mtubebs'));
            return false;
        }

        const opened = await handler.launch(params, ctx);
        if (opened === false) {
            // Handler declined to open; do not leave stale params behind.
            cleanUrl();
            return false;
        }
        // Leave launch params in place: the opened modal keeps the URL in sync so it stays shareable.
        return true;
    } catch (error) {
        cleanUrl();
        await notifyError(error);
        return false;
    }
};

export default {
    registerModalType,
    parseLaunchParams,
    processLaunchFromUrl,
    syncModalUrl,
    clearModalUrl,
};
