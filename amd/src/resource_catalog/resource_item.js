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
 * Resource item reactive component.
 *
 * @module mod_bookit/resource_catalog/resource_item
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent, DragDrop} from 'core/reactive';
import {getResourceReactive, SELECTORS} from 'mod_bookit/resource_catalog/resource_reactive';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

export default class ResourceItem extends BaseComponent {

    create(descriptor) {
        const itemId = descriptor.element.dataset.bookitItemId;
        const itemEditBtnSelector = this._getEditButtonSelector(itemId);
        this.selectors[itemEditBtnSelector] = `#edit-item-${itemId}`;
    }

    static init(target, selectors) {
        return new this({
            element: document.querySelector(target),
            reactive: getResourceReactive(),
            selectors: selectors || SELECTORS,
        });
    }

    getWatchers() {
        return [];
    }

    /**
     * Get the selector key for the edit button of a specific item.
     *
     * @param {string} itemId The item ID
     * @returns {string} The selector key
     */
    _getEditButtonSelector(itemId) {
        return 'EDIT_ITEM_BTN_' + itemId;
    }

    stateReady() {
        this.dragdrop = new DragDrop(this);

        const itemId = this.element.dataset.bookitItemId;
        const itemEditBtnSelector = this._getEditButtonSelector(itemId);

        this.addEventListener(this.getElement(this.selectors[itemEditBtnSelector]), 'click', (e) => {
            e.preventDefault();
            this._handleEdit(e);
        });
    }

    async _handleEdit(event) {
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\local\\form\\resource\\edit_resource_form',
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: event.currentTarget.value,
            },
            modalConfig: {
                title: await getString('resources:edit_resource', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);

            if (response.detail[0].action === 'delete') {
                this.reactive.dispatch('resourceDeleted', {id: parseInt(response.detail[0].fields.id)});
                this.remove();
                return;
            }
        });

        modalForm.show();
    }

    remove() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }

    destroy() {
        if (this.dragdrop !== undefined) {
            this.dragdrop.unregister();
        }
    }

    getDraggableData() {
        return {
            type: 'resource-item',
            id: parseInt(this.element.dataset.bookitItemId),
            parentId: parseInt(this.element.dataset.itemCategoryid),
        };
    }

    validateDropData(dropdata) {
        return dropdata?.type === 'resource-item';
    }

    showDropZone() {
        const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#0f6cbf';
        this.element.style.boxShadow = `0px -5px 0px 0px ${primary} inset`;
        this.element.style.transition = 'box-shadow 0.1s ease';
    }

    hideDropZone() {
        this.element.style.boxShadow = '';
        this.element.style.transition = '';
    }

    drop(dropdata) {
        if (dropdata.parentId !== parseInt(this.element.dataset.itemCategoryid)) {
            return;
        }
        dropdata.targetId = parseInt(this.element.dataset.bookitItemId);

        const draggedEl = document.getElementById(`resource-item-row-${dropdata.id}`);
        if (draggedEl && draggedEl !== this.element) {
            this.element.parentNode.insertBefore(draggedEl, this.element);
        }

        this.reactive.dispatch('reOrderItems', dropdata);
    }
}
