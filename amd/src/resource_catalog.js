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
 * Resource catalog reactive component.
 *
 * @module mod_bookit/resource_catalog
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {resourceReactiveInstance, initResourceReactive} from './resource_reactive';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';
import ResourceCategory from './resource_category';

/**
 * Resource catalog component.
 */
export default class extends BaseComponent {
    /**
     * Static init method.
     *
     * @param {string} target - CSS selector for container element
     * @return {ResourceCatalog} Component instance
     */
    static init(target) {
        // Get container element.
        const element = document.querySelector(target);
        if (!element) {
            return null;
        }

        // Read contextId from DOM.
        const contextId = parseInt(element.dataset.contextid);

        // Read categories and resources from DOM.
        const categoriesArray = [];
        const itemsArray = [];

        const categoryElements = element.querySelectorAll('[data-region="resource-category"]');
        categoryElements.forEach(categoryEl => {
            const categoryRow = categoryEl.querySelector('[data-region="resource-category-row"]');
            const categoryData = {
                id: parseInt(categoryEl.dataset.categoryid),
                name: categoryEl.dataset.categoryName || categoryRow?.textContent.trim(),
                description: categoryEl.dataset.categoryDescription || '',
                sortorder: parseInt(categoryEl.dataset.categorySortorder) || 0,
            };
            categoriesArray.push(categoryData);

            // Read resources for this category.
            const itemElements = categoryEl.querySelectorAll('[data-region="resource-item-row"]');
            itemElements.forEach(itemEl => {
                const itemData = {
                    id: parseInt(itemEl.dataset.itemid),
                    name: itemEl.dataset.itemName || '',
                    description: itemEl.dataset.itemDescription || '',
                    categoryid: parseInt(itemEl.dataset.itemCategoryid),
                    amount: parseInt(itemEl.dataset.itemAmount) || 0,
                    amountirrelevant: itemEl.dataset.itemAmountirrelevant === '1',
                    active: itemEl.dataset.itemActive === '1',
                    sortorder: parseInt(itemEl.dataset.itemSortorder) || 0,
                };
                itemsArray.push(itemData);
            });
        });

        // Initialize reactive store.
        initResourceReactive({
            categories: categoriesArray,
            items: itemsArray,
        });

        return new this({
            element: element,
            reactive: resourceReactiveInstance,
            selectors: {contextId},
        });
    }

    /**
     * Component descriptor for debugging.
     *
     * @return {string} Component name
     */
    static get componentName() {
        return 'mod_bookit/resource_catalog';
    }

    /**
     * Create component.
     *
     * Called by BaseComponent constructor.
     */
    create() {
        this.selectors.addCategoryBtn = '#add-category-btn';
        this.selectors.addResourceBtn = '#add-resource-btn';
        this.selectors.tableView = '#mod-bookit-resource-table-view';
        this.categoryComponents = new Map();
    }

    /**
     * Initial state ready.
     *
     * Called when reactive state is ready.
     */
    stateReady() {
        this._initializeCategoryComponents();
        this._attachEventListeners();
    }

    /**
     * Get state watchers.
     *
     * @return {Array} Array of watcher definitions
     */
    getWatchers() {
        return [
            {watch: `categories:created`, handler: this._handleCategoryCreated},
            {watch: `categories.name:updated`, handler: this._handleCategoryUpdated},
            {watch: `categories.description:updated`, handler: this._handleCategoryUpdated},
            {watch: `categories:deleted`, handler: this._handleCategoryDeleted},
            {watch: `items:created`, handler: this._handleItemCreated},
            {watch: `items.active:updated`, handler: this._handleActiveToggle},
            {watch: `items.name:updated`, handler: this._replaceRenderedItem},
            {watch: `items.description:updated`, handler: this._replaceRenderedItem},
            {watch: `items.amount:updated`, handler: this._replaceRenderedItem},
            {watch: `items.amountirrelevant:updated`, handler: this._replaceRenderedItem},
        ];
    }

    /**
     * Handle category created.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - New category data
     */
    _handleCategoryCreated({element}) {
        this._renderCategory(element);
    }

    /**
     * Handle category updated.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated category data
     */
    _handleCategoryUpdated({element}) {
        const component = this.categoryComponents.get(element.id);
        if (component) {
            component.update(element);
        }
    }

    /**
     * Handle category deleted.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Deleted category data
     */
    _handleCategoryDeleted({element}) {
        const component = this.categoryComponents.get(element.id);
        if (component) {
            component.remove();
            this.categoryComponents.delete(element.id);
        }
    }

    /**
     * Handle item created.
     *
     * Item creation is handled by the category component.
     */
    _handleItemCreated() {
        // Category component handles this via its watchers.
    }

    /**
     * Handle active state toggle.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated item data
     */
    _handleActiveToggle({element}) {
        const checkbox = document.querySelector(`#resource-active-${element.id}`);
        if (!checkbox) {
            window.console.warn(`Toggle checkbox not found for item ${element.id}`);
            return;
        }
        checkbox.checked = element.active;

        const label = document.querySelector(`label[for="resource-active-${element.id}"]`);
        if (label) {
            label.textContent = element.active ? 'Active' : 'Inactive';
        }

        const row = document.querySelector(`#resource-item-row-${element.id}`);
        if (row) {
            row.classList.toggle('opacity-50', !element.active);
        }
    }

    /**
     * Replace rendered item field (follows masterchecklist pattern).
     *
     * @param {Object} event - Event object
     */
    _replaceRenderedItem(event) {
        const actionParts = event.action.split('.');
        const fieldPart = actionParts[1].split(':')[0];
        const item = this.reactive.state.items.get(event.element.id);

        if (fieldPart === 'amount' || fieldPart === 'amountirrelevant') {
            const amountCell = document.querySelector(`td[data-bookit-resource-tabledata-amount-id="${item.id}"]`);
            if (item.amountirrelevant) {
                amountCell.innerHTML = '<span class="badge badge-secondary">Unlimited</span>';
            } else {
                amountCell.innerHTML = `<span class="badge badge-info">${item.amount}x</span>`;
            }
        } else if (fieldPart === 'name') {
            const nameSpan = document.querySelector(`span[data-bookit-resource-tabledata-name-id="${item.id}"]`);
            nameSpan.textContent = item.name;
        } else if (fieldPart === 'description') {
            const descSpan = document.querySelector(`small[data-bookit-resource-tabledata-description-id="${item.id}"]`);
            descSpan.innerHTML = item.description || '';
        }
    }

    /**
     * Attach event listeners.
     */
    _attachEventListeners() {
        const addBtn = document.querySelector(this.selectors.addCategoryBtn);
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._handleAddCategory();
            });
        }

        const addResourceBtn = document.querySelector(this.selectors.addResourceBtn);
        if (addResourceBtn) {
            addResourceBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._handleAddResource();
            });
        }

        // Table view button event delegation.
        this._attachTableViewEventListeners();
    }

    /**
     * Attach event listeners for table view buttons (event delegation).
     */
    _attachTableViewEventListeners() {
        const tableView = document.querySelector(this.selectors.tableView);
        if (!tableView) {
            return;
        }

        // Event delegation for all buttons in table view.
        tableView.addEventListener('click', async(e) => {
            const target = e.target.closest('button[data-action]');
            if (!target) {
                // Check for toggle switches.
                const toggle = e.target.closest('[data-action="toggle-active"]');
                if (toggle) {
                    e.preventDefault();
                    await this._handleToggleActive(toggle);
                }
                return;
            }

            const action = target.dataset.action;
            e.preventDefault();

            switch (action) {
                case 'edit-category':
                    await this._handleEditCategoryFromTable(target);
                    break;
                case 'edit-item':
                    await this._handleEditItemFromTable(target);
                    break;
            }
        });
    }

    /**
     * Handle edit category from table view.
     *
     * @param {HTMLElement} button - Button element
     */
    async _handleEditCategoryFromTable(button) {
        const categoryId = parseInt(button.dataset.categoryId);
        const state = this.reactive.state;
        const category = state.categories.get(categoryId);

        if (!category) {
            return;
        }

        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_resource_category_form',
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: categoryId,
            },
            modalConfig: {
                title: await getString('resources:edit_category', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
        });

        modalForm.addEventListener(modalForm.events.LOADED, () => {
            const deleteButton = modalForm.modal.getRoot().find('button[data-action="delete"]');

            deleteButton.on('click', async(e) => {
                e.preventDefault();

                // Check if category has resources.
                const state = this.reactive.state;
                const resourceCount = Array.from(state.items.values())
                    .filter(item => item.categoryid === categoryId).length;

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
     * Handle edit item from table view.
     *
     * @param {HTMLElement} button - Button element
     */
    async _handleEditItemFromTable(button) {
        const itemId = parseInt(button.dataset.itemId);
        const state = this.reactive.state;
        const item = state.items.get(itemId);

        if (!item) {
            return;
        }

        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_resource_form',
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: itemId,
            },
            modalConfig: {
                title: await getString('resources:edit_resource', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
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
     * Handle add category action.
     */
    async _handleAddCategory() {
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_resource_category_form',
            args: {
                contextid: this.selectors.contextId,
            },
            modalConfig: {
                title: await getString('resources:add_category', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
        });

        modalForm.show();
    }

    /**
     * Handle add resource button click.
     */
    async _handleAddResource() {
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_resource_form',
            args: {
                contextid: this.selectors.contextId,
            },
            modalConfig: {
                title: await getString('resources:add_resource', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
        });

        modalForm.show();
    }

    /**
     * Handle toggle active switch.
     *
     * @param {HTMLElement} checkbox - Toggle checkbox element
     */
    async _handleToggleActive(checkbox) {
        const itemId = parseInt(checkbox.dataset.itemId);
        const state = this.reactive.state;
        const item = state.items.get(itemId);

        if (!item) {
            return;
        }

        // Get the new active state from the checkbox.
        const newActiveState = checkbox.checked;

        // Build form data object.
        const formData = {
            id: itemId,
            name: item.name,
            description: item.description || '',
            categoryid: item.categoryid,
            amount: item.amount,
            amountirrelevant: item.amountirrelevant ? 1 : 0,
            sortorder: item.sortorder,
            active: newActiveState ? 1 : 0,
            roomids: item.roomids || [],
            action: 'put',
            [`_qf__mod_bookit_form_edit_resource_form`]: 1
        };

        // Convert to URL-encoded string.
        const formDataString = new URLSearchParams(formData).toString();

        // Submit via Ajax.
        Ajax.call([{
            methodname: 'core_form_dynamic_form',
            args: {
                formdata: formDataString,
                form: 'mod_bookit\\form\\edit_resource_form'
            }
        }])[0]
        .then((response) => {
            this.reactive.stateManager.processUpdates(JSON.parse(response.data));
            return;
        })
        .catch((error) => {
            // Revert checkbox on error.
            checkbox.checked = !newActiveState;
            window.console.error('Toggle active error:', error);
        });
    }

    /**
     * Show no categories message.
     */
    _showNoCategoriesMessage() {
        const msg = document.querySelector(this.selectors.noCategoriesMsg);
        if (msg) {
            msg.classList.remove('d-none');
        }
    }

    /**
     * Hide no categories message.
     */
    _hideNoCategoriesMessage() {
        const msg = document.querySelector(this.selectors.noCategoriesMsg);
        if (msg) {
            msg.classList.add('d-none');
        }
    }

    /**
     * Initialize category components from existing DOM.
     */
    _initializeCategoryComponents() {
        const categoryElements = this.element.querySelectorAll('[data-region="resource-category"]');
        const state = this.reactive.state;

        categoryElements.forEach(categoryEl => {
            const categoryId = parseInt(categoryEl.dataset.categoryid);
            const category = state.categories.get(categoryId);
            if (category) {
                // Component attaches to existing DOM element (no rendering).
                const component = new ResourceCategory({
                    element: this.element.querySelector('#mod-bookit-resource-table'),
                    reactive: this.reactive,
                    categoryData: category,
                });
                // Set existing DOM element.
                component.categoryElement = categoryEl;
                component._attachEventListeners();
                // Initialize item components from existing DOM.
                component._initItemsFromDOM();

                this.categoryComponents.set(categoryId, component);
            }
        });
    }

    /**
     * Render a new category component.
     *
     * @param {Object} categoryData - Category data
     */
    _renderCategory(categoryData) {
        const tableEl = this.element.querySelector('#mod-bookit-resource-table');
        if (!tableEl) {
            return;
        }

        const component = new ResourceCategory({
            element: tableEl,
            reactive: this.reactive,
            categoryData: categoryData,
        });

        this.categoryComponents.set(categoryData.id, component);
    }
}
