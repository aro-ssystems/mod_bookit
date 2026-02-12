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
 * Reactive store for resource checklist.
 *
 * @module mod_bookit/resource_checklist/resource_checklist_reactive
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {createChecklistReactive} from 'mod_bookit/checklist/base_checklist_reactive';
import ResourceChecklistMutations from 'mod_bookit/resource_checklist/resource_checklist_mutations';

export const SELECTORS = {
    TABLE: '#resource-checklist-table',
    ALL_CATEGORY_ROWS: 'tbody[data-bookit-category-id]',
    ALL_ITEM_ROWS: 'tr[data-bookit-item-id]',
};

const store = createChecklistReactive({
    name: 'Moodle Bookit Resource Checklist',
    eventName: 'mod_bookit:resource_checklist_state_event',
    selectors: SELECTORS,
    mutationsClass: new ResourceChecklistMutations(),
    stateKeys: {
        categories: 'categories',
        items: 'checklistitems'
    }
});

export const resourceChecklistReactiveInstance = store.reactiveInstance;
export const initResourceChecklistReactive = store.initReactive;
