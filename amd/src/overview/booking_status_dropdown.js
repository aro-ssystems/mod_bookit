// This file is part of Moodle - https://moodle.org/
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
 * Reactive booking status dropdown for the event overview table.
 *
 * Listens for changes on select[data-action="update-booking-status"] and
 * persists the new status via the update_event_booking_status web service.
 *
 * @module     mod_bookit/overview/booking_status_dropdown
 * @copyright  2026 ssystems GmbH <oss@ssystems.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const SELECTOR = 'select[data-action="update-booking-status"]';

/** Background and text colours per booking status value. */
const STATUS_COLORS = {
    0: {bg: '#d3d3d3', fg: '#000000'},
    1: {bg: '#fff3cd', fg: '#000000'},
    2: {bg: '#d4edda', fg: '#000000'},
    3: {bg: '#343a40', fg: '#ffffff'},
    4: {bg: '#f8d7da', fg: '#000000'},
};

/**
 * Apply status colour to a select element.
 *
 * @param {HTMLSelectElement} select
 * @param {number} status
 */
const applyColor = (select, status) => {
    const colors = STATUS_COLORS[status] ?? {bg: '#ffffff', fg: '#000000'};
    select.style.backgroundColor = colors.bg;
    select.style.color = colors.fg;
    const td = select.closest('td');
    if (td) {
        td.style.backgroundColor = colors.bg;
    }
};

/**
 * Initialise the dropdown listener on the overview table.
 */
export const init = () => {
    // Apply initial colours to all existing dropdowns on the page.
    document.querySelectorAll(SELECTOR).forEach((select) => {
        applyColor(select, parseInt(select.dataset.bookingstatus, 10));
    });

    document.addEventListener('change', (e) => {
        const select = e.target.closest(SELECTOR);
        if (!select) {
            return;
        }

        const cmid    = parseInt(select.dataset.cmid, 10);
        const eventid = parseInt(select.dataset.eventid, 10);
        const status  = parseInt(select.value, 10);

        select.disabled = true;

        Ajax.call([{
            methodname: 'mod_bookit_update_event_booking_status',
            args: {cmid, eventid, status},
        }])[0]
        .then(() => {
            applyColor(select, status);
            select.disabled = false;
            return;
        })
        .catch((err) => {
            select.disabled = false;
            Notification.exception(err);
        });
    });
};
