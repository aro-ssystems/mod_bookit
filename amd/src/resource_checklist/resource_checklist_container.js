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
 * Resource checklist container component.
 *
 * @module mod_bookit/resource_checklist/resource_checklist_container
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import BaseChecklistContainer from 'mod_bookit/checklist/base_checklist_container';
import ResourceChecklistMutations from './resource_checklist_mutations';
import {initializeFromDOM} from 'mod_bookit/checklist/checklist_helper';
import ResourceChecklistCategory from './resource_checklist_category';

const EVENTNAME = 'mod_bookit:resource_checklist_state_event';
let resourceChecklistReactiveInstance = null;

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

/**
 * Resource checklist container component.
 */
export default class extends BaseChecklistContainer {
    /**
     * Static init method.
     *
     * @param {string} target - CSS selector for container element
     * @return {Object} Component instance
     */
    static init(target) {
        const element = document.querySelector(target);
        if (!element) {
            return null;
        }

        // Initialize from DOM
        const data = initializeFromDOM(element, {
            categoryRegion: 'resource-checklist-category',
            itemRegion: 'resource-checklist-item-row',
            itemFields: {
                resourceid: 'itemResourceid',
                duedate: (el) => el.dataset.itemDuedate ? parseInt(el.dataset.itemDuedate) : null,
                duedatetype: 'itemDuedatetype',
                active: (el) => el.dataset.itemActive === '1',
            }
        });

        // Initialize reactive WITHOUT setting state yet
        if (!resourceChecklistReactiveInstance) {
            resourceChecklistReactiveInstance = new Reactive({
                name: 'Moodle Bookit Resource Checklist',
                eventName: 'mod_bookit:resource_checklist_state_event',
                eventDispatch: dispatchResourceChecklistStateEvent,
                mutations: new ResourceChecklistMutations(),
            });
        }

        // Create component FIRST so it registers for stateReady
        const instance = new this({
            element: element,
            reactive: resourceChecklistReactiveInstance,
        });

        // Set state AFTER component is registered
        resourceChecklistReactiveInstance.setInitialState({
            categories: data.categories,
            checklistitems: data.items,
        });

        return instance;
    }

    /**
     * Component descriptor for debugging.
     *
     * @return {string} Component name
     */
    static get componentName() {
        return 'mod_bookit/resource_checklist/resource_checklist_container';
    }

    /**
     * Get configuration object.
     *
     * @return {Object} Configuration
     */
    getConfig() {
        return {
            categoriesStateKey: 'categories',
            itemsStateKey: 'checklistitems',
            itemType: 'checklistitem',
            categoryRegion: 'resource-checklist-category',
            itemRegion: 'resource-checklist-item-row',
            categoryModalForm: 'mod_bookit\\form\\edit_category_form',
            itemModalForm: 'mod_bookit\\form\\edit_resource_checklist_item_form',
            selectors: {
                addCategoryBtn: '#add-category-btn',
                addItemBtn: '#add-checklist-item-btn',
                tableView: '#resource-checklist-table-view',
            }
        };
    }

    /**
     * Get category component class.
     *
     * @return {Class} Category component class
     */
    getCategoryComponent() {
        return ResourceChecklistCategory;
    }
}
