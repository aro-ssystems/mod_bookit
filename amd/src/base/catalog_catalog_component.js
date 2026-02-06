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
 * Base component for catalog container.
 *
 * @module mod_bookit/base/catalog_catalog_component
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

/**
 * Base class for catalog container component.
 */
export default class CatalogCatalogComponent {
    /**
     * Constructor.
     *
     * @param {Object} reactive - Reactive store instance
     * @param {Function} CategoryComponentClass - Category component class constructor
     */
    constructor(reactive, CategoryComponentClass) {
        this.reactive = reactive;
        this.CategoryComponentClass = CategoryComponentClass;
        this.containerElement = null;
        this.categoriesContainer = null;
        this.categoryComponents = new Map();
    }

    /**
     * Initialize the catalog.
     *
     * @param {string} containerId - Container element ID
     */
    async init(containerId) {
        this.containerElement = document.getElementById(containerId);

        if (!this.containerElement) {
            window.console.error('Catalog container not found:', containerId);
            return;
        }

        this.categoriesContainer = this.containerElement.querySelector('#mod-bookit-resource-categories');

        if (!this.categoriesContainer) {
            window.console.error('Categories container not found');
            return;
        }

        await this.registerWatchers();
        await this.renderCategories();
        this.attachEventListeners();
        this.hideSpinner();
        this.showContent();
    }

    /**
     * Register state watchers.
     */
    async registerWatchers() {
        // Watch for category changes.
        return this.reactive.stateReady.then(() => {
            const state = this.reactive.state;

            // Watch categories map.
            if (state.categories) {
                this.reactive.watch(() => state.categories, () => {
                    this.renderCategories();
                });
            }

            // Watch items map.
            if (state.items) {
                this.reactive.watch(() => state.items, () => {
                    this.renderCategories();
                });
            }
            return true;
        }).catch((error) => {
            window.console.error('Error registering watchers:', error);
            throw error;
        });
    }

    /**
     * Render all categories.
     */
    async renderCategories() {
        if (!this.categoriesContainer) {
            return;
        }

        const state = this.reactive.state;
        const categories = Array.from(state.categories.values())
            .sort((a, b) => a.sortorder - b.sortorder);

        // Clear existing.
        this.categoriesContainer.innerHTML = '';
        this.categoryComponents.clear();

        if (categories.length === 0) {
            this.showNoCategoriesMessage();
            return;
        }

        this.hideNoCategoriesMessage();

        // Render each category.
        for (const categoryData of categories) {
            const categoryComponent = new this.CategoryComponentClass(
                this.reactive,
                categoryData,
                this.getItemComponentClass()
            );
            const categoryElement = await categoryComponent.render();
            this.categoriesContainer.appendChild(categoryElement);
            this.categoryComponents.set(categoryData.id, categoryComponent);
        }
    }

    /**
     * Get item component class.
     *
     * ABSTRACT - Must be implemented by subclass.
     *
     * @return {Function} Item component class constructor
     */
    getItemComponentClass() {
        throw new Error('getItemComponentClass() must be implemented by subclass');
    }

    /**
     * Get the form class name for adding category.
     *
     * ABSTRACT - Must be implemented by subclass.
     *
     * @return {string} Form class name
     */
    getAddCategoryModalFormClass() {
        throw new Error('getAddCategoryModalFormClass() must be implemented by subclass');
    }

    /**
     * Attach event listeners.
     */
    attachEventListeners() {
        const addCategoryBtn = document.getElementById('add-category-btn');
        if (addCategoryBtn) {
            addCategoryBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleAddCategory();
            });
        }
    }

    /**
     * Handle add category action.
     */
    async handleAddCategory() {
        const modalForm = new ModalForm({
            formClass: this.getAddCategoryModalFormClass(),
            args: {
                id: 0,
            },
            modalConfig: {
                title: await this.getAddCategoryModalTitle(),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            this.reactive.dispatch('createCategory', e.detail);
        });

        modalForm.show();
    }

    /**
     * Get add category modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getAddCategoryModalTitle() {
        return getString('resources:add_category', 'mod_bookit');
    }

    /**
     * Hide spinner.
     */
    hideSpinner() {
        const spinner = document.getElementById('mod-bookit-resource-spinner');
        if (spinner) {
            spinner.classList.add('d-none');
        }
    }

    /**
     * Show content.
     */
    showContent() {
        const content = document.getElementById('mod-bookit-resource-content');
        if (content) {
            content.classList.remove('d-none');
        }
    }

    /**
     * Show no categories message.
     */
    showNoCategoriesMessage() {
        const msg = document.getElementById('mod-bookit-no-categories');
        if (msg) {
            msg.classList.remove('d-none');
        }
    }

    /**
     * Hide no categories message.
     */
    hideNoCategoriesMessage() {
        const msg = document.getElementById('mod-bookit-no-categories');
        if (msg) {
            msg.classList.add('d-none');
        }
    }
}
