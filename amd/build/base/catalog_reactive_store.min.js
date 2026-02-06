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
 * Reactive store factory for catalog-based modules.
 *
 * @module mod_bookit/base/catalog_reactive_store
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import {call as ajaxCall} from 'core/ajax';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

/**
 * Creates a reactive store for catalog-based modules.
 *
 * @param {Object} config - Configuration object
 * @param {string} config.storeName - Unique store name (e.g., 'mod_bookit-resources')
 * @param {string} config.categoriesName - State key for categories (default: 'categories')
 * @param {string} config.itemsName - State key for items (default: 'items')
 * @param {string} config.ajaxEndpoint - AJAX endpoint prefix (e.g., 'mod_bookit_resource')
 * @return {Reactive} Reactive store instance
 */
export const createCatalogStore = (config) => {
    // Defaults
    const storeName = config.storeName || 'mod_bookit-catalog';
    const categoriesName = config.categoriesName || 'categories';
    const itemsName = config.itemsName || 'items';
    const ajaxEndpoint = config.ajaxEndpoint || 'mod_bookit_catalog';

    // Create Reactive Instance
    const reactive = new Reactive({
        name: storeName,
        eventName: `${storeName}:statechanged`,
        eventDispatch: () => {
            // Dispatch to document for global listening
            return document;
        },
    });

    /**
     * Mutation: Prepare state from server data.
     *
     * Loads initial categories and items from server data into the state.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts initial data
     */
    const prepareState = (stateManager) => {
        return (data) => {
            const state = stateManager.state;

            // Initialize state structure
            state[categoriesName] = state[categoriesName] || new Map();
            state[itemsName] = state[itemsName] || new Map();
            state.activeFilters = state.activeFilters || {};

            // Load categories
            if (data.categories) {
                data.categories.forEach(category => {
                    state[categoriesName].set(category.id, category);
                });
            }

            // Load items
            if (data.items) {
                data.items.forEach(item => {
                    state[itemsName].set(item.id, item);
                });
            }

            // Trigger state update
            stateManager.setReadOnly(false);
            stateManager.state = state;
            stateManager.setReadOnly(true);
        };
    };

    /**
     * Mutation: Create new category.
     *
     * Calls AJAX endpoint to persist category and updates state.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts category data
     * @throws {Error} If AJAX call fails
     */
    const createCategory = (stateManager) => {
        return async(data) => {
            try {
                // AJAX Call to backend
                const result = await ajaxCall([{
                    methodname: `${ajaxEndpoint}_create_category`,
                    args: {
                        name: data.name,
                        description: data.description || '',
                        sortorder: data.sortorder || 0,
                        active: data.active !== undefined ? data.active : 1,
                    },
                }]);

                // Add to state
                const state = stateManager.state;
                state[categoriesName].set(result[0].id, result[0]);
                stateManager.setReadOnly(false);
                stateManager.state = state;
                stateManager.setReadOnly(true);

                // Success notification
                const message = await getString('category_created', 'mod_bookit');
                Notification.addNotification({
                    message: message,
                    type: 'success',
                });
            } catch (error) {
                Notification.exception(error);
            }
        };
    };

    /**
     * Mutation: Update existing category.
     *
     * Calls AJAX endpoint to update category and refreshes state.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts category data
     * @throws {Error} If AJAX call fails
     */
    const updateCategory = (stateManager) => {
        return async(data) => {
            try {
                // AJAX Call
                const result = await ajaxCall([{
                    methodname: `${ajaxEndpoint}_update_category`,
                    args: {
                        categoryid: data.id,
                        name: data.name,
                        description: data.description,
                        active: data.active,
                    },
                }]);

                // Update state
                const state = stateManager.state;
                state[categoriesName].set(data.id, result[0]);
                stateManager.setReadOnly(false);
                stateManager.state = state;
                stateManager.setReadOnly(true);

                const message = await getString('category_updated', 'mod_bookit');
                Notification.addNotification({
                    message: message,
                    type: 'success',
                });
            } catch (error) {
                Notification.exception(error);
            }
        };
    };

    /**
     * Mutation: Delete category.
     *
     * Removes category and all associated items from state and backend.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts category ID
     * @throws {Error} If AJAX call fails
     */
    const deleteCategory = (stateManager) => {
        return async(categoryId) => {
            try {
                // AJAX Call
                await ajaxCall([{
                    methodname: `${ajaxEndpoint}_delete_category`,
                    args: {categoryid: categoryId},
                }]);

                // Remove from state
                const state = stateManager.state;
                state[categoriesName].delete(categoryId);

                // Remove all items in this category
                state[itemsName].forEach((item, itemId) => {
                    if (item.categoryid === categoryId) {
                        state[itemsName].delete(itemId);
                    }
                });

                stateManager.setReadOnly(false);
                stateManager.state = state;
                stateManager.setReadOnly(true);

                const message = await getString('category_deleted', 'mod_bookit');
                Notification.addNotification({
                    message: message,
                    type: 'success',
                });
            } catch (error) {
                Notification.exception(error);
            }
        };
    };

    /**
     * Mutation: Create new item.
     *
     * Calls AJAX endpoint to persist item and updates state.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts item data
     * @throws {Error} If AJAX call fails
     */
    const createItem = (stateManager) => {
        return async(data) => {
            try {
                const result = await ajaxCall([{
                    methodname: `${ajaxEndpoint}_create_item`,
                    args: {
                        name: data.name,
                        categoryid: data.categoryid,
                        description: data.description || '',
                        amount: data.amount || 0,
                        amountirrelevant: data.amountirrelevant || 0,
                        sortorder: data.sortorder || 0,
                        active: data.active !== undefined ? data.active : 1,
                    },
                }]);

                const state = stateManager.state;
                state[itemsName].set(result[0].id, result[0]);
                stateManager.setReadOnly(false);
                stateManager.state = state;
                stateManager.setReadOnly(true);

                const message = await getString('item_created', 'mod_bookit');
                Notification.addNotification({
                    message: message,
                    type: 'success',
                });
            } catch (error) {
                Notification.exception(error);
            }
        };
    };

    /**
     * Mutation: Update existing item.
     *
     * Calls AJAX endpoint to update item and refreshes state.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts item data
     * @throws {Error} If AJAX call fails
     */
    const updateItem = (stateManager) => {
        return async(data) => {
            try {
                const result = await ajaxCall([{
                    methodname: `${ajaxEndpoint}_update_item`,
                    args: {
                        itemid: data.id,
                        name: data.name,
                        description: data.description,
                        amount: data.amount,
                        amountirrelevant: data.amountirrelevant,
                        active: data.active,
                    },
                }]);

                const state = stateManager.state;
                state[itemsName].set(data.id, result[0]);
                stateManager.setReadOnly(false);
                stateManager.state = state;
                stateManager.setReadOnly(true);

                const message = await getString('item_updated', 'mod_bookit');
                Notification.addNotification({
                    message: message,
                    type: 'success',
                });
            } catch (error) {
                Notification.exception(error);
            }
        };
    };

    /**
     * Mutation: Delete item.
     *
     * Removes item from state and backend.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts item ID
     * @throws {Error} If AJAX call fails
     */
    const deleteItem = (stateManager) => {
        return async(itemId) => {
            try {
                await ajaxCall([{
                    methodname: `${ajaxEndpoint}_delete_item`,
                    args: {itemid: itemId},
                }]);

                const state = stateManager.state;
                state[itemsName].delete(itemId);
                stateManager.setReadOnly(false);
                stateManager.state = state;
                stateManager.setReadOnly(true);

                const message = await getString('item_deleted', 'mod_bookit');
                Notification.addNotification({
                    message: message,
                    type: 'success',
                });
            } catch (error) {
                Notification.exception(error);
            }
        };
    };

    /**
     * Mutation: Reorder category (Drag & Drop).
     *
     * Updates category sort order via AJAX and applies optimistic UI update.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts reorder parameters
     * @throws {Error} If AJAX call fails
     */
    const reorderCategory = (stateManager) => {
        return async({categoryId, targetCategoryId}) => {
            try {
                // AJAX Call
                await ajaxCall([{
                    methodname: `${ajaxEndpoint}_reorder_categories`,
                    args: {
                        categoryid: categoryId,
                        targetcategoryid: targetCategoryId,
                    },
                }]);

                // Optimistic UI Update
                const state = stateManager.state;
                const category = state[categoriesName].get(categoryId);
                const targetCategory = state[categoriesName].get(targetCategoryId);

                if (category && targetCategory) {
                    // Swap sortorder
                    const tempOrder = category.sortorder;
                    category.sortorder = targetCategory.sortorder;
                    targetCategory.sortorder = tempOrder;

                    state[categoriesName].set(categoryId, category);
                    state[categoriesName].set(targetCategoryId, targetCategory);

                    stateManager.setReadOnly(false);
                    stateManager.state = state;
                    stateManager.setReadOnly(true);
                }
            } catch (error) {
                Notification.exception(error);
                // Reload to restore correct order
                window.location.reload();
            }
        };
    };

    /**
     * Mutation: Reorder item within or across categories.
     *
     * Updates item sort order and category assignment via AJAX.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts reorder parameters
     * @throws {Error} If AJAX call fails
     */
    const reorderItem = (stateManager) => {
        return async({itemId, targetItemId, categoryId}) => {
            try {
                await ajaxCall([{
                    methodname: `${ajaxEndpoint}_reorder_items`,
                    args: {
                        itemid: itemId,
                        targetitemid: targetItemId,
                        categoryid: categoryId,
                    },
                }]);

                // Optimistic UI Update
                const state = stateManager.state;
                const item = state[itemsName].get(itemId);
                const targetItem = state[itemsName].get(targetItemId);

                if (item && targetItem) {
                    // Update categoryid if moved
                    item.categoryid = categoryId;

                    // Swap sortorder
                    const tempOrder = item.sortorder;
                    item.sortorder = targetItem.sortorder;
                    targetItem.sortorder = tempOrder;

                    state[itemsName].set(itemId, item);
                    state[itemsName].set(targetItemId, targetItem);

                    stateManager.setReadOnly(false);
                    stateManager.state = state;
                    stateManager.setReadOnly(true);
                }
            } catch (error) {
                Notification.exception(error);
                window.location.reload();
            }
        };
    };

    /**
     * Mutation: Set filter value.
     *
     * Updates active filter state for UI filtering.
     *
     * @param {Object} stateManager - Reactive state manager
     * @return {Function} Mutation function that accepts filter parameters
     */
    const setFilter = (stateManager) => {
        return ({filterName, filterValue}) => {
            const state = stateManager.state;
            state.activeFilters[filterName] = filterValue;
            stateManager.setReadOnly(false);
            stateManager.state = state;
            stateManager.setReadOnly(true);
        };
    };

    // Register all mutations
    reactive.stateReady((state) => {
        state[categoriesName] = state[categoriesName] || new Map();
        state[itemsName] = state[itemsName] || new Map();
        state.activeFilters = state.activeFilters || {};
    });

    reactive.registerMutation('prepareState', prepareState);
    reactive.registerMutation('createCategory', createCategory);
    reactive.registerMutation('updateCategory', updateCategory);
    reactive.registerMutation('deleteCategory', deleteCategory);
    reactive.registerMutation('createItem', createItem);
    reactive.registerMutation('updateItem', updateItem);
    reactive.registerMutation('deleteItem', deleteItem);
    reactive.registerMutation('reorderCategory', reorderCategory);
    reactive.registerMutation('reorderItem', reorderItem);
    reactive.registerMutation('setFilter', setFilter);

    return reactive;
};
