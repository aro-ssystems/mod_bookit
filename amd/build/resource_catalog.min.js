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
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

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
            {watch: `categories:updated`, handler: this._handleCategoryUpdated},
            {watch: `categories:deleted`, handler: this._handleCategoryDeleted},
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

        const state = this.reactive.state;
        if (state.categories.size === 0) {
            this._showNoCategoriesMessage();
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
