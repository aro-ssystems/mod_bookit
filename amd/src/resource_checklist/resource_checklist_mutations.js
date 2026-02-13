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
 * Mutations for resource checklist reactive state.
 *
 * @module mod_bookit/resource_checklist/resource_checklist_mutations
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseChecklistMutations from 'mod_bookit/checklist/base_checklist_mutations';

/**
 * Resource checklist mutations.
 *
 * Extends base mutations with resource checklist specific fields.
 */
export default class ResourceChecklistMutations extends BaseChecklistMutations {
    /**
     * Constructor.
     */
    constructor() {
        super('categories', 'checklistitems');
    }

    /**
     * Extract resource checklist specific item fields.
     *
     * @param {Object} fields - All fields from the data
     * @return {Object} Domain-specific fields
     */
    _getItemFields(fields) {
        return {
            resourceid: fields.resourceid || 0,
            duedate: fields.duedate || null,
            duedatetype: fields.duedatetype || null,
            active: Boolean(fields.active ?? true),
            beforedueid: fields.beforedueid || null,
            whendueid: fields.whendueid || null,
            overdueid: fields.overdueid || null,
            whendoneid: fields.whendoneid || null,
        };
    }
}
