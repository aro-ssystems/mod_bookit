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
 * Event checklist container component.
 *
 * Initializes the reactive store from DOM data and registers
 * one EventChecklistItem component per item row.
 *
 * @module mod_bookit/event_checklist/event_checklist_container
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {getReactive} from 'mod_bookit/event_checklist/event_checklist_reactive';
import EventChecklistItem from 'mod_bookit/event_checklist/event_checklist_item';

/**
 * Event checklist container component.
 */
export default class EventChecklistContainer extends BaseComponent {
    /**
     * Static factory: parse DOM, init reactive, create component.
     *
     * @param {string} target - CSS selector for the container element
     * @return {EventChecklistContainer|null}
     */
    static init(target) {
        const element = document.querySelector(target);
        if (!element) {
            return null;
        }

        // Parse items from DOM data-attributes.
        const items = [];
        element.querySelectorAll('[data-region="event-checklist-item-row"]').forEach(row => {
            items.push({
                id:         parseInt(row.dataset.itemid),
                resourceid: parseInt(row.dataset.itemResourceid),
                status:     row.dataset.itemStatus || 'requested',
            });
        });

        const reactive = getReactive();

        // Create component BEFORE setInitialState so it registers for stateReady.
        const instance = new EventChecklistContainer({
            element,
            reactive,
        });

        // Moodle reactive converts the array to a Map keyed by item.id.
        reactive.setInitialState({items});

        return instance;
    }

    /**
     * State ready: register all item components.
     */
    stateReady() {
        this.element.querySelectorAll('[data-region="event-checklist-item-row"]').forEach(row => {
            new EventChecklistItem({
                element: row,
                reactive: this.reactive,
            });
        });
    }

    /**
     * No container-level watchers needed (items handle their own status).
     *
     * @return {Array}
     */
    getWatchers() {
        return [];
    }
}
