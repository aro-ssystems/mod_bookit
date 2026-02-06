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
 * Resource catalog component (entry point).
 *
 * @module mod_bookit/resource_catalog
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CatalogCatalogComponent from './base/catalog_catalog_component';
import ResourceCategoryComponent from './resource_category_component';
import ResourceItemComponent from './resource_item_component';
import {createResourceReactive} from './resource_reactive';
import {get_string as getString} from 'core/str';

/**
 * Concrete resource catalog component.
 */
class ResourceCatalogComponent extends CatalogCatalogComponent {
    /**
     * Get item component class.
     *
     * @return {Function} Item component class constructor
     */
    getItemComponentClass() {
        return ResourceItemComponent;
    }

    /**
     * Get the form class name for adding category.
     *
     * @return {string} Form class name
     */
    getAddCategoryModalFormClass() {
        return 'mod_bookit\\form\\edit_resource_category_form';
    }

    /**
     * Get add category modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getAddCategoryModalTitle() {
        return getString('resources:add_category', 'mod_bookit');
    }
}

/**
 * Initialize resource catalog.
 *
 * Called from PHP via $PAGE->requires->js_call_amd()
 *
 * @param {number} contextId - Context ID
 * @param {string} categoriesJson - JSON string of categories with resources
 */
export const init = (contextId, categoriesJson) => {
    // Parse categories JSON.
    const categories = JSON.parse(categoriesJson);

    // Extract items from categories.
    const items = [];
    categories.forEach(category => {
        if (category.resources && Array.isArray(category.resources)) {
            items.push(...category.resources);
        }
    });

    // Create reactive store.
    const reactive = createResourceReactive({
        categories: categories,
        items: items,
    });

    // Create and initialize catalog component.
    const catalog = new ResourceCatalogComponent(reactive, ResourceCategoryComponent);
    catalog.init('mod-bookit-resource-catalog');

    window.console.log('Resource catalog initialized', {
        contextId: contextId,
        categoriesCount: categories.length,
        itemsCount: items.length,
    });
};
