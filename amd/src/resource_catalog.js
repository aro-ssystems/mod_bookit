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
 * Resource catalog main module (placeholder).
 *
 * @module mod_bookit/resource_catalog
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = (contextid, categories) => {
    // TODO: Phase 8 - Initialize reactive store and components
    const container = document.getElementById('resource-catalog-container');
    if (container) {
        container.innerHTML = '<div class="alert alert-info">';
        container.innerHTML += '<h4>Resource Catalog (Reactive JS in Phase 8)</h4>';
        container.innerHTML += '<p>Categories loaded: ' + categories.length + '</p>';
        container.innerHTML += '<pre>' + JSON.stringify(categories, null, 2) + '</pre>';
        container.innerHTML += '</div>';
    }
};
