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
 * Base component for catalog items.
 *
 * @module mod_bookit/base/catalog_item_component
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

/**
 * Base class for catalog item components.
 *
 * Abstract class - must be extended by concrete implementations.
 */
export default class CatalogItemComponent {
    /**
     * Constructor.
     *
     * @param {Object} reactive - Reactive store instance
     * @param {Object} itemData - Item data from state
     */
    constructor(reactive, itemData) {
        this.reactive = reactive;
        this.itemData = itemData;
        this.element = null;
    }

    /**
     * Render the item to a DOM element.
     *
     * @return {Promise<HTMLElement>} Rendered element
     */
    async render() {
        const context = this.getTemplateContext();
        const html = await Templates.render(this.getTemplateName(), context);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        this.element = wrapper.firstChild;

        this.attachEventListeners();
        this.attachDragDropListeners();

        return this.element;
    }

    /**
     * Get template name for rendering.
     *
     * ABSTRACT - Must be implemented by subclass.
     *
     * @return {string} Template name (e.g., 'mod_bookit/resource_item_card')
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
            ...this.itemData,
        };
    }

    /**
     * Get the form class name for editing.
     *
     * ABSTRACT - Must be implemented by subclass.
     *
     * @return {string} Form class name (e.g., 'mod_bookit\\form\\edit_resource_form')
     */
    getEditModalFormClass() {
        throw new Error('getEditModalFormClass() must be implemented by subclass');
    }

    /**
     * Attach event listeners to the element.
     */
    attachEventListeners() {
        if (!this.element) {
            return;
        }

        // Edit button.
        const editBtn = this.element.querySelector('[data-action="edit-item"]');
        if (editBtn) {
            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleEdit();
            });
        }

        // Delete button.
        const deleteBtn = this.element.querySelector('[data-action="delete-item"]');
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

        this.element.setAttribute('draggable', 'true');
        this.element.classList.add('draggable-item');

        this.element.addEventListener('dragstart', (e) => {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.itemData.id);
            e.dataTransfer.setData('application/x-bookit-item-id', this.itemData.id);
            this.element.classList.add('dragging');
        });

        this.element.addEventListener('dragend', () => {
            this.element.classList.remove('dragging');
        });

        this.element.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        this.element.addEventListener('drop', (e) => {
            e.preventDefault();
            const draggedItemId = parseInt(e.dataTransfer.getData('application/x-bookit-item-id'), 10);
            this.handleReorder(draggedItemId, this.itemData.id);
        });
    }

    /**
     * Handle edit action.
     */
    async handleEdit() {
        const modalForm = new ModalForm({
            formClass: this.getEditModalFormClass(),
            args: {
                id: this.itemData.id,
                categoryid: this.itemData.categoryid,
            },
            modalConfig: {
                title: await this.getEditModalTitle(),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            // Reactive store will handle update via mutation.
            this.reactive.dispatch('updateItem', e.detail);
        });

        modalForm.show();
    }

    /**
     * Get edit modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getEditModalTitle() {
        return getString('resources:edit_resource', 'mod_bookit');
    }

    /**
     * Handle delete action.
     */
    async handleDelete() {
        const message = await getString('resources:confirm_delete_resource', 'mod_bookit', this.itemData.name);
        // Use eslint-disable for window.confirm as it's acceptable in Moodle context.
        // eslint-disable-next-line no-alert
        const confirmed = window.confirm(message);

        if (confirmed) {
            this.reactive.dispatch('deleteItem', this.itemData.id);
        }
    }

    /**
     * Handle reorder action.
     *
     * @param {number} draggedItemId - ID of dragged item
     * @param {number} targetItemId - ID of target item
     */
    handleReorder(draggedItemId, targetItemId) {
        if (draggedItemId === targetItemId) {
            return;
        }

        this.reactive.dispatch('reorderItem', {
            itemId: draggedItemId,
            targetItemId: targetItemId,
            categoryId: this.itemData.categoryid,
        });
    }

    /**
     * Update the element with new data.
     *
     * @param {Object} newData - Updated item data
     */
    update(newData) {
        this.itemData = newData;
        // Re-render if needed.
        if (this.element && this.element.parentNode) {
            return this.render().then(newElement => {
                this.element.parentNode.replaceChild(newElement, this.element);
                return newElement;
            }).catch((error) => {
                window.console.error('Error updating item:', error);
                throw error;
            });
        }
        return Promise.resolve();
    }

    /**
     * Remove the element from DOM.
     */
    remove() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }
}
