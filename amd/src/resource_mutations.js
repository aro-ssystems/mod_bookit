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
 * Mutations for resource catalog reactive state.
 *
 * @module mod_bookit/resource_mutations
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default class {
    /**
     * Placeholder method for resource catalog state events.
     */
    resourceCatalogStateEvent() {
        // This method is intentionally empty - it's a placeholder for state events.
    }

    /**
     * Handle category deletion.
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {number} categoryId - The ID of the category to delete
     */
    deleteCategory(stateManager, categoryId) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        // Delete all items in this category first
        const itemsToDelete = [];
        state.items.forEach((item, id) => {
            if (item.categoryid === categoryId) {
                itemsToDelete.push(id);
            }
        });
        itemsToDelete.forEach(id => state.items.delete(id));

        // Delete the category
        state.categories.delete(categoryId);

        stateManager.setReadOnly(true);
    }

    /**
     * Handle item deletion.
     *
     * @param {Object} stateManager - The reactive state manager
     * @param {number} itemId - The ID of the item to delete
     */
    deleteItem(stateManager, itemId) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        state.items.delete(itemId);

        stateManager.setReadOnly(true);
    }
}
