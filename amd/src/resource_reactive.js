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
 * Reactive store for resource catalog.
 *
 * @module mod_bookit/resource_reactive
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import ModalForm from 'core_form/modalform';

/**
 * Mutations for resource catalog.
 */
class ResourceMutations {
    /**
     * Prepare initial state.
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - Initial data {categories, items}
     */
    prepareState(stateManager, data) {
        const state = stateManager.state;

        // Initialize maps.
        if (!state.categories) {
            state.categories = new Map();
        }
        if (!state.items) {
            state.items = new Map();
        }

        // Load categories.
        if (data.categories) {
            data.categories.forEach(category => {
                state.categories.set(category.id, {
                    ...category,
                    id: parseInt(category.id, 10),
                });
            });
        }

        // Load items.
        if (data.items) {
            data.items.forEach(item => {
                state.items.set(item.id, {
                    ...item,
                    id: parseInt(item.id, 10),
                    categoryid: parseInt(item.categoryid, 10),
                });
            });
        }
    }

    /**
     * Reorder items within or between categories.
     *
     * Pattern: Wie master_checklist_mutations.js
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - {id, targetId, parentId, targetParentId}
     */
    reOrderCategoryItems(stateManager, data) {
        const state = stateManager.state;

        stateManager.setReadOnly(false);

        // Item moved to different category.
        if (data.parentId !== data.targetParentId) {
            const sourceCategory = state.categories.get(data.parentId);
            const targetCategory = state.categories.get(data.targetParentId);

            if (!sourceCategory.items || !Array.isArray(sourceCategory.items)) {
                sourceCategory.items = [];
            }

            if (!targetCategory.items || !Array.isArray(targetCategory.items)) {
                targetCategory.items = [];
            }

            const idToMove = parseInt(data.id, 10);
            const targetId = parseInt(data.targetId, 10);

            // Remove from source.
            sourceCategory.items = sourceCategory.items.filter(item => item !== idToMove);

            // Add to target.
            const targetItems = [...targetCategory.items];
            const existingTargetIndex = targetItems.indexOf(idToMove);

            if (existingTargetIndex === -1) {
                const targetIndex = targetItems.indexOf(targetId);

                if (targetIndex !== -1) {
                    targetItems.splice(targetIndex + 1, 0, idToMove);
                } else {
                    targetItems.push(idToMove);
                }
            }

            targetCategory.items = targetItems;

            // Update item's categoryid.
            const targetItem = state.items.get(idToMove);
            if (targetItem) {
                targetItem.categoryid = parseInt(targetCategory.id, 10);
            }

        } else {
            // Item moved within same category.
            const category = state.categories.get(data.targetParentId);

            if (!category.items || !Array.isArray(category.items)) {
                category.items = [];
            }

            const currentItems = [...category.items];
            const idToMove = parseInt(data.id, 10);
            const targetId = parseInt(data.targetId, 10);

            const currentIndex = currentItems.indexOf(idToMove);

            if (currentIndex === -1) {
                currentItems.push(idToMove);
            }

            const targetIndex = currentItems.indexOf(targetId);

            if (targetIndex !== -1 && currentIndex !== -1) {
                currentItems.splice(currentIndex, 1);
                const newTargetIndex = currentItems.indexOf(targetId);
                currentItems.splice(newTargetIndex + 1, 0, idToMove);
            } else if (currentIndex !== -1) {
                currentItems.splice(currentIndex, 1);
                currentItems.push(idToMove);
            }

            category.items = currentItems;
        }

        stateManager.setReadOnly(true);

        // Persist state changes via ModalForm.
        const categoriesToUpdate = [];

        categoriesToUpdate.push(data.targetParentId);

        if (data.parentId !== data.targetParentId) {
            categoriesToUpdate.push(data.parentId);
        }

        // Update each affected category.
        categoriesToUpdate.forEach(categoryId => {
            const category = state.categories.get(categoryId);
            const formDataObj = {
                id: category.id,
                name: category.name,
                description: category.description || '',
                items: category.items || [], // Reordered items
                action: 'put',
            };

            const mutationData = {
                formData: formDataObj,
                formType: 'category'
            };

            this._callDynamicForm(stateManager, mutationData, false);
        });
    }

    /**
     * Reorder categories.
     *
     * Pattern: Wie master_checklist_mutations.js
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - {id, targetId, parentId}
     */
    reOrderCategories(stateManager, data) {
        const state = stateManager.state;

        // Build category order array.
        let categoryOrder = Array.from(state.categories.keys()).sort((a, b) => {
            const catA = state.categories.get(a);
            const catB = state.categories.get(b);
            return (catA.sortorder || 0) - (catB.sortorder || 0);
        });

        const idToMove = parseInt(data.id, 10);
        const targetId = parseInt(data.targetId, 10);

        // Remove from current position.
        categoryOrder = categoryOrder.filter(id => id !== idToMove);

        // Insert after target.
        const targetIndex = categoryOrder.indexOf(targetId);

        if (targetIndex !== -1) {
            categoryOrder.splice(targetIndex + 1, 0, idToMove);
        } else {
            categoryOrder.push(idToMove);
        }

        // Build comma-separated string.
        const updatedCategoryOrder = categoryOrder.join(',');

        // Persist via ModalForm.
        const formDataObj = {
            id: 0, // Special marker for "catalog" level update
            categoryorder: updatedCategoryOrder,
            action: 'put',
        };

        data.formData = formDataObj;
        data.formType = 'category';

        this._callDynamicForm(stateManager, data);
    }

    /**
     * Create category (opens ModalForm).
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - Form submission data
     */
    createCategory(stateManager, data) {
        // Data comes from FORM_SUBMITTED event.
        // Process and add to state.
        stateManager.setReadOnly(false);

        const newCategory = {
            id: parseInt(data.id, 10),
            name: data.name,
            description: data.description || '',
            sortorder: data.sortorder || 0,
            active: data.active !== undefined ? data.active : true,
            items: [],
        };

        stateManager.state.categories.set(newCategory.id, newCategory);

        stateManager.setReadOnly(true);
    }

    /**
     * Update category.
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - Form submission data
     */
    updateCategory(stateManager, data) {
        stateManager.setReadOnly(false);

        const category = stateManager.state.categories.get(parseInt(data.id, 10));

        if (category) {
            category.name = data.name;
            category.description = data.description || '';
            category.sortorder = data.sortorder || category.sortorder;
            category.active = data.active !== undefined ? data.active : category.active;
        }

        stateManager.setReadOnly(true);
    }

    /**
     * Delete category.
     *
     * @param {Object} stateManager - State manager
     * @param {number} categoryId - Category ID
     */
    deleteCategory(stateManager, categoryId) {
        stateManager.setReadOnly(false);

        const category = stateManager.state.categories.get(categoryId);

        // Delete all items in category.
        if (category && category.items) {
            category.items.forEach(itemId => {
                stateManager.state.items.delete(itemId);
            });
        }

        // Delete category.
        stateManager.state.categories.delete(categoryId);

        stateManager.setReadOnly(true);
    }

    /**
     * Create item (opens ModalForm).
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - Form submission data
     */
    createItem(stateManager, data) {
        stateManager.setReadOnly(false);

        const newItem = {
            id: parseInt(data.id, 10),
            categoryid: parseInt(data.categoryid, 10),
            name: data.name,
            description: data.description || '',
            amount: data.amount || 0,
            amountirrelevant: data.amountirrelevant !== undefined ? data.amountirrelevant : false,
            sortorder: data.sortorder || 0,
            active: data.active !== undefined ? data.active : true,
        };

        stateManager.state.items.set(newItem.id, newItem);

        // Add to category items array.
        const category = stateManager.state.categories.get(newItem.categoryid);
        if (category) {
            if (!category.items) {
                category.items = [];
            }
            category.items.push(newItem.id);
        }

        stateManager.setReadOnly(true);
    }

    /**
     * Update item.
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - Form submission data
     */
    updateItem(stateManager, data) {
        stateManager.setReadOnly(false);

        const item = stateManager.state.items.get(parseInt(data.id, 10));

        if (item) {
            const oldCategoryId = item.categoryid;
            const newCategoryId = parseInt(data.categoryid, 10);

            item.name = data.name;
            item.description = data.description || '';
            item.amount = data.amount || 0;
            item.amountirrelevant = data.amountirrelevant !== undefined ? data.amountirrelevant : false;
            item.sortorder = data.sortorder || item.sortorder;
            item.active = data.active !== undefined ? data.active : item.active;
            item.categoryid = newCategoryId;

            // If category changed, update items arrays.
            if (oldCategoryId !== newCategoryId) {
                const oldCategory = stateManager.state.categories.get(oldCategoryId);
                const newCategory = stateManager.state.categories.get(newCategoryId);

                if (oldCategory && oldCategory.items) {
                    oldCategory.items = oldCategory.items.filter(id => id !== item.id);
                }

                if (newCategory) {
                    if (!newCategory.items) {
                        newCategory.items = [];
                    }
                    newCategory.items.push(item.id);
                }
            }
        }

        stateManager.setReadOnly(true);
    }

    /**
     * Delete item.
     *
     * @param {Object} stateManager - State manager
     * @param {number} itemId - Item ID
     */
    deleteItem(stateManager, itemId) {
        stateManager.setReadOnly(false);

        const item = stateManager.state.items.get(itemId);

        if (item) {
            // Remove from category items array.
            const category = stateManager.state.categories.get(item.categoryid);
            if (category && category.items) {
                category.items = category.items.filter(id => id !== itemId);
            }

            // Delete item.
            stateManager.state.items.delete(itemId);
        }

        stateManager.setReadOnly(true);
    }

    /**
     * Call dynamic form for persistence.
     *
     * Pattern: Wie master_checklist_mutations.js
     *
     * @param {Object} stateManager - State manager
     * @param {Object} data - {formData, formType}
     * @param {boolean} showModal - Whether to show modal (default: false)
     * @private
     */
    _callDynamicForm(stateManager, data, showModal = false) {
        const formClass = this._getFormClass(data.formType);

        const modalForm = new ModalForm({
            formClass: formClass,
            args: data.formData,
            modalConfig: {
                title: '', // Not shown if not modal
            },
            saveButtonText: '', // Not relevant for silent submission
        });

        if (!showModal) {
            // Submit form silently (no modal shown).
            return modalForm.processNoSubmitButton().then(() => {
                // Form submitted successfully.
                window.console.log('Form submitted:', data.formType, data.formData);
                return true;
            }).catch((error) => {
                window.console.error('Form submission failed:', error);
                throw error;
            });
        }
        // Show modal.
        modalForm.show();
        return Promise.resolve();
    }

    /**
     * Get form class name based on type.
     *
     * @param {string} formType - Form type ('category' or 'item')
     * @return {string} Form class name
     * @private
     */
    _getFormClass(formType) {
        switch (formType) {
            case 'category':
                return 'mod_bookit\\form\\edit_resource_category_form';
            case 'item':
                return 'mod_bookit\\form\\edit_resource_form';
            default:
                throw new Error(`Unknown form type: ${formType}`);
        }
    }
}

/**
 * Create reactive store for resources.
 *
 * @param {Object} initialData - {categories, items}
 * @return {Object} Reactive store instance
 */
export const createResourceReactive = (initialData) => {
    const mutations = new ResourceMutations();

    const reactive = new Reactive({
        name: 'mod_bookit-resource_catalog',
        eventName: 'resourceCatalogUpdated',
        // eslint-disable-next-line no-empty-function
        eventDispatch: () => {},
        mutations: mutations,
        state: {
            categories: new Map(),
            items: new Map(),
        },
    });

    reactive.stateReady.then(() => {
        // Initial state setup.
        mutations.prepareState(reactive, initialData);
        return true;
    }).catch((error) => {
        window.console.error('Error initializing reactive state:', error);
        throw error;
    });

    return reactive;
};
