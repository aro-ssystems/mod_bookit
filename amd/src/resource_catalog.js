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
import ResourceCategory from './resource_category';
import Templates from 'core/templates';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

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
            const categoryData = {
                id: parseInt(categoryEl.dataset.categoryid),
                name: categoryEl.dataset.categoryName,
                description: categoryEl.dataset.categoryDescription || '',
                sortorder: parseInt(categoryEl.dataset.categorySortorder) || 0,
            };
            categoriesArray.push(categoryData);

            // Read resources for this category.
            const itemElements = categoryEl.querySelectorAll('[data-region="resource-item"]');
            itemElements.forEach(itemEl => {
                const itemData = {
                    id: parseInt(itemEl.dataset.itemid),
                    name: itemEl.dataset.itemName,
                    description: itemEl.dataset.itemDescription || '',
                    categoryid: parseInt(itemEl.dataset.itemCategoryid),
                    amount: parseInt(itemEl.dataset.itemAmount) || 0,
                    amountirrelevant: itemEl.dataset.itemAmountirrelevant === 'true',
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
        this.selectors.categoriesContainer = '#mod-bookit-resource-categories';
        this.selectors.noCategoriesMsg = '#mod-bookit-no-categories';
        this.selectors.addCategoryBtn = '#add-category-btn';
        this.selectors.tableView = '#mod-bookit-resource-table-view';

        this.categoryComponents = new Map();
    }

    /**
     * Initial state ready.
     *
     * Called when reactive state is ready.
     *
     * @param {Object} state - Reactive state
     */
    stateReady(state) {
        this._initializeExistingComponents(state);
        this._attachEventListeners();
    }

    /**
     * Initialize components for existing DOM elements.
     *
     * @param {Object} state - Reactive state
     */
    _initializeExistingComponents(state) {
        const categories = Array.from(state.categories.values());

        categories.forEach(categoryData => {
            const categoryEl = document.getElementById(`resource-category-${categoryData.id}`);
            if (categoryEl) {
                const categoryComponent = new ResourceCategory({
                    element: categoryEl.parentElement,
                    reactive: this.reactive,
                    categoryData: categoryData,
                    existingElement: categoryEl,
                });
                this.categoryComponents.set(categoryData.id, categoryComponent);
            }
        });
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
            {watch: `items.name:updated`, handler: this._replaceRenderedItem},
            {watch: `items.description:updated`, handler: this._replaceRenderedItem},
            {watch: `items.amount:updated`, handler: this._replaceRenderedItem},
            {watch: `items.amountirrelevant:updated`, handler: this._replaceRenderedItem},
            {watch: `items.active:updated`, handler: this._replaceRenderedItem},
        ];
    }

    /**
     * Handle category created.
     */
    _handleCategoryCreated() {
        const state = this.reactive.state;
        this._renderCategories(state);
    }

    /**
     * Render all categories (for dynamic updates).
     *
     * @param {Object} state - Reactive state
     */
    _renderCategories(state) {
        const container = document.querySelector(this.selectors.categoriesContainer);
        if (!container) {
            return;
        }

        const categories = Array.from(state.categories.values())
            .sort((a, b) => a.sortorder - b.sortorder);

        // Clear existing.
        container.innerHTML = '';
        this.categoryComponents.clear();

        if (categories.length === 0) {
            this._showNoCategoriesMessage();
            return;
        }

        this._hideNoCategoriesMessage();

        // Render each category.
        categories.forEach(categoryData => {
            const categoryComponent = new ResourceCategory({
                element: container,
                reactive: this.reactive,
                categoryData: categoryData,
            });
            this.categoryComponents.set(categoryData.id, categoryComponent);
        });
    }

    /**
     * Handle category updated.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated category data
     */
    _handleCategoryUpdated({element}) {
        if (this.currentView === 'table') {
            // Table view: Re-render the table row
            const targetElement = document.getElementById(`resource-category-row-${element.id}`);
            if (targetElement) {
                Templates.renderForPromise('mod_bookit/resource_category_row', {
                    id: element.id,
                    name: element.name,
                    description: element.description || '',
                    sortorder: element.sortorder,
                    active: element.active,
                })
                .then(({html, js}) => {
                    return Templates.replaceNode(targetElement, html, js);
                })
                .catch(error => {
                    window.console.error('Error rendering resource category row:', error);
                });
            }
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

        const state = this.reactive.state;
        if (state.categories.size === 0) {
            this._showNoCategoriesMessage();
        }
    }

    /**
     * Handle item created.
     */
    _handleItemCreated() {
        // Re-render categories to show the new item
        const state = this.reactive.state;
        this._renderCategories(state);
    }

    /**
     * Replace rendered item field (follows checklist pattern).
     *
     * @param {Object} event - Event object
     */
    _replaceRenderedItem(event) {
        const actionParts = event.action.split('.');
        const fieldPart = actionParts[1].split(':')[0];

        if (fieldPart === 'amount' || fieldPart === 'amountirrelevant') {
            // Amount field requires template re-render due to conditional logic
            const targetElement = document.querySelector(`td[data-bookit-resource-tabledata-amount-id="${event.element.id}"]`);
            if (targetElement) {
                const state = this.reactive.state;
                const item = state.items.get(event.element.id);
                if (item.amountirrelevant) {
                    targetElement.innerHTML = '<span class="badge badge-secondary">Unlimited</span>';
                } else {
                    targetElement.innerHTML = `<span class="badge badge-info">${item.amount}x</span>`;
                }
            }
        } else if (fieldPart === 'active') {
            // Active field requires conditional badge rendering
            const targetElement = document.querySelector(`td[data-bookit-resource-tabledata-active-id="${event.element.id}"]`);
            if (targetElement) {
                const state = this.reactive.state;
                const item = state.items.get(event.element.id);
                if (item.active) {
                    targetElement.innerHTML = '<span class="badge badge-success">Active</span>';
                } else {
                    targetElement.innerHTML = '<span class="badge badge-secondary">Inactive</span>';
                }
            }
        } else {
            // Simple fields: directly update innerHTML
            const elementSelector = `span[data-bookit-resource-tabledata-${fieldPart}-id="${event.element.id}"]`;
            const targetElement = document.querySelector(elementSelector);
            if (targetElement) {
                targetElement.innerHTML = event.element[fieldPart];
            }
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

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.dispatch('createCategory', e.detail);
        });

        modalForm.show();
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
}
