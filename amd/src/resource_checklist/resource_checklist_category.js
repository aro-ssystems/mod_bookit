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
 * Resource checklist category component.
 *
 * @module mod_bookit/resource_checklist/resource_checklist_category
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseChecklistCategory from 'mod_bookit/checklist/base_checklist_category';
import ResourceChecklistItem from './resource_checklist_item';

/**
 * Resource checklist category component.
 */
export default class extends BaseChecklistCategory {
    /**
     * Component descriptor for debugging.
     *
     * @return {string} Component name
     */
    static get componentName() {
        return 'mod_bookit/resource_checklist/resource_checklist_category';
    }

    /**
     * Get configuration object.
     *
     * @return {Object} Configuration
     */
    getConfig() {
        return {
            categoriesStateKey: 'categories',
            itemsStateKey: 'checklistitems',
            itemType: 'checklistitem',
            itemModalForm: 'mod_bookit\\form\\edit_resource_checklist_item_form',
            categoryModalForm: 'mod_bookit\\form\\edit_category_form',
            templates: {
                categoryRow: 'mod_bookit/resource_checklist_category_row',
                itemRow: 'mod_bookit/resource_checklist_item_row',
            }
        };
    }

    /**
     * Get item component class.
     *
     * @return {Class} Item component class
     */
    getItemComponent() {
        return ResourceChecklistItem;
    }
}
