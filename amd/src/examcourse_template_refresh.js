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
 * Reloads the exam course templates in the booking form when the faculty or term changes.
 *
 * @module      mod_bookit/examcourse_template_refresh
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {exception as displayException} from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';

/** CSS class of the hint shown when a chosen template no longer fits. */
const HINT_CLASS = 'mod-bookit-examcourse-template-hint';

/**
 * Show or hide the whole form row of the template field.
 *
 * @param {HTMLElement} row
 * @param {boolean} visible
 */
const setRowVisible = (row, visible) => {
    row.classList.toggle('d-none', !visible);
    row.hidden = !visible;
};

/**
 * Remove the reset hint, if any.
 *
 * @param {HTMLElement} row
 */
const removeHint = (row) => {
    row.querySelectorAll('.' + HINT_CLASS).forEach((hint) => hint.remove());
};

/**
 * Tell the user that the chosen template was reset and has to be chosen again.
 *
 * @param {HTMLElement} row
 * @param {HTMLSelectElement} select
 * @returns {Promise}
 */
const showHint = async(row, select) => {
    const text = await getString('examcourse_template_reset', 'mod_bookit');
    removeHint(row);
    const hint = document.createElement('div');
    hint.className = 'form-text text-warning ' + HINT_CLASS;
    hint.setAttribute('role', 'status');
    hint.textContent = text;
    (select.closest('.felement') || select.parentElement).appendChild(hint);
};

/**
 * Integer value of a form field, 0 when it is missing or empty.
 *
 * @param {HTMLFormElement} form
 * @param {string} name
 * @returns {number}
 */
const fieldValue = (form, name) => {
    const field = form.querySelector('[name="' + name + '"]');
    return field ? (parseInt(field.value, 10) || 0) : 0;
};

/**
 * Replace the offered templates, keep the choice if it still fits, otherwise reset it with a hint.
 *
 * @param {HTMLElement} row
 * @param {HTMLSelectElement} select
 * @param {{visible: boolean, options: Array<{id: number, name: string}>}} result
 * @returns {Promise|undefined}
 */
const applyTemplates = (row, select, result) => {
    const previous = parseInt(select.value, 10) || 0;
    removeHint(row);

    // The first option is the empty choice and stays.
    while (select.options.length > 1) {
        select.remove(1);
    }
    result.options.forEach((option) => select.add(new Option(option.name, String(option.id))));

    const stillOffered = result.options.some((option) => option.id === previous);
    select.value = stillOffered ? String(previous) : '0';

    setRowVisible(row, result.visible);
    if (result.visible && previous > 0 && !stillOffered) {
        return showHint(row, select);
    }
    return undefined;
};

/**
 * Initialise the refresh for one booking form.
 *
 * Only loaded for users who may choose the template and edit the booking.
 *
 * @param {string} formId Id of the booking form
 * @param {number} cmId Course module id
 * @param {number} eventId Booking id, 0 for a new booking
 * @param {boolean} hasTemplates Whether templates are offered when the form opens
 */
export const init = (formId, cmId, eventId, hasTemplates) => {
    const form = document.getElementById(formId);
    const select = form?.querySelector('select[name="coursetemplate"]');
    if (!select) {
        return;
    }
    const row = select.closest('.fitem') || select.parentElement;
    setRowVisible(row, hasTemplates);

    // Every change starts a new request; only the answer to the latest one is applied.
    let latestRequest = 0;
    const refresh = () => {
        latestRequest++;
        const requestId = latestRequest;
        const pending = new Pending('mod_bookit/examcourse_template_refresh');
        const request = fetchMany([{
            methodname: 'mod_bookit_get_examcourse_template_options',
            args: {
                cmid: cmId,
                eventid: eventId,
                institutionid: fieldValue(form, 'institutionid'),
                semester: fieldValue(form, 'semester'),
            },
        }])[0];
        // The ajax module returns a jQuery promise; wrap it to get the full native promise chain.
        return Promise.resolve(request)
            .then((result) => (requestId === latestRequest ? applyTemplates(row, select, result) : undefined))
            .catch((error) => {
                // The list stays as it was; errors of outdated requests do not matter any more.
                if (requestId === latestRequest) {
                    displayException(error);
                }
            })
            .finally(() => pending.resolve());
    };

    ['institutionid', 'semester'].forEach((name) => {
        form.querySelector('select[name="' + name + '"]')?.addEventListener('change', refresh);
    });
    select.addEventListener('change', () => removeHint(row));
};
