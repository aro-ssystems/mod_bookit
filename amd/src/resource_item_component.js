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
 * Resource item component.
 *
 * @module mod_bookit/resource_item_component
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CatalogItemComponent from './base/catalog_item_component';
import {get_string as getString} from 'core/str';

/**
 * Concrete resource item component.
 */
export default class ResourceItemComponent extends CatalogItemComponent {
    /**
     * Get template name for rendering.
     *
     * @return {string} Template name
     */
    getTemplateName() {
        return 'mod_bookit/resource_item_card';
    }

    /**
     * Get the form class name for editing.
     *
     * @return {string} Form class name
     */
    getEditModalFormClass() {
        return 'mod_bookit\\form\\edit_resource_form';
    }

    /**
     * Get edit modal title.
     *
     * @return {Promise<string>} Modal title
     */
    async getEditModalTitle() {
        return getString('resources:edit_resource', 'mod_bookit');
    }

    /**
     * Get delete confirmation message.
     *
     * @return {Promise<string>} Confirm message
     */
    async getDeleteConfirmMessage() {
        return getString('resources:confirm_delete_resource', 'mod_bookit', this.itemData.name);
    }
}
