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
 * Base checklist mutations class.
 *
 * Provides common mutation functions for CRUD operations on checklist-style reactive stores.
 * Can be extended by specializations to add domain-specific mutations.
 *
 * @module mod_bookit/checklist/base_checklist_mutations
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Base checklist mutations class.
 *
 * Provides standard mutations for categories and items.
 * Specializations can extend this class to add domain-specific mutations.
 *
 * Usage:
 * ```
 * import BaseChecklistMutations from 'mod_bookit/checklist/base_checklist_mutations';
 *
 * export default class ResourceMutations extends BaseChecklistMutations {
 *     constructor() {
 *         super('categories', 'items');
 *     }
 *
 *     // Add domain-specific mutations
 *     itemsActiveToggled(stateManager, data) {
 *         // Custom logic
 *     }
 * }
 * ```
 */
export default class BaseChecklistMutations {
    /**
     * Constructor.
     *
     * @param {string} categoriesKey - State key for categories (default: 'categories')
     * @param {string} itemsKey - State key for items (default: 'items')
     */
    constructor(categoriesKey = 'categories', itemsKey = 'items') {
        this.categoriesKey = categoriesKey;
        this.itemsKey = itemsKey;
    }

    /**
     * Placeholder method for state events.
     */
    checklistStateEvent() {
        // This method is intentionally empty - it's a placeholder for state events.
    }

    /**
     * Handle category created (called by processUpdates for action: 'create').
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {Object} data - Data with fields object
     */
    categoriesCreated(stateManager, data) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        state[this.categoriesKey].set(data.fields.id, {
            id: data.fields.id,
            name: data.fields.name,
            description: data.fields.description || '',
            sortorder: data.fields.sortorder || 0,
        });

        stateManager.setReadOnly(true);
    }

    /**
     * Handle category updated (called by processUpdates for action: 'put').
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {Object} data - Data with fields object
     */
    categoriesUpdated(stateManager, data) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        const existing = state[this.categoriesKey].get(data.fields.id) || {};
        state[this.categoriesKey].set(data.fields.id, {
            ...existing,
            id: data.fields.id,
            name: data.fields.name,
            description: data.fields.description || '',
            sortorder: data.fields.sortorder || existing.sortorder || 0,
        });

        stateManager.setReadOnly(true);
    }

    /**
     * Handle category deletion (called by processUpdates for action: 'delete').
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {Object} data - Data with fields object containing id
     */
    categoriesDeleted(stateManager, data) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        // Delete all items in this category first
        const itemsToDelete = [];
        state[this.itemsKey].forEach((item, id) => {
            if (item.categoryid === data.fields.id) {
                itemsToDelete.push(id);
            }
        });
        itemsToDelete.forEach(id => state[this.itemsKey].delete(id));

        // Delete the category
        state[this.categoriesKey].delete(data.fields.id);

        stateManager.setReadOnly(true);
    }

    /**
     * Handle item created (called by processUpdates for action: 'create').
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {Object} data - Data with fields object
     */
    itemsCreated(stateManager, data) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        state[this.itemsKey].set(data.fields.id, {
            id: data.fields.id,
            name: data.fields.name,
            description: data.fields.description || '',
            categoryid: data.fields.categoryid,
            sortorder: data.fields.sortorder || 0,
            ...this._getItemFields(data.fields),
        });

        stateManager.setReadOnly(true);
    }

    /**
     * Handle item updated (called by processUpdates for action: 'put').
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {Object} data - Data with fields object
     */
    itemsUpdated(stateManager, data) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        const existing = state[this.itemsKey].get(data.fields.id) || {};
        state[this.itemsKey].set(data.fields.id, {
            ...existing,
            id: data.fields.id,
            name: data.fields.name,
            description: data.fields.description || '',
            categoryid: data.fields.categoryid,
            sortorder: data.fields.sortorder || existing.sortorder || 0,
            ...this._getItemFields(data.fields),
        });

        stateManager.setReadOnly(true);
    }

    /**
     * Handle item deletion (called by processUpdates for action: 'delete').
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {Object} data - Data with fields object containing id
     */
    itemsDeleted(stateManager, data) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        state[this.itemsKey].delete(data.fields.id);

        stateManager.setReadOnly(true);
    }

    /**
     * Extract domain-specific item fields.
     *
     * Can be overridden by specializations to add domain-specific fields.
     *
     * @param {Object} fields - All fields from the data
     * @return {Object} Domain-specific fields
     */
    _getItemFields(fields) { // eslint-disable-line no-unused-vars
        // Base implementation returns empty object
        // Specializations can override to add fields like:
        // return {
        //     active: Boolean(fields.active),
        //     amount: fields.amount || 0,
        //     duedate: fields.duedate || null,
        // };
        return {};
    }

    /**
     * Extract domain-specific category fields.
     *
     * Can be overridden by specializations to add domain-specific fields.
     *
     * @param {Object} fields - All fields from the data
     * @return {Object} Domain-specific fields
     */
    _getCategoryFields(fields) { // eslint-disable-line no-unused-vars
        // Base implementation returns empty object
        // Specializations can override to add domain-specific fields
        return {};
    }
}
