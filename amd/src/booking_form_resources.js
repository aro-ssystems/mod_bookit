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
 * Room-based resource filtering for booking form.
 *
 * @module     mod_bookit/booking_form_resources
 * @copyright  2026 ssystems GmbH <oss@ssystems.de>
 * @author     Andreas Rosenthal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Show a resource group.
     *
     * @param {Element} groupElement The resource group element
     */
    function showResource(groupElement) {
        groupElement.style.display = '';
    }

    /**
     * Hide a resource group.
     *
     * @param {Element} groupElement The resource group element
     */
    function hideResource(groupElement) {
        groupElement.style.display = 'none';
    }

    /**
     * Update visibility of resource category groups based on their content.
     *
     * @param {Element} modalRoot The modal root element
     */
    function updateCategoryVisibility(modalRoot) {
        if (!modalRoot) {
            return;
        }

        // Find all resource category fieldsets (groups).
        // Category fieldsets have IDs like "id_header_cat_1_*", "id_header_cat_2_*", etc.
        const categoryGroups = modalRoot.querySelectorAll('fieldset[id*="id_header_cat_"]');

        categoryGroups.forEach(fieldset => {
            // Check if this category has any visible resources.
            const resourceCheckboxes = fieldset.querySelectorAll('[data-resource-rooms]');
            let hasVisibleResource = false;

            resourceCheckboxes.forEach(checkbox => {
                const display = window.getComputedStyle(checkbox).display;
                if (display !== 'none') {
                    hasVisibleResource = true;
                }
            });

            // Hide the entire category if no resources are visible.
            if (hasVisibleResource) {
                fieldset.style.display = '';
            } else {
                fieldset.style.display = 'none';
            }
        });
    }

    /**
     * Filter resources based on selected room.
     *
     * @param {Element} modalRoot The modal root element
     * @param {number|string} selectedRoomId The selected room ID
     */
    function filterResourcesByRoom(modalRoot, selectedRoomId) {
        if (!modalRoot) {
            return;
        }

        const roomId = parseInt(selectedRoomId, 10);

        // Find all resource groups with data-resource-rooms attribute.
        const resourceGroups = modalRoot.querySelectorAll('[data-resource-rooms]');

        resourceGroups.forEach(group => {
            const roomsJson = group.getAttribute('data-resource-rooms');
            if (!roomsJson) {
                hideResource(group);
                return;
            }

            try {
                const rooms = JSON.parse(roomsJson);
                if (Array.isArray(rooms) && rooms.includes(roomId)) {
                    showResource(group);
                } else {
                    hideResource(group);
                }
            } catch (e) {
                // Invalid JSON, hide the resource.
                hideResource(group);
            }
        });

        // After filtering individual resources, update category visibility.
        updateCategoryVisibility(modalRoot);
    }

    /**
     * Initialize resource filtering.
     *
     * @param {Element} modalRoot The modal root element containing the form
     */
    function init(modalRoot) {
        if (!modalRoot) {
            return;
        }

        // Find the room select element.
        const roomSelect = modalRoot.querySelector('select[name="roomid"]');
        if (!roomSelect) {
            return;
        }

        // Initially hide all resources (no room selected) and empty categories.
        const resourceGroups = modalRoot.querySelectorAll('[data-resource-rooms]');
        resourceGroups.forEach(group => hideResource(group));
        updateCategoryVisibility(modalRoot);

        // Listen for room changes.
        roomSelect.addEventListener('change', function() {
            const selectedRoomId = this.value;
            if (selectedRoomId) {
                filterResourcesByRoom(modalRoot, selectedRoomId);
            } else {
                // No room selected - hide all resources and categories.
                resourceGroups.forEach(group => hideResource(group));
                updateCategoryVisibility(modalRoot);
            }
        });

        // If a room is already selected, filter immediately.
        if (roomSelect.value) {
            filterResourcesByRoom(modalRoot, roomSelect.value);
        }
    }

    return {
        init: init
    };
});
