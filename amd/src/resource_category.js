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

        // Render immediately.
        this._render();
    }

    /**
     * Get state watchers.
     *
     * @return {Array} Array of watcher definitions
     */
    getWatchers() {
        return [
            {watch: `items:created`, handler: this._handleItemCreated},
            {watch: `items:updated`, handler: this._handleItemUpdated},
            {watch: `items:deleted`, handler: this._handleItemDeleted},
        ];
    }

    /**
     * Render category.
     */
    async _render() {
        const context = {
            ...this.categoryData,
            resources: [], // JS renders items.
        };

        const html = await Templates.render('mod_bookit/resource_category_card', context);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        this.categoryElement = wrapper.firstChild;

        this.parentElement.appendChild(this.categoryElement);

        this._renderItems();
        this._attachEventListeners();
    }

    /**
     * Render items in this category.
     */
    _renderItems() {
        if (!this.categoryElement) {
            return;
        }

        const itemsContainer = this.categoryElement.querySelector('[data-region="items-container"]');
        if (!itemsContainer) {
            return;
        }

        // Clear existing.
        itemsContainer.innerHTML = '';
        this.itemComponents.clear();

        // Get items from state.
        const state = this.reactive.state;
        const items = Array.from(state.items.values())
            .filter(item => item.categoryid === this.categoryData.id)
            .sort((a, b) => a.sortorder - b.sortorder);

        // Render each item.
        items.forEach(itemData => {
            const itemComponent = new ResourceItem({
                element: itemsContainer,
                reactive: this.reactive,
                itemData: itemData,
            });
            this.itemComponents.set(itemData.id, itemComponent);
        });

        // Show/hide no items message.
        this._updateNoItemsMessage(items.length === 0);
    }

    /**
     * Update no items message visibility.
     *
     * @param {boolean} show - Whether to show
     */
    _updateNoItemsMessage(show) {
        const msg = this.categoryElement.querySelector('[data-region="no-items"]');
        if (msg) {
            if (show) {
                msg.classList.remove('d-none');
            } else {
                msg.classList.add('d-none');
            }
        }
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
     * Handle item updated.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated item data
     */
    _handleItemUpdated({element}) {
        // Check if item moved to/from this category.
        const hadItem = this.itemComponents.has(element.id);
        const shouldHaveItem = element.categoryid === this.categoryData.id;

        if (hadItem !== shouldHaveItem) {
            // Item moved - re-render.
            this._renderItems();
        } else if (shouldHaveItem) {
            // Item updated in place.
            const component = this.itemComponents.get(element.id);
            if (component) {
                component.update(element);
            }
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

            // Update no items message.
            const state = this.reactive.state;
            const remainingItems = Array.from(state.items.values())
                .filter(item => item.categoryid === this.categoryData.id);
            this._updateNoItemsMessage(remainingItems.length === 0);
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

        // Delete Category.
        const deleteBtn = this.categoryElement.querySelector('[data-action="delete-category"]');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._handleDelete();
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
            args: {
                id: this.categoryData.id,
            },
            modalConfig: {
                title: await getString('resources:edit_category', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.dispatch('updateCategory', e.detail);
        });

        modalForm.show();
    }

    /**
     * Handle delete category.
     */
    async _handleDelete() {
        const confirmMsg = await getString('resources:confirm_delete_category', 'mod_bookit', this.categoryData.name);
        const confirmed = window.confirm(confirmMsg);

        if (confirmed) {
            this.reactive.dispatch('deleteCategory', this.categoryData.id);
        }
    }

    /**
     * Update with new data.
     *
     * @param {Object} newData - Updated category data
     */
    update(newData) {
        this.categoryData = newData;
        // Update header text if needed.
        const header = this.categoryElement.querySelector('h5');
        if (header) {
            header.textContent = newData.name;
        }
        const desc = this.categoryElement.querySelector('.text-muted');
        if (desc) {
            desc.textContent = newData.description || '';
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
