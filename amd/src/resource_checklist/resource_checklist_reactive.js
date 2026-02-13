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
 * Reactive store for resource checklist.
 *
 * @module mod_bookit/resource_checklist/resource_checklist_reactive
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import ResourceChecklistMutations from 'mod_bookit/resource_checklist/resource_checklist_mutations';

export const SELECTORS = {
    TABLE: '#resource-checklist-table',
    ALL_CATEGORY_ROWS: 'tbody[data-bookit-category-id]',
    ALL_ITEM_ROWS: 'tr[data-bookit-item-id]',
};

const EVENTNAME = 'mod_bookit:resource_checklist_state_event';

let resourceChecklistReactiveInstance = null;

/**
 * Initialize resource checklist reactive store.
 *
 * @param {Object} initialState - Initial state with categories and checklistitems
 * @param {Array} initialState.categories - Array of category data
 * @param {Array} initialState.checklistitems - Array of checklist item data
 * @return {Reactive} Reactive instance
 */
export const initResourceChecklistReactive = (initialState) => {
    if (resourceChecklistReactiveInstance === null) {
        resourceChecklistReactiveInstance = new Reactive({
            name: 'Moodle Bookit Resource Checklist',
            eventName: EVENTNAME,
            eventDispatch: dispatchResourceChecklistStateEvent,
            mutations: new ResourceChecklistMutations(),
        });

        // Set initial state - Moodle automatically converts arrays to Maps.
        resourceChecklistReactiveInstance.setInitialState({
            categories: initialState.categories,
            checklistitems: initialState.checklistitems,
        });
    }

    return resourceChecklistReactiveInstance;
};

/**
 * Dispatch the resource checklist state event.
 *
 * @param {Object} detail - The event detail
 * @param {HTMLElement} target - The target element
 */
function dispatchResourceChecklistStateEvent(detail, target) {
    if (target === undefined) {
        target = document;
    }
    target.dispatchEvent(
        new CustomEvent(
            EVENTNAME,
            {
                bubbles: true,
                detail: detail,
            }
        )
    );
}

export {resourceChecklistReactiveInstance};
