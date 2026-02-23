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

define(['core/notification', 'core/str'], function(Notification, Str) {
    'use strict';

    /**
     * Get the row container for a resource checkbox element.
     *
     * @param {Element} groupElement The resource checkbox element
     * @return {Element} The fitem row div or the element itself as fallback
     */
    function getResourceRow(groupElement) {
        return groupElement.closest('div[id*="fgroup_id_"]') || groupElement;
    }

    /**
     * Enable a resource group (room is available).
     *
     * @param {Element} groupElement The resource checkbox element
     */
    function enableResource(groupElement) {
        const row = getResourceRow(groupElement);
        row.classList.remove('bookit-resource-disabled');
        row.querySelectorAll('input').forEach(input => {
            input.disabled = false;
        });
    }

    /**
     * Disable and grey out a resource group (room not available).
     *
     * @param {Element} groupElement The resource checkbox element
     */
    function disableResource(groupElement) {
        const row = getResourceRow(groupElement);
        row.classList.add('bookit-resource-disabled');
        row.querySelectorAll('input').forEach(input => {
            input.disabled = true;
            if (input.type === 'checkbox') {
                input.checked = false;
            }
        });
    }

    /**
     * Update visibility of resource category groups based on their content.
     *
     * @param {Element} modalRoot The modal root element
     */
    function updateCategoryVisibility(modalRoot) {
        window.console.log('[booking_form_resources] updateCategoryVisibility() called');

        if (!modalRoot) {
            return;
        }

        // Find all resource category fieldsets (groups).
        // Category fieldsets have IDs like "id_header_cat_1_*", "id_header_cat_2_*", etc.
        const categoryGroups = modalRoot.querySelectorAll('fieldset[id*="id_header_cat_"]');
        window.console.log('[booking_form_resources] Found', categoryGroups.length, 'category groups');

        // Categories are always visible - resources within are enabled or disabled.
        categoryGroups.forEach(fieldset => {
            fieldset.style.display = '';
        });
    }

    /**
     * Check for resource-room conflicts and show alert if any, then filter.
     *
     * Scenario 2: If resources are already checked and the newly selected room
     * does not support all of them, show a Moodle alert notification.
     *
     * @param {Element} modalRoot The modal root element
     * @param {string} selectedRoomId The newly selected room ID
     * @param {NodeList} resourceGroups All resource group elements
     */
    async function checkConflictAndFilter(modalRoot, selectedRoomId, resourceGroups) {
        const roomId = parseInt(selectedRoomId, 10);

        // Collect all currently checked resource checkboxes.
        const checkedGroups = Array.from(resourceGroups).filter(group => {
            const checkbox = group;
            return checkbox.type === 'checkbox' && checkbox.checked;
        });

        window.console.log('[booking_form_resources] Checked resources before room change:', checkedGroups.length);

        // Check if any checked resource is not available in the new room.
        const hasConflict = checkedGroups.some(group => {
            const roomsJson = group.getAttribute('data-resource-rooms');
            if (!roomsJson) {
                return true;
            }
            try {
                const rooms = JSON.parse(roomsJson);
                return !(Array.isArray(rooms) && rooms.includes(roomId));
            } catch (e) {
                return true;
            }
        });

        if (hasConflict) {
            window.console.log('[booking_form_resources] Conflict detected for room:', roomId);
            const title = await Str.get_string('error', 'core');
            const message = await Str.get_string('booking:resource_room_conflict', 'mod_bookit');
            await Notification.alert(title, message);
        }

        filterResourcesByRoom(modalRoot, selectedRoomId);
    }

    /**
     * Filter resources based on selected room.
     *
     * @param {Element} modalRoot The modal root element
     * @param {number|string} selectedRoomId The selected room ID
     */
    function filterResourcesByRoom(modalRoot, selectedRoomId) {
        window.console.log('[booking_form_resources] filterResourcesByRoom() called for room:', selectedRoomId);

        if (!modalRoot) {
            return;
        }

        const roomId = parseInt(selectedRoomId, 10);

        // Find all resource groups with data-resource-rooms attribute.
        const resourceGroups = modalRoot.querySelectorAll('[data-resource-rooms]');
        window.console.log('[booking_form_resources] Filtering', resourceGroups.length, 'resource groups for room ID:', roomId);

        resourceGroups.forEach(group => {
            const roomsJson = group.getAttribute('data-resource-rooms');
            if (!roomsJson) {
                disableResource(group);
                return;
            }

            try {
                const rooms = JSON.parse(roomsJson);
                const isAvailable = Array.isArray(rooms) && rooms.includes(roomId);
                window.console.log('[booking_form_resources] Resource rooms:', rooms, 'contains', roomId, ':', isAvailable);

                if (isAvailable) {
                    enableResource(group);
                } else {
                    disableResource(group);
                }
            } catch (e) {
                window.console.log('[booking_form_resources] Invalid JSON for resource:', roomsJson, e);
                disableResource(group);
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
        window.console.log('[booking_form_resources] init() called with modalRoot:', modalRoot);

        if (!modalRoot) {
            window.console.log('[booking_form_resources] No modalRoot provided, exiting');
            return;
        }

        // Find the room select element.
        const roomSelect = modalRoot.querySelector('select[name="roomid"]');
        window.console.log('[booking_form_resources] Room select element:', roomSelect);

        if (!roomSelect) {
            window.console.log('[booking_form_resources] Room select not found, exiting');
            return;
        }

        // Initially disable all resources (no room selected yet).
        const resourceGroups = modalRoot.querySelectorAll('[data-resource-rooms]');
        window.console.log('[booking_form_resources] Found', resourceGroups.length, 'resource groups');
        resourceGroups.forEach(group => disableResource(group));
        updateCategoryVisibility(modalRoot);

        // Listen for room changes.
        roomSelect.addEventListener('change', function() {
            const selectedRoomId = this.value;
            window.console.log('[booking_form_resources] Room changed to:', selectedRoomId);

            if (selectedRoomId) {
                checkConflictAndFilter(modalRoot, selectedRoomId, resourceGroups);
            } else {
                // No room selected - disable all resources.
                window.console.log('[booking_form_resources] No room selected, disabling all resources');
                resourceGroups.forEach(group => disableResource(group));
                updateCategoryVisibility(modalRoot);
            }
        });

        // If a room is already selected, filter immediately.
        if (roomSelect.value) {
            window.console.log('[booking_form_resources] Room already selected:', roomSelect.value);
            filterResourcesByRoom(modalRoot, roomSelect.value);
        }
    }

    return {
        init: init
    };
});
