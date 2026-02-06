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
 * Reactive store for resource catalog.
 *
 * @module mod_bookit/resource_reactive
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';

const EVENTNAME = 'mod_bookit:resource_catalog_state_event';

let resourceReactiveInstance = null;

/**
 * Initialize resource reactive store.
 *
 * @param {Object} initialState - Initial state with categories and items
 * @param {Array} initialState.categories - Array of category data
 * @param {Array} initialState.items - Array of item data
 * @return {Reactive} Reactive instance
 */
export const initResourceReactive = (initialState) => {
    if (resourceReactiveInstance === null) {
        resourceReactiveInstance = new Reactive({
            name: 'Moodle Bookit Resource Catalog',
            eventName: EVENTNAME,
            eventDispatch: dispatchResourceStateEvent,
            mutations: {
                // Category Mutations.
                createCategory: (state, category) => {
                    state.categories.push({
                        id: category.id,
                        name: category.name,
                        description: category.description || '',
                        sortorder: category.sortorder || state.categories.length,
                    });
                },

                updateCategory: (state, category) => {
                    const index = state.categories.findIndex(c => c.id === category.id);
                    if (index !== -1) {
                        state.categories[index] = {
                            id: category.id,
                            name: category.name,
                            description: category.description || '',
                            sortorder: category.sortorder,
                        };
                    }
                },

                deleteCategory: (state, categoryId) => {
                    // Delete all items in this category first.
                    state.items = state.items.filter(item => item.categoryid !== categoryId);

                    // Delete the category.
                    state.categories = state.categories.filter(c => c.id !== categoryId);
                },

                // Item Mutations.
                createItem: (state, item) => {
                    state.items.push({
                        id: item.id,
                        name: item.name,
                        description: item.description || '',
                        categoryid: item.categoryid,
                        amount: item.amount || 1,
                        amountirrelevant: item.amountirrelevant || false,
                        sortorder: item.sortorder || 0,
                    });
                },

                updateItem: (state, item) => {
                    const index = state.items.findIndex(i => i.id === item.id);
                    if (index !== -1) {
                        state.items[index] = {
                            id: item.id,
                            name: item.name,
                            description: item.description || '',
                            categoryid: item.categoryid,
                            amount: item.amount || 1,
                            amountirrelevant: item.amountirrelevant || false,
                            sortorder: item.sortorder,
                        };
                    }
                },

                deleteItem: (state, itemId) => {
                    state.items = state.items.filter(i => i.id !== itemId);
                },
            },
        });

        // Set initial state.
        resourceReactiveInstance.setInitialState({
            categories: initialState.categories,
            items: initialState.items,
        });
    }

    return resourceReactiveInstance;
};

/**
 * Dispatch the resource catalog state event.
 *
 * @param {Object} detail - The event detail
 * @param {HTMLElement} target - The target element
 */
function dispatchResourceStateEvent(detail, target) {
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

export {resourceReactiveInstance};
