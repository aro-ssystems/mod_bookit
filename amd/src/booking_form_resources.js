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
     * Only re-enables the controlling checkbox; dependent fields remain
     * governed by MoodleQuickForm's disabledIf logic.
     *
     * @param {Element} groupElement The resource checkbox element
     */
    function enableResource(groupElement) {
        const row = getResourceRow(groupElement);
        row.classList.remove('bookit-resource-disabled');
        if (groupElement && groupElement.tagName === 'INPUT') {
            groupElement.disabled = false;
        } else {
            const checkbox = row.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.disabled = false;
            }
        }
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
        if (!modalRoot) {
            return;
        }

        // Categories are always visible - resources within are enabled or disabled.
        const categoryGroups = modalRoot.querySelectorAll('fieldset[id*="id_header_cat_"]');
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

        // Check if any checked resource is not available in the new room.
        // An empty rooms array means the resource is available in all rooms (no conflict).
        const hasConflict = checkedGroups.some(group => {
            const roomsJson = group.getAttribute('data-resource-rooms');
            if (!roomsJson) {
                return false;
            }
            try {
                const rooms = JSON.parse(roomsJson);
                if (!Array.isArray(rooms) || rooms.length === 0) {
                    return false; // Available in all rooms.
                }
                return !rooms.includes(roomId);
            } catch (e) {
                return false;
            }
        });

        if (hasConflict) {
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
        if (!modalRoot) {
            return;
        }

        const roomId = parseInt(selectedRoomId, 10);

        // Find all resource groups with data-resource-rooms attribute.
        const resourceGroups = modalRoot.querySelectorAll('[data-resource-rooms]');

        resourceGroups.forEach(group => {
            const roomsJson = group.getAttribute('data-resource-rooms');
            try {
                const rooms = JSON.parse(roomsJson || '[]');
                // Empty rooms array means available in all rooms.
                const isAvailable = !Array.isArray(rooms) || rooms.length === 0 || rooms.includes(roomId);

                if (isAvailable) {
                    enableResource(group);
                } else {
                    disableResource(group);
                }
            } catch (e) {
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
        if (!modalRoot) {
            return;
        }

        // Find the room select element.
        const roomSelect = modalRoot.querySelector('select[name="roomid"]');

        if (!roomSelect) {
            return;
        }

        // Collect all resource groups.
        const resourceGroups = modalRoot.querySelectorAll('[data-resource-rooms]');

        // If no room is selected yet, disable all resources initially.
        if (!roomSelect.value) {
            resourceGroups.forEach(group => disableResource(group));
            updateCategoryVisibility(modalRoot);
        }

        // Listen for room changes.
        roomSelect.addEventListener('change', function() {
            const selectedRoomId = this.value;

            if (selectedRoomId) {
                checkConflictAndFilter(modalRoot, selectedRoomId, resourceGroups);
            } else {
                // No room selected - disable all resources.
                resourceGroups.forEach(group => disableResource(group));
                updateCategoryVisibility(modalRoot);
            }
        });

        // If a room is already selected, filter immediately without wiping existing selections.
        if (roomSelect.value) {
            filterResourcesByRoom(modalRoot, roomSelect.value);
        }
    }

    return {
        init: init
    };
});
