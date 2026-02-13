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
 * Base checklist reactive store factory.
 *
 * Provides a factory function for creating reactive stores for checklist-style components.
 * Uses configuration object to customize store name, event name, selectors, and state keys.
 *
 * @module mod_bookit/checklist/base_checklist_reactive
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';

/**
 * Create a reactive store for a checklist component.
 *
 * @param {Object} config - Configuration object
 * @param {string} config.name - Store name (e.g., 'Resource Catalog')
 * @param {string} config.eventName - Custom event name (e.g., 'mod_bookit:resource_catalog_state_event')
 * @param {Object} config.selectors - Selectors for DOM elements
 * @param {string} config.selectors.TABLE - Main table selector
 * @param {string} config.selectors.ALL_CATEGORY_ROWS - Category rows selector
 * @param {string} config.selectors.ALL_ITEM_ROWS - Item rows selector
 * @param {Class} config.mutationsClass - Mutations class instance
 * @param {Object} config.initialState - Initial state object with categories and items
 * @return {Object} Object with reactiveInstance and initReactive function
 */
export const createChecklistReactive = (config) => {
    let reactiveInstance = null;

    /**
     * Dispatch the checklist state event.
     *
     * @param {Object} detail - The event detail
     * @param {HTMLElement} target - The target element
     */
    function dispatchStateEvent(detail, target) {
        if (target === undefined) {
            target = document;
        }
        target.dispatchEvent(
            new CustomEvent(
                config.eventName,
                {
                    bubbles: true,
                    detail: detail,
                }
            )
        );
    }

    /**
     * Initialize checklist reactive store.
     *
     * @param {Object} initialState - Initial state with categories and items
     * @param {Array} initialState.categories - Array of category data
     * @param {Array} initialState.items - Array of item data
     * @return {Reactive} Reactive instance
     */
    const initReactive = (initialState) => {
        if (reactiveInstance === null) {
            reactiveInstance = new Reactive({
                name: config.name,
                eventName: config.eventName,
                eventDispatch: dispatchStateEvent,
                mutations: config.mutationsClass,
            });

            // Set initial state - Moodle automatically converts arrays to Maps
            const stateKeys = config.stateKeys || {
                categories: 'categories',
                items: 'items'
            };

            const state = {};
            state[stateKeys.categories] = initialState.categories || initialState[stateKeys.categories] || [];
            state[stateKeys.items] = initialState.items || initialState[stateKeys.items] || [];

            reactiveInstance.setInitialState(state);
        }

        return reactiveInstance;
    };

    return {
        SELECTORS: config.selectors,
        reactiveInstance,
        initReactive,
    };
};

/**
 * Export a helper to get standard selectors.
 *
 * @param {string} prefix - Prefix for data attributes (e.g., 'resource', 'checklist')
 * @return {Object} Selectors object
 */
export const getStandardSelectors = (prefix) => {
    return {
        TABLE: `#${prefix}-table`,
        ALL_CATEGORY_ROWS: `tbody[data-${prefix}-category-id]`,
        ALL_ITEM_ROWS: `tr[data-${prefix}-item-id]`,
    };
};
