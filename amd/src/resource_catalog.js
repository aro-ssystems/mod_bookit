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
 * Resource catalog placeholder - Phase 8 to be implemented with BaseComponent pattern.
 *
 * @module mod_bookit/resource_catalog
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize resource catalog.
 *
 * Placeholder for Phase 8 implementation with Moodle BaseComponent pattern.
 *
 * @param {number} contextId - Context ID
 * @param {string} categoriesJson - JSON string of categories with resources
 */
export const init = (contextId, categoriesJson) => {
    const categories = JSON.parse(categoriesJson);

    const container = document.getElementById('mod-bookit-resource-catalog');
    if (container) {
        container.innerHTML = '<div class="alert alert-info m-4">';
        container.innerHTML += '<h4>Resource Catalog</h4>';
        container.innerHTML += '<p>Phase 8: Implementation with Moodle BaseComponent pattern pending</p>';
        container.innerHTML += '<p>Categories loaded: ' + categories.length + '</p>';
        container.innerHTML += '<pre>' + JSON.stringify(categories, null, 2).substring(0, 500) + '...</pre>';
        container.innerHTML += '</div>';
    }

    window.console.log('Resource catalog placeholder initialized', {
        contextId: contextId,
        categoriesCount: categories.length,
    });
};
