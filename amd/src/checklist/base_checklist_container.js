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
 * Base checklist container component.
 *
 * Abstract base class for checklist-style components providing common functionality:
 * - Reactive state management
 * - Category/item hierarchy
 * - Modal form handling
 * - Event listeners and watchers
 * - Config-driven specialization
 *
 * @module mod_bookit/checklist/base_checklist_container
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

/**
 * Base checklist container component.
 *
 * Specializations must implement:
 * - getConfig(): Return configuration object
 * - getCategoryComponent(): Return category component class
 *
 * Optional overrides:
 * - _getInitialData(element): Extract initial data from DOM
 * - _initializeCustomControls(): Add custom UI controls
 * - _getCustomWatchers(): Add custom state watchers
 */
export default class BaseChecklistContainer extends BaseComponent {
    /**
     * Create component.
     *
     * Called by BaseComponent constructor.
     */
    create() {
        const config = this.getConfig();

        // Initialize selectors
        this.selectors = {
            addCategoryBtn: config.selectors?.addCategoryBtn || '#add-category-btn',
            addItemBtn: config.selectors?.addItemBtn || '#add-item-btn',
            tableView: config.selectors?.tableView || '.mod-bookit-table-view',
            ...config.selectors || {}
        };

        // Initialize component registry
        this.categoryComponents = new Map();

        // Cache localized strings
        this.strings = {};
        this._cacheStrings();
    }

    /**
     * Initial state ready.
     *
     * Called when reactive state is ready.
     */
    stateReady() {
        this._initializeCategoryComponents();
        this._attachEventListeners();
        if (this._initializeCustomControls) {
            this._initializeCustomControls();
        }
    }

    /**
     * Get state watchers.
     *
     * @return {Array} Array of watcher definitions
     */
    getWatchers() {
        const config = this.getConfig();
        const categoriesKey = config.categoriesStateKey || 'categories';
        const itemsKey = config.itemsStateKey || 'items';

        const baseWatchers = [
            {watch: `${categoriesKey}:created`, handler: this._handleCategoryCreated},
            {watch: `${categoriesKey}.name:updated`, handler: this._handleCategoryUpdated},
            {watch: `${categoriesKey}.description:updated`, handler: this._handleCategoryUpdated},
            {watch: `${categoriesKey}:deleted`, handler: this._handleCategoryDeleted},
            {watch: `${itemsKey}:created`, handler: this._handleItemCreated},
            {watch: `${itemsKey}.name:updated`, handler: this._replaceRenderedItem},
            {watch: `${itemsKey}.description:updated`, handler: this._replaceRenderedItem},
        ];

        // Allow specializations to add custom watchers
        if (this._getCustomWatchers) {
            return [...baseWatchers, ...this._getCustomWatchers()];
        }

        return baseWatchers;
    }

    /**
     * Get configuration object.
     *
     * Must be implemented by specializations.
     *
     * @return {Object} Configuration object with:
     *   - categoriesStateKey: State key for categories (default: 'categories')
     *   - itemsStateKey: State key for items (default: 'items')
     *   - itemType: Name of item type (e.g., 'resource', 'checklistitem')
     *   - categoryModalForm: Full class name for category modal form
     *   - itemModalForm: Full class name for item modal form
     *   - selectors: Optional custom selectors
     */
    getConfig() {
        throw new Error('getConfig() must be implemented by specialization');
    }

    /**
     * Get category component class.
     *
     * Must be implemented by specializations.
     *
     * @return {Class} Category component class
     */
    getCategoryComponent() {
        throw new Error('getCategoryComponent() must be implemented by specialization');
    }

    /**
     * Initialize category components.
     */
    _initializeCategoryComponents() {
        const config = this.getConfig();
        const categoriesKey = config.categoriesStateKey || 'categories';
        const categories = this.reactive.state[categoriesKey];

        if (!categories) {
            return;
        }

        categories.forEach((category, categoryId) => {
            const categoryElement = this.getElement(`[data-region="checklist-category"][data-categoryid="${categoryId}"]`);
            if (categoryElement) {
                const CategoryComponent = this.getCategoryComponent();
                const instance = new CategoryComponent({
                    element: categoryElement,
                    reactive: this.reactive,
                });
                this.categoryComponents.set(categoryId, instance);
            }
        });
    }

    /**
     * Attach event listeners.
     */
    _attachEventListeners() {
        // Add category button
        const addCategoryBtn = this.getElement(this.selectors.addCategoryBtn);
        if (addCategoryBtn) {
            this.addEventListener(addCategoryBtn, 'click', this._handleAddCategoryClick.bind(this));
        }

        // Add item button
        const addItemBtn = this.getElement(this.selectors.addItemBtn);
        if (addItemBtn) {
            this.addEventListener(addItemBtn, 'click', this._handleAddItemClick.bind(this));
        }
    }

    /**
     * Handle category created.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - New category data
     */
    async _handleCategoryCreated({element}) {
        await this._renderCategory(element);
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
            component._updateCategoryRow(element);
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
            component.unregister();
            this.categoryComponents.delete(element.id);
        }

        const categoryElement = this.getElement(
            `[data-region="checklist-category"][data-categoryid="${element.id}"]`
        );
        if (categoryElement) {
            categoryElement.remove();
        }
    }

    /**
     * Handle item created.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - New item data
     */
    _handleItemCreated({element}) {
        const component = this.categoryComponents.get(element.categoryid);
        if (component) {
            component._handleItemCreated({element});
        }
    }

    /**
     * Replace rendered item.
     *
     * @param {Object} args - Event args
     * @param {Object} args.element - Updated item data
     */
    _replaceRenderedItem({element}) {
        const component = this.categoryComponents.get(element.categoryid);
        if (component) {
            component._replaceRenderedItem({element});
        }
    }

    /**
     * Render category.
     *
     * @param {Object} categoryData - Category data
     */
    async _renderCategory(categoryData) {
        const tableView = this.getElement(this.selectors.tableView);
        if (!tableView) {
            return;
        }

        // Use specialization's render method if available
        if (this._renderCategoryCustom) {
            await this._renderCategoryCustom(categoryData, tableView);
            return;
        }

        // Default rendering (can be overridden)
        const CategoryComponent = this.getCategoryComponent();
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = `
            <tbody data-region="checklist-category" data-categoryid="${categoryData.id}">
                <tr data-region="checklist-category-row">
                    <td colspan="100">${categoryData.name}</td>
                </tr>
            </tbody>
        `;
        const categoryElement = tempDiv.firstElementChild;
        tableView.appendChild(categoryElement);

        const instance = new CategoryComponent({
            element: categoryElement,
            reactive: this.reactive,
        });
        this.categoryComponents.set(categoryData.id, instance);
    }

    /**
     * Handle add category button click.
     *
     * @param {Event} event - Click event
     */
    async _handleAddCategoryClick(event) {
        event.preventDefault();

        const config = this.getConfig();
        const contextId = this.selectors.contextId;

        const modalForm = new ModalForm({
            formClass: config.categoryModalForm,
            args: {id: 0, contextid: contextId},
            modalConfig: {title: await getString('addcategory', 'mod_bookit')},
            returnFocus: event.currentTarget,
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            const response = e.detail;
            if (response.result === 'success') {
                this.reactive.dispatch('categoriesUpdated', {fields: response.data});
            }
        });

        modalForm.show();
    }

    /**
     * Handle add item button click.
     *
     * @param {Event} event - Click event
     */
    async _handleAddItemClick(event) {
        event.preventDefault();

        const config = this.getConfig();
        const contextId = this.selectors.contextId;

        const modalForm = new ModalForm({
            formClass: config.itemModalForm,
            args: {id: 0, contextid: contextId},
            modalConfig: {title: await getString(`add${config.itemType}`, 'mod_bookit')},
            returnFocus: event.currentTarget,
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
            const response = e.detail;
            if (response.result === 'success') {
                this.reactive.dispatch('itemsUpdated', {fields: response.data});
            }
        });

        modalForm.show();
    }

    /**
     * Cache localized strings.
     */
    _cacheStrings() {
        getString('active', 'core').then(str => {
            this.strings.active = str;
            return str;
        }).catch(() => {
            this.strings.active = 'Active';
        });
        getString('inactive', 'core').then(str => {
            this.strings.inactive = str;
            return str;
        }).catch(() => {
            this.strings.inactive = 'Inactive';
        });
    }
}
