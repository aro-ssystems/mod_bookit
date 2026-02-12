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

import BaseChecklistContainer from 'mod_bookit/checklist/base_checklist_container';
import {resourceChecklistReactiveInstance, initResourceChecklistReactive} from './resource_checklist_reactive';
import {initializeFromDOM} from 'mod_bookit/checklist/checklist_helper';
import ResourceChecklistCategory from './resource_checklist_category';

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

        const contextId = parseInt(element.dataset.contextid);

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

        initResourceChecklistReactive({
            categories: data.categories,
            checklistitems: data.items,
        });

        return new this({
            element: element,
            reactive: resourceChecklistReactiveInstance,
            selectors: {contextId},
        });
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
