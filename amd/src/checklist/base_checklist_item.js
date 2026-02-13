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
 * Base checklist item component.
 *
 * Abstract base class for item components providing common functionality:
 * - Edit/delete modal form handling
 * - Event listeners
 * - Config-driven specialization
 *
 * @module mod_bookit/checklist/base_checklist_item
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import Templates from 'core/templates';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

/**
 * Base checklist item component.
 *
 * Specializations must implement:
 * - getConfig(): Return configuration object
 *
 * Optional overrides:
 * - _updateRow(itemData): Custom row update logic
 * - _getCustomWatchers(): Add custom state watchers
 */
export default class BaseChecklistItem extends BaseComponent {
    /**
     * Create component.
     *
     * @param {Object} descriptor - Component descriptor
     */
    create(descriptor) {
        // Initialize selectors object if it doesn't exist
        if (!this.selectors) {
            this.selectors = {};
        }

        const itemId = descriptor.element.dataset.itemid || descriptor.element.dataset.bookitItemId;
        if (itemId) {
            const itemEditBtnSelector = this._getEditButtonSelector(itemId);
            this.selectors[itemEditBtnSelector] = `#edit-item-${itemId}`;
        }

        // Store item data if provided
        this.itemData = descriptor.itemData;
    }

    /**
     * Get state watchers.
     *
     * @return {Array} Array of watcher definitions
     */
    getWatchers() {
        const customWatchers = this._getCustomWatchers ? this._getCustomWatchers() : [];
        return [...customWatchers];
    }

    /**
     * Get configuration object.
     *
     * Must be implemented by specializations.
     *
     * @return {Object} Configuration object with:
     *   - itemsStateKey: State key for items (default: 'items')
     *   - itemType: Name of item type (e.g., 'resource', 'checklistitem')
     *   - itemModalForm: Full class name for item modal form
     *   - templates: Object with template names
     */
    getConfig() {
        throw new Error('getConfig() must be implemented by specialization');
    }

    /**
     * Initialize state ready.
     */
    stateReady() {
        this._attachEventListeners();
    }

    /**
     * Get the selector key for the edit button.
     *
     * @param {string} itemId - The item ID
     * @returns {string} The selector key
     */
    _getEditButtonSelector(itemId) {
        return 'EDIT_ITEM_BTN_' + itemId;
    }

    /**
     * Attach event listeners.
     */
    _attachEventListeners() {
        // Edit button
        const itemId = this.element.dataset.itemid || this.element.dataset.bookitItemId;
        // eslint-disable-next-line no-console
        console.log('_attachEventListeners itemId:', itemId, 'element:', this.element);
        if (itemId) {
            const itemEditBtnSelector = this._getEditButtonSelector(itemId);
            const editBtn = this.getElement(this.selectors[itemEditBtnSelector]);
            // eslint-disable-next-line no-console
            console.log('_attachEventListeners editBtn:', editBtn, 'selector:', this.selectors[itemEditBtnSelector]);
            if (editBtn) {
                this.addEventListener(editBtn, 'click', this._handleEdit.bind(this));
                // eslint-disable-next-line no-console
                console.log('_attachEventListeners addEventListener attached for itemId:', itemId);
            }
        }

        // Allow specializations to add more listeners
        if (this._attachCustomEventListeners) {
            this._attachCustomEventListeners();
        }
    }

    /**
     * Handle edit button click.
     *
     * @param {Event} event - Click event
     */
    async _handleEdit(event) {
        // eslint-disable-next-line no-console
        console.log('_handleEdit called', {event, currentTarget: event.currentTarget});
        event.preventDefault();

        const config = this.getConfig();
        const itemId = event.currentTarget.value || event.currentTarget.dataset.itemid;
        // eslint-disable-next-line no-console
        console.log('_handleEdit itemId:', itemId, 'config:', config);

        const modalForm = new ModalForm({
            formClass: config.itemModalForm,
            args: {
                id: parseInt(itemId),
            },
            modalConfig: {
                title: await getString(`edit${config.itemType}`, 'mod_bookit'),
            },
            returnFocus: event.currentTarget,
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            const response = e.detail;

            // Handle processUpdates format (array of updates)
            if (Array.isArray(response)) {
                this.reactive.stateManager.processUpdates(response);

                // Check if item was deleted
                if (response[0]?.action === 'delete') {
                    this.reactive.dispatch('itemDeleted', {id: parseInt(response[0].fields.id)});
                    this.remove();
                    return;
                }
            } else if (response.result === 'success') {
                // Handle direct result format
                if (response.action === 'delete') {
                    this.reactive.dispatch('itemsDeleted', {fields: response.data});
                    this.remove();
                } else {
                    this.reactive.dispatch('itemsUpdated', {fields: response.data});
                }
            }
        });

        modalForm.show();
    }

    /**
     * Update row with new data.
     *
     * Can be overridden by specializations for custom update logic.
     *
     * @param {Object} itemData - Updated item data
     */
    _updateRow(itemData) {
        this.itemData = itemData;

        // Update dataset
        if (this.element) {
            this.element.dataset.itemName = itemData.name || '';
            this.element.dataset.itemDescription = itemData.description || '';
            this.element.dataset.itemSortorder = itemData.sortorder || 0;
        }

        // Update visible content - specializations should override this
        // for more specific updates
        const nameCell = this.element.querySelector('[data-field="item-name"]');
        if (nameCell) {
            nameCell.textContent = itemData.name;
        }

        const descCell = this.element.querySelector('[data-field="item-description"]');
        if (descCell) {
            descCell.textContent = itemData.description || '';
        }
    }

    /**
     * Remove element from DOM.
     */
    remove() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }

    /**
     * Render item (for dynamic creation).
     *
     * Can be overridden by specializations.
     */
    async _render() {
        const config = this.getConfig();
        const template = config.templates?.itemRow || 'mod_bookit/checklist_item_row';

        const html = await Templates.render(template, this.itemData);
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html.trim();
        const itemElement = tempDiv.firstElementChild;

        if (this.element.tagName === 'TBODY') {
            // Append to category tbody
            this.element.appendChild(itemElement);
        } else {
            // Replace element
            this.element.parentNode.replaceChild(itemElement, this.element);
        }

        this.element = itemElement;
        this._attachEventListeners();
    }
}
