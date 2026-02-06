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
     * @param {Object} selectors - Additional configuration
     * @param {number} selectors.contextId - System context ID
     * @param {string} selectors.categoriesJson - JSON string with categories data
     * @return {ResourceCatalog} Component instance
     */
    static init(target, selectors) {
        // Parse categories and initialize state.
        const categories = JSON.parse(selectors.categoriesJson);

        // Prepare state data as arrays.
        const categoriesArray = [];
        const itemsArray = [];

        categories.forEach(category => {
            categoriesArray.push({
                id: category.id,
                name: category.name,
                description: category.description,
                sortorder: category.sortorder,
            });

            if (category.resources && Array.isArray(category.resources)) {
                category.resources.forEach(resource => {
                    itemsArray.push({
                        id: resource.id,
                        name: resource.name,
                        description: resource.description,
                        categoryid: category.id,
                        amount: resource.amount,
                        amountirrelevant: resource.amountirrelevant,
                        sortorder: resource.sortorder,
                    });
                });
            }
        });

        // Initialize reactive store.
        initResourceReactive({
            categories: categoriesArray,
            items: itemsArray,
        });

        return new this({
            element: document.querySelector(target),
            reactive: resourceReactiveInstance,
            selectors,
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
        this.selectors.spinner = '#mod-bookit-resource-spinner';
        this.selectors.content = '#mod-bookit-resource-content';
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
        this._renderCategories(state);
        this._attachEventListeners();
        this._hideSpinner();
        this._showContent();
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
     * Render all categories.
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
     * Handle category created.
     */
    _handleCategoryCreated() {
        const state = this.reactive.state;
        this._renderCategories(state);
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
     * Hide spinner.
     */
    _hideSpinner() {
        const spinner = document.querySelector(this.selectors.spinner);
        if (spinner) {
            spinner.classList.add('d-none');
        }
    }

    /**
     * Show content.
     */
    _showContent() {
        const content = document.querySelector(this.selectors.content);
        if (content) {
            content.classList.remove('d-none');
            content.classList.add('d-block');
        }
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
