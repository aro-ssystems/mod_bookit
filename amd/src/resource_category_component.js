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
 * Resource category component.
 *
 * @module mod_bookit/resource_category_component
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CatalogCategoryComponent from './base/catalog_category_component';
import {get_string as getString} from 'core/str';

/**
 * Concrete resource category component.
 */
export default class ResourceCategoryComponent extends CatalogCategoryComponent {
    /**
     * Get template name for rendering.
     *
     * @return {string} Template name
     */
    getTemplateName() {
        return 'mod_bookit/resource_category_card';
    }

    /**
     * Get the form class name for editing category.
     *
     * @return {string} Form class name
     */
    getEditModalFormClass() {
        return 'mod_bookit\\form\\edit_resource_category_form';
    }

    /**
     * Get add item modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getAddItemModalTitle() {
        return getString('resources:add_resource', 'mod_bookit');
    }

    /**
     * Get edit modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getEditModalTitle() {
        return getString('resources:edit_category', 'mod_bookit');
    }

    /**
     * Get delete confirmation message.
     *
     * @return {Promise<string>} Confirm message
     */
    async getDeleteConfirmMessage() {
        return getString('resources:confirm_delete_category', 'mod_bookit', this.categoryData.name);
    }
}
