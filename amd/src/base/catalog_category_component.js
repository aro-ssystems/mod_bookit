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
 * Base component for catalog categories.
 *
 * @module mod_bookit/base/catalog_category_component
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

/**
 * Base class for catalog category components.
 */
export default class CatalogCategoryComponent {
    /**
     * Constructor.
     *
     * @param {Object} reactive - Reactive store instance
     * @param {Object} categoryData - Category data from state
     * @param {Function} ItemComponentClass - Item component class constructor
     */
    constructor(reactive, categoryData, ItemComponentClass) {
        this.reactive = reactive;
        this.categoryData = categoryData;
        this.ItemComponentClass = ItemComponentClass;
        this.element = null;
        this.itemComponents = new Map();
    }

    /**
     * Render the category to a DOM element.
     *
     * @return {Promise<HTMLElement>} Rendered element
     */
    async render() {
        const context = this.getTemplateContext();
        const html = await Templates.render(this.getTemplateName(), context);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        this.element = wrapper.firstChild;

        await this.renderItems();
        this.attachEventListeners();
        this.attachDragDropListeners();

        return this.element;
    }

    /**
     * Get template name for rendering.
     *
     * ABSTRACT - Must be implemented by subclass.
     *
     * @return {string} Template name
     */
    getTemplateName() {
        throw new Error('getTemplateName() must be implemented by subclass');
    }

    /**
     * Get template context data.
     *
     * @return {Object} Context for template
     */
    getTemplateContext() {
        return {
            ...this.categoryData,
            resources: [], // Empty - JS renders.
        };
    }

    /**
     * Get the form class name for editing category.
     *
     * ABSTRACT - Must be implemented by subclass.
     *
     * @return {string} Form class name
     */
    getEditModalFormClass() {
        throw new Error('getEditModalFormClass() must be implemented by subclass');
    }

    /**
     * Render items in this category.
     */
    async renderItems() {
        if (!this.element) {
            return;
        }

        const itemsContainer = this.element.querySelector('[data-region="items-container"]');
        if (!itemsContainer) {
            return;
        }

        // Clear existing items.
        itemsContainer.innerHTML = '';
        this.itemComponents.clear();

        // Get items from reactive state.
        const state = this.reactive.state;
        const items = Array.from(state.items.values())
            .filter(item => item.categoryid === this.categoryData.id)
            .sort((a, b) => a.sortorder - b.sortorder);

        // Render each item.
        for (const itemData of items) {
            const itemComponent = new this.ItemComponentClass(this.reactive, itemData);
            const itemElement = await itemComponent.render();
            itemsContainer.appendChild(itemElement);
            this.itemComponents.set(itemData.id, itemComponent);
        }

        // Show/hide no items message.
        this.updateNoItemsMessage(items.length === 0);
    }

    /**
     * Update no items message visibility.
     *
     * @param {boolean} show - Whether to show the message
     */
    updateNoItemsMessage(show) {
        const noItemsMsg = this.element.querySelector('[data-region="no-items"]');
        if (noItemsMsg) {
            if (show) {
                noItemsMsg.classList.remove('d-none');
            } else {
                noItemsMsg.classList.add('d-none');
            }
        }
    }

    /**
     * Attach event listeners.
     */
    attachEventListeners() {
        if (!this.element) {
            return;
        }

        // Add Item button.
        const addItemBtn = this.element.querySelector('[data-action="add-item"]');
        if (addItemBtn) {
            addItemBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleAddItem();
            });
        }

        // Edit Category button.
        const editBtn = this.element.querySelector('[data-action="edit-category"]');
        if (editBtn) {
            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleEdit();
            });
        }

        // Delete Category button.
        const deleteBtn = this.element.querySelector('[data-action="delete-category"]');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleDelete();
            });
        }
    }

    /**
     * Attach drag and drop listeners.
     */
    attachDragDropListeners() {
        if (!this.element) {
            return;
        }

        // Category header draggable.
        const header = this.element.querySelector('[data-region="category-header"]');
        if (header) {
            header.setAttribute('draggable', 'true');
            header.classList.add('draggable-category');

            header.addEventListener('dragstart', (e) => {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('application/x-bookit-category-id', this.categoryData.id);
                this.element.classList.add('dragging');
            });

            header.addEventListener('dragend', () => {
                this.element.classList.remove('dragging');
            });

            header.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            header.addEventListener('drop', (e) => {
                e.preventDefault();
                const draggedCategoryId = parseInt(
                    e.dataTransfer.getData('application/x-bookit-category-id'),
                    10
                );
                if (draggedCategoryId && draggedCategoryId !== this.categoryData.id) {
                    this.handleReorder(draggedCategoryId, this.categoryData.id);
                }
            });
        }

        // Items container - allow drop from other categories.
        const itemsContainer = this.element.querySelector('[data-region="items-container"]');
        if (itemsContainer) {
            itemsContainer.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            itemsContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                const itemId = parseInt(e.dataTransfer.getData('application/x-bookit-item-id'), 10);
                if (itemId) {
                    // Move item to this category.
                    this.handleItemMovedToCategory(itemId);
                }
            });
        }
    }

    /**
     * Handle add item action.
     */
    async handleAddItem() {
        const ItemComponentClass = this.ItemComponentClass;
        const instance = new ItemComponentClass(this.reactive, {categoryid: this.categoryData.id});
        const modalForm = new ModalForm({
            formClass: instance.getEditModalFormClass(),
            args: {
                id: 0,
                categoryid: this.categoryData.id,
            },
            modalConfig: {
                title: await this.getAddItemModalTitle(),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.dispatch('createItem', e.detail);
        });

        modalForm.show();
    }

    /**
     * Get add item modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getAddItemModalTitle() {
        return getString('resources:add_resource', 'mod_bookit');
    }

    /**
     * Handle edit category action.
     */
    async handleEdit() {
        const modalForm = new ModalForm({
            formClass: this.getEditModalFormClass(),
            args: {
                id: this.categoryData.id,
            },
            modalConfig: {
                title: await this.getEditModalTitle(),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.dispatch('updateCategory', e.detail);
        });

        modalForm.show();
    }

    /**
     * Get edit modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getEditModalTitle() {
        return getString('resources:edit_category', 'mod_bookit');
    }

    /**
     * Handle delete category action.
     */
    async handleDelete() {
        const message = await getString('resources:confirm_delete_category', 'mod_bookit', this.categoryData.name);
        // Use eslint-disable for window.confirm as it's acceptable in Moodle context.
        // eslint-disable-next-line no-alert
        const confirmed = window.confirm(message);

        if (confirmed) {
            this.reactive.dispatch('deleteCategory', this.categoryData.id);
        }
    }

    /**
     * Handle category reorder.
     *
     * @param {number} draggedCategoryId - ID of dragged category
     * @param {number} targetCategoryId - ID of target category
     */
    handleReorder(draggedCategoryId, targetCategoryId) {
        this.reactive.dispatch('reorderCategory', {
            categoryId: draggedCategoryId,
            targetCategoryId: targetCategoryId,
        });
    }

    /**
     * Handle item moved to this category.
     *
     * @param {number} itemId - ID of item
     */
    handleItemMovedToCategory(itemId) {
        const state = this.reactive.state;
        const item = state.items.get(itemId);

        if (item && item.categoryid !== this.categoryData.id) {
            // Update item's category.
            this.reactive.dispatch('updateItem', {
                ...item,
                categoryid: this.categoryData.id,
            });
        }
    }

    /**
     * Update the element with new data.
     *
     * @param {Object} newData - Updated category data
     */
    update(newData) {
        this.categoryData = newData;
        if (this.element && this.element.parentNode) {
            return this.render().then(newElement => {
                this.element.parentNode.replaceChild(newElement, this.element);
                return newElement;
            }).catch((error) => {
                window.console.error('Error updating category:', error);
                throw error;
            });
        }
        return Promise.resolve();
    }

    /**
     * Remove the element from DOM.
     */
    remove() {
        // Remove all item components.
        this.itemComponents.forEach(component => component.remove());
        this.itemComponents.clear();

        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }
}
