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
 * Resource category reactive component.
 *
 * @module mod_bookit/resource_category
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import Templates from 'core/templates';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import ResourceItem from './resource_item';

/**
 * Resource category component.
 */
export default class ResourceCategory extends BaseComponent {
    /**
     * Component descriptor for debugging.
     *
     * @return {string} Component name
     */
    static get componentName() {
        return 'mod_bookit/resource_category';
    }

    /**
     * Create component.
     *
     * @param {Object} options - Component options
     * @param {HTMLElement} options.element - Parent container element
     * @param {Object} options.categoryData - Category data
     */
    create({element, categoryData}) {
        this.categoryData = categoryData;
        this.categoryElement = null;
        this.itemComponents = new Map();
        this.parentElement = element;

        // Note: _render() must be called explicitly when creating new categories.
        // When initializing from existing DOM, categoryElement is set externally.
    }

    /**
     * Get state watchers.
     *
     * @return {Array} Array of watcher definitions
     */
    getWatchers() {
        return [
            {watch: `items:created`, handler: this._handleItemCreated},
            {watch: `items.categoryid:updated`, handler: this._handleItemCategoryChanged},
            {watch: `items:deleted`, handler: this._handleItemDeleted},
        ];
    }

    /**
     * Render category (table view: tbody with category row).
     */
    async _render() {
        // Render category row.
        const categoryRowHtml = await Templates.render('mod_bookit/resource_category_row', this.categoryData);

        // Create tbody element for this category.
        const tbody = document.createElement('tbody');
        tbody.dataset.region = 'resource-category';
        tbody.dataset.categoryid = this.categoryData.id;
        tbody.dataset.categoryName = this.categoryData.name;
        tbody.dataset.categoryDescription = this.categoryData.description || '';
        tbody.dataset.categorySortorder = this.categoryData.sortorder || 0;
        tbody.innerHTML = categoryRowHtml.trim();

        this.categoryElement = tbody;
        this.parentElement.appendChild(this.categoryElement);

        this._renderItems();
        this._attachEventListeners();
    }

    /**
     * Render items in this category (table view: append rows to tbody).
     * Used when creating new items dynamically.
     */
    async _renderItems() {
        if (!this.categoryElement) {
            return;
        }

        // Clear existing item rows (keep category row).
        const categoryRow = this.categoryElement.querySelector('[data-region="resource-category-row"]');
        this.categoryElement.innerHTML = '';
        if (categoryRow) {
            this.categoryElement.appendChild(categoryRow);
        }
        this.itemComponents.clear();

        // Get items from state.
        const state = this.reactive.state;
        const items = Array.from(state.items.values())
            .filter(item => item.categoryid === this.categoryData.id)
            .sort((a, b) => a.sortorder - b.sortorder);

        // Render each item as a table row.
        for (const itemData of items) {
            const itemComponent = new ResourceItem({
                element: this.categoryElement,
                reactive: this.reactive,
                itemData: itemData,
            });
            // Explicitly call render for dynamically created items.
            await itemComponent._render();
            this.itemComponents.set(itemData.id, itemComponent);
        }
    }

    /**
     * Initialize item components from existing DOM.
     * Called when attaching to pre-rendered HTML.
     */
    _initItemsFromDOM() {
        if (!this.categoryElement) {
            return;
        }

        const state = this.reactive.state;
        const itemRows = this.categoryElement.querySelectorAll('tr[data-itemid]');

        itemRows.forEach(itemRow => {
            const itemId = parseInt(itemRow.dataset.itemid);
            const itemData = state.items.get(itemId);

            if (itemData && itemData.categoryid === this.categoryData.id) {
                const itemComponent = new ResourceItem({
                    element: this.categoryElement,
                    reactive: this.reactive,
                    itemData: itemData,
                });
                // Set existing DOM element instead of rendering.
                itemComponent.itemElement = itemRow;
                itemComponent._attachEventListeners();

                this.itemComponents.set(itemData.id, itemComponent);
            }
        });
    }

    /**
     * Handle item created.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - New item data
     */
    _handleItemCreated({element}) {
        if (element.categoryid === this.categoryData.id) {
            this._renderItems();
        }
    }

    /**
     * Handle item category changed (item moved between categories).
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated item data
     */
    _handleItemCategoryChanged({element}) {
        // Check if item moved to/from this category.
        const hadItem = this.itemComponents.has(element.id);
        const shouldHaveItem = element.categoryid === this.categoryData.id;

        if (hadItem !== shouldHaveItem) {
            // Item moved - re-render.
            this._renderItems();
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
            component.remove();
            this.itemComponents.delete(element.id);
        }
    }

    /**
     * Attach event listeners.
     */
    _attachEventListeners() {
        if (!this.categoryElement) {
            return;
        }

        // Add Item.
        const addBtn = this.categoryElement.querySelector('[data-action="add-item"]');
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._handleAddItem();
            });
        }

        // Edit Category.
        const editBtn = this.categoryElement.querySelector('[data-action="edit-category"]');
        if (editBtn) {
            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._handleEdit();
            });
        }
    }

    /**
     * Handle add item.
     */
    async _handleAddItem() {
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_resource_form',
            args: {
                categoryid: this.categoryData.id,
            },
            modalConfig: {
                title: await getString('resources:add_resource', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.dispatch('createItem', e.detail);
        });

        modalForm.show();
    }

    /**
     * Handle edit category.
     */
    async _handleEdit() {
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_resource_category_form',
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: this.categoryData.id,
            },
            modalConfig: {
                title: await getString('resources:edit_category', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.stateManager.processUpdates(e.detail);
        });

        modalForm.addEventListener(modalForm.events.LOADED, () => {
            const deleteButton = modalForm.modal.getRoot().find('button[data-action="delete"]');

            deleteButton.on('click', async(e) => {
                e.preventDefault();

                // Check if category has resources.
                const state = this.reactive.state;
                const resourceCount = Array.from(state.items.values())
                    .filter(item => item.categoryid === this.categoryData.id).length;

                if (resourceCount > 0) {
                    // Category has resources - show informative message.
                    const errorTitle = await getString('error', 'core');
                    const errorMessage = await getString('resources:category_has_resources', 'mod_bookit');

                    Notification.alert(
                        errorTitle,
                        errorMessage
                    );
                    return;
                }

                // No resources - proceed with delete confirmation.
                const confirmTitle = await getString('confirm', 'core');
                const confirmMessage = await getString('areyousure', 'core');
                const deleteText = await getString('delete', 'core');

                Notification.deleteCancel(
                    confirmTitle,
                    confirmMessage,
                    deleteText,
                    () => {
                        modalForm.getFormNode().querySelector('input[name="action"]').value = 'delete';
                        modalForm.submitFormAjax();
                    }
                );
            });
        });

        modalForm.show();
    }

    /**
     * Update with new data (re-render category row).
     *
     * @param {Object} newData - Updated category data
     */
    update(newData) {
        this.categoryData = newData;
        // Re-render the category row.
        const categoryRow = this.categoryElement.querySelector('[data-region="resource-category-row"]');
        if (categoryRow) {
            Templates.render('mod_bookit/resource_category_row', newData)
                .then(html => {
                    const wrapper = document.createElement('tbody');
                    wrapper.innerHTML = html.trim();
                    const newRow = wrapper.firstChild;
                    categoryRow.parentNode.replaceChild(newRow, categoryRow);
                    this._attachEventListeners();
                    return true;
                })
                .catch(error => {
                    window.console.error('Error updating category row:', error);
                });
        }
    }

    /**
     * Remove from DOM.
     */
    remove() {
        this.itemComponents.forEach(component => component.remove());
        this.itemComponents.clear();

        if (this.categoryElement && this.categoryElement.parentNode) {
            this.categoryElement.parentNode.removeChild(this.categoryElement);
        }
    }
}
