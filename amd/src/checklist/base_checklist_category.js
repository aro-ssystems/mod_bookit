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
 * Base checklist category component.
 *
 * Abstract base class for category components providing common functionality:
 * - Item management and rendering
 * - Modal form handling
 * - Event listeners for add/edit/delete actions
 * - Config-driven specialization
 *
 * @module mod_bookit/checklist/base_checklist_category
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

/**
 * Base checklist category component.
 *
 * Specializations must implement:
 * - getConfig(): Return configuration object
 * - getItemComponent(): Return item component class
 *
 * Optional overrides:
 * - _renderCategoryRow(): Custom category row rendering
 * - _getCustomWatchers(): Add custom state watchers
 */
export default class BaseChecklistCategory extends BaseComponent {
    /**
     * Create component.
     *
     * @param {Object} options - Component options
     * @param {HTMLElement} options.element - Parent container element
     */
    create({element}) {
        this.parentElement = element;
        this.categoryElement = null;
        this.itemComponents = new Map();

        // Extract category data from DOM
        const categoryId = parseInt(element.dataset.categoryid);
        const config = this.getConfig();
        const categoriesKey = config.categoriesStateKey || 'categories';
        this.categoryData = this.reactive.state[categoriesKey].get(categoryId);
    }

    /**
     * Get state watchers.
     *
     * @return {Array} Array of watcher definitions
     */
    getWatchers() {
        const config = this.getConfig();
        const itemsKey = config.itemsStateKey || 'items';

        const baseWatchers = [
            {watch: `${itemsKey}:created`, handler: this._handleItemCreated},
            {watch: `${itemsKey}.categoryid:updated`, handler: this._handleItemCategoryChanged},
            {watch: `${itemsKey}:deleted`, handler: this._handleItemDeleted},
        ];

        // Allow specializations to add custom watchers
        if (this._getCustomWatchers) {
            return [...baseWatchers, ...this._getCustomWatchers()];
        }

        return baseWatchers;
    }

    /**
     * Get configuration object.
     *
     * Must be implemented by specializations.
     *
     * @return {Object} Configuration object with:
     *   - categoriesStateKey: State key for categories (default: 'categories')
     *   - itemsStateKey: State key for items (default: 'items')
     *   - itemType: Name of item type (e.g., 'resource', 'checklistitem')
     *   - itemModalForm: Full class name for item modal form
     *   - categoryModalForm: Full class name for category modal form
     *   - templates: Object with template names
     */
    getConfig() {
        throw new Error('getConfig() must be implemented by specialization');
    }

    /**
     * Get item component class.
     *
     * Must be implemented by specializations.
     *
     * @return {Class} Item component class
     */
    getItemComponent() {
        throw new Error('getItemComponent() must be implemented by specialization');
    }

    /**
     * Initialize state ready.
     */
    stateReady() {
        this.categoryElement = this.element;
        this._initItemsFromDOM();
        this._attachEventListeners();
    }

    /**
     * Update category row.
     *
     * @param {Object} categoryData - Updated category data
     */
    _updateCategoryRow(categoryData) {
        this.categoryData = categoryData;
        if (!this.categoryElement) {
            return;
        }

        // Update dataset
        this.categoryElement.dataset.categoryName = categoryData.name;
        this.categoryElement.dataset.categoryDescription = categoryData.description || '';
        this.categoryElement.dataset.categorySortorder = categoryData.sortorder || 0;

        // Update row content
        const categoryRow = this.categoryElement.querySelector('[data-region="checklist-category-row"]');
        if (categoryRow) {
            const nameCell = categoryRow.querySelector('[data-field="category-name"]');
            if (nameCell) {
                nameCell.textContent = categoryData.name;
            }
        }
    }

    /**
     * Initialize item components from existing DOM.
     */
    _initItemsFromDOM() {
        if (!this.categoryElement) {
            return;
        }

        const config = this.getConfig();
        const itemsKey = config.itemsStateKey || 'items';
        const state = this.reactive.state;
        const itemElements = this.categoryElement.querySelectorAll('[data-region="checklist-item-row"]');

        itemElements.forEach(itemElement => {
            const itemId = parseInt(itemElement.dataset.itemid);
            const itemData = state[itemsKey].get(itemId);

            if (itemData && itemData.categoryid === this.categoryData.id) {
                const ItemComponent = this.getItemComponent();
                const itemComponent = new ItemComponent({
                    element: itemElement,
                    reactive: this.reactive,
                });

                this.itemComponents.set(itemData.id, itemComponent);
            }
        });
    }

    /**
     * Render items in this category.
     */
    async _renderItems() {
        if (!this.categoryElement) {
            return;
        }

        const config = this.getConfig();
        const itemsKey = config.itemsStateKey || 'items';

        // Clear existing item rows (keep category row)
        const categoryRow = this.categoryElement.querySelector('[data-region="checklist-category-row"]');
        this.categoryElement.innerHTML = '';
        if (categoryRow) {
            this.categoryElement.appendChild(categoryRow);
        }
        this.itemComponents.clear();

        // Get items from state
        const state = this.reactive.state;
        const items = Array.from(state[itemsKey].values())
            .filter(item => item.categoryid === this.categoryData.id)
            .sort((a, b) => a.sortorder - b.sortorder);

        // Render each item
        for (const itemData of items) {
            const ItemComponent = this.getItemComponent();
            const itemComponent = new ItemComponent({
                element: this.categoryElement,
                reactive: this.reactive,
                itemData: itemData,
            });
            // Explicitly call render for dynamically created items
            if (itemComponent._render) {
                await itemComponent._render();
            }
            this.itemComponents.set(itemData.id, itemComponent);
        }
    }

    /**
     * Handle item created.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - New item data
     */
    async _handleItemCreated({element}) {
        if (element.categoryid === this.categoryData.id) {
            await this._renderItems();
        }
    }

    /**
     * Handle item category changed.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated item data
     */
    async _handleItemCategoryChanged({element}) {
        const hadItem = this.itemComponents.has(element.id);
        const shouldHaveItem = element.categoryid === this.categoryData.id;

        if (hadItem !== shouldHaveItem) {
            await this._renderItems();
        }
    }

    /**
     * Handle item deleted.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Deleted item data
     */
    _handleItemDeleted({element}) {
        const component = this.itemComponents.get(element.id);
        if (component) {
            if (component.unregister) {
                component.unregister();
            }
            this.itemComponents.delete(element.id);
        }

        // Remove item element from DOM
        const itemElement = this.categoryElement.querySelector(
            `[data-region="checklist-item-row"][data-itemid="${element.id}"]`
        );
        if (itemElement) {
            itemElement.remove();
        }
    }

    /**
     * Replace rendered item.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated item data
     */
    _replaceRenderedItem({element}) {
        const component = this.itemComponents.get(element.id);
        if (component && component._updateRow) {
            component._updateRow(element);
        }
    }

    /**
     * Attach event listeners.
     */
    _attachEventListeners() {
        if (!this.categoryElement) {
            return;
        }

        // Add Item button
        const addBtn = this.categoryElement.querySelector('[data-action="add-item"]');
        if (addBtn) {
            this.addEventListener(addBtn, 'click', this._handleAddItem.bind(this));
        }

        // Edit Category button
        const editBtn = this.categoryElement.querySelector('[data-action="edit-category"]');
        if (editBtn) {
            this.addEventListener(editBtn, 'click', this._handleEdit.bind(this));
        }

        // Delete Category button
        const deleteBtn = this.categoryElement.querySelector('[data-action="delete-category"]');
        if (deleteBtn) {
            this.addEventListener(deleteBtn, 'click', this._handleDelete.bind(this));
        }
    }

    /**
     * Handle add item.
     *
     * @param {Event} event - Click event
     */
    async _handleAddItem(event) {
        event.preventDefault();

        const config = this.getConfig();
        const modalForm = new ModalForm({
            formClass: config.itemModalForm,
            args: {
                id: 0,
                categoryid: this.categoryData.id,
            },
            modalConfig: {
                title: await getString(`add${config.itemType}`, 'mod_bookit'),
            },
            returnFocus: event.currentTarget,
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            const response = e.detail;
            if (response.result === 'success') {
                this.reactive.dispatch('itemsUpdated', {fields: response.data});
            }
        });

        modalForm.show();
    }

    /**
     * Handle edit category.
     *
     * @param {Event} event - Click event
     */
    async _handleEdit(event) {
        event.preventDefault();

        const config = this.getConfig();
        const modalForm = new ModalForm({
            formClass: config.categoryModalForm,
            args: {id: this.categoryData.id},
            modalConfig: {
                title: await getString('editcategory', 'mod_bookit'),
            },
            returnFocus: event.currentTarget,
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            const response = e.detail;
            if (response.result === 'success') {
                this.reactive.dispatch('categoriesUpdated', {fields: response.data});
            }
        });

        modalForm.show();
    }

    /**
     * Handle delete category.
     *
     * @param {Event} event - Click event
     */
    async _handleDelete(event) {
        event.preventDefault();

        const confirmed = await Notification.confirm(
            await getString('confirm', 'core'),
            await getString('deletecategory_confirm', 'mod_bookit'),
            await getString('delete', 'core'),
            await getString('cancel', 'core')
        );

        if (confirmed) {
            this.reactive.dispatch('categoriesDeleted', {fields: {id: this.categoryData.id}});
        }
    }
}
