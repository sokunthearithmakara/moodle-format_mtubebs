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
 * Webservice helper with retry for transient server errors.
 *
 * @module     format_mtubebs/ajax_retry
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/** @type {string} Moodle error code that is worth retrying once. */
const RETRYABLE_CODE = 'dbconnectionfailed';

/** @type {number} Pause before the retry (ms). */
const RETRY_DELAY_MS = 600;

/**
 * Call a Moodle webservice, retrying once on transient failures.
 *
 * @param {Object[]} requests Argument to core/ajax.call.
 * @return {Promise<*>} First webservice response object.
 */
export const callWebservice = async(requests) => {
    try {
        return await Ajax.call(requests)[0];
    } catch (error) {
        if (error?.errorcode !== RETRYABLE_CODE) {
            throw error;
        }
        await new Promise((resolve) => {
            setTimeout(resolve, RETRY_DELAY_MS);
        });
        return Ajax.call(requests)[0];
    }
};
