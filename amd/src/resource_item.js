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
 * @module mod_bookit/resource_item
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import Templates from 'core/templates';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

/**
 * Resource item component.
 */
export default class ResourceItem extends BaseComponent {
    /**
     * Component descriptor for debugging.
     *
     * @return {string} Component name
     */
    static get componentName() {
        return 'mod_bookit/resource_item';
    }

    /**
     * Create component.
     *
     * @param {Object} options - Component options
     * @param {HTMLElement} options.element - Parent container element
     * @param {Object} options.itemData - Item data
     */
    create({element, itemData}) {
        this.itemData = itemData;
        this.itemElement = null;
        this.parentElement = element;

        // Render immediately.
        this._render();
    }

    /**
     * Get state watchers.
     *
     * Resource items don't need state watchers -
     * parent category handles item changes.
     *
     * @return {Array} Empty array
     */
    getWatchers() {
        return [];
    }

    /**
     * Render item.
     */
    async _render() {
        const html = await Templates.render('mod_bookit/resource_item_card', this.itemData);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        this.itemElement = wrapper.firstChild;

        this.parentElement.appendChild(this.itemElement);

        this._attachEventListeners();
    }

    /**
     * Attach event listeners.
     */
    _attachEventListeners() {
        if (!this.itemElement) {
            return;
        }

        // Edit Item.
        const editBtn = this.itemElement.querySelector('[data-action="edit-item"]');
        if (editBtn) {
            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._handleEdit();
            });
        }
    }

    /**
     * Handle edit item.
     */
    async _handleEdit() {
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_resource_form',
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: this.itemData.id,
            },
            modalConfig: {
                title: await getString('resources:edit_resource', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.stateManager.processUpdates(e.detail);
        });

        modalForm.addEventListener(modalForm.events.LOADED, () => {
            const deleteButton = modalForm.modal.getRoot().find('button[data-action="delete"]');

            deleteButton.on('click', async(e) => {
                e.preventDefault();

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
     * Update with new data.
     *
     * @param {Object} newData - Updated item data
     */
    update(newData) {
        this.itemData = newData;
        // Update text if needed.
        const nameEl = this.itemElement.querySelector('h6');
        if (nameEl) {
            nameEl.textContent = newData.name;
        }
        const descEl = this.itemElement.querySelector('.text-muted');
        if (descEl) {
            descEl.textContent = newData.description || '';
        }
    }

    /**
     * Remove from DOM.
     */
    remove() {
        if (this.itemElement && this.itemElement.parentNode) {
            this.itemElement.parentNode.removeChild(this.itemElement);
        }
    }
}
