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
 * Provides the functionality for toggling the manual completion state of a course module through
 * the manual completion button.
 *
 * @module      format_mtubebs/manual_completion_toggle
 * @copyright   2021 Jun Pataleta <jun@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import {notifyError} from 'format_mtubebs/error_toast';
import {toggleManualCompletion} from 'core_course/repository';
import * as CourseEvents from 'core_course/events';
import Pending from 'core/pending';

/**
 * Selectors in the manual completion template.
 *
 * @type {{MANUAL_TOGGLE: string}}
 */
const SELECTORS = {
    MANUAL_TOGGLE: 'button[data-action=toggle-manual-completion-mtubebs]',
};

/**
 * Value of data-bs-toggletype of a button that marks the activity done, in the completion_manual template.
 *
 * @type {string}
 */
const TOGGLE_MARK_DONE = 'manual:mark-done';

/**
 * Whether the event listener has already been registered for this module.
 *
 * @type {boolean}
 */
let registered = false;

/**
 * Registers the click event listener for the manual completion toggle button.
 */
export const init = () => {
    if (registered) {
        return;
    }
    document.addEventListener('click', (e) => {
        const toggleButton = e.target.closest(SELECTORS.MANUAL_TOGGLE);
        if (toggleButton) {
            e.preventDefault();
            toggleManualCompletionState(toggleButton).catch(notifyError);
        }
    });
    registered = true;
};

/**
 * Toggles the manual completion state of the module for the given user.
 *
 * @param {HTMLElement} toggleButton
 * @returns {Promise<void>}
 */
const toggleManualCompletionState = async(toggleButton) => {
    const pendingPromise = new Pending('core_course:toggleManualCompletionState');
    // Make a copy of the original content of the button.
    const originalInnerHtml = toggleButton.innerHTML;

    // Disable the button to prevent double clicks.
    toggleButton.setAttribute('disabled', 'disabled');

    // Get button data.
    const toggleType = toggleButton.getAttribute('data-bs-toggletype');
    const cmid = toggleButton.getAttribute('data-cmid');
    const activityname = toggleButton.getAttribute('data-activityname');
    // Get the target completion state.
    const completed = toggleType === TOGGLE_MARK_DONE;

    try {
        // Call the webservice to update the manual completion status.
        await toggleManualCompletion(cmid, completed);

        // All good so far. Refresh the manual completion button to reflect its new state by re-rendering the template.
        const templateContext = {
            cmid: cmid,
            activityname: activityname,
            overallcomplete: completed,
            overallincomplete: !completed,
            istrackeduser: true, // We know that we're tracking completion for this user given the presence of this button.
            normalbutton: !toggleButton.classList.contains('btn-sm'),
            withavailability: toggleButton.getAttribute('data-hasavailability') || 0,
        };
        const renderObject = await Templates.renderForPromise('format_mtubebs/completion_manual', templateContext);

        // Replace the toggle button with the newly loaded template.
        const replacedNode = await Templates.replaceNode(toggleButton, renderObject.html, renderObject.js);
        const newToggleButton = replacedNode.pop();

        // Build manualCompletionToggled custom event.
        const hasAvailability = toggleButton.getAttribute('data-hasavailability');
        // We're doing this because an error is thrown when cm is duplicated without page refresh and completion is toggled.
        const eventname = window.MTubeBs.canedit
            ? 'core_course:manualcompletiontoggled-mtubebs' : CourseEvents.manualCompletionToggled;
        const toggledEvent = new CustomEvent(eventname, {
            bubbles: true,
            detail: {
                cmid,
                activityname,
                completed,
                withAvailability: 0,
                hasAvailability,
            }
        });
        // Dispatch the manualCompletionToggled custom event.
        newToggleButton.dispatchEvent(toggledEvent);

    } catch (exception) {
        // In case of an error, revert the original state and appearance of the button.
        toggleButton.removeAttribute('disabled');
        toggleButton.innerHTML = originalInnerHtml;

        // Show the exception.
        notifyError(exception);
    }
    pendingPromise.resolve();
};
