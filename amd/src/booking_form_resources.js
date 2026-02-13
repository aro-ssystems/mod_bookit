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
 * This module handles filtering of resources based on room selection in the booking form.
 * It disables resources that are not available in the selected room and validates that
 * all selected resources are available in the chosen room.
 *
 * @module     mod_bookit/booking_form_resources
 * @copyright  2026 ssystems GmbH <oss@ssystems.de>
 * @author     Andreas Rosenthal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/str', 'core/notification'],
function($, Log, Str, Notification) {

    /**
     * Selectors for DOM elements.
     *
     * @type {Object}
     */
    const SELECTORS = {
        ROOM_SELECT: 'select[name="roomid"]',
        RESOURCE_CHECKBOX: '[name^="resource_selected_"]',
        RESOURCE_GROUP: '[id^="resourcegroup_"]',
        RESOURCE_AMOUNT: '[name^="resource_amount_"]',
        CATEGORY_HEADER: '[id^="header_cat_"]',
    };

    /**
     * CSS classes for styling.
     *
     * @type {Object}
     */
    const CSS = {
        DISABLED: 'disabled',
        UNAVAILABLE: 'text-muted',
        CONFLICT: 'alert-danger',
    };

    /**
     * Module state.
     *
     * @type {Object}
     */
    let state = {
        roomResourceMap: {},
        resourceRooms: {},
        currentRoom: null,
        selectedResources: [],
    };

    /**
     * Filter resources based on selected room.
     *
     * Resources with NO room assignments are always hidden (cannot be booked).
     * If no room is selected, show only resources that have room assignments.
     * If a room is selected, show only resources available in that room.
     *
     * @param {Number} roomId The selected room ID (null if no room selected)
     */
    const filterResourcesByRoom = function(roomId) {
        // Defensive check: ensure data is loaded and not just an empty object.
        if (!state.roomResourceMap || !state.resourceRooms) {
            Log.debug('[BookIt Resources] Data not yet loaded, skipping filter');
            return;
        }

        // Get all resources that have at least one room assignment.
        const bookableResourceIds = Object.keys(state.resourceRooms).map(id => parseInt(id, 10));

        if (!roomId) {
            // No room selected - show only resources that have room assignments.
            $(SELECTORS.RESOURCE_CHECKBOX).each(function() {
                const checkbox = $(this);
                const resourceId = getResourceIdFromElement(checkbox);

                if (bookableResourceIds.includes(resourceId)) {
                    enableResource(checkbox);
                } else {
                    // Resource has no room assignments - hide it.
                    disableResource(checkbox);
                }
            });
            return;
        }

        // Room selected - show only resources available in that specific room.
        const availableResources = state.roomResourceMap[roomId] || [];

        $(SELECTORS.RESOURCE_CHECKBOX).each(function() {
            const checkbox = $(this);
            const resourceId = getResourceIdFromElement(checkbox);

            if (availableResources.includes(resourceId)) {
                // Resource available in this room.
                enableResource(checkbox);
            } else {
                // Resource NOT available in this room (or has no room assignments at all).
                disableResource(checkbox);
            }
        });
    };

    /**
     * Enable a resource checkbox.
     *
     * Removes disabled state, shows the resource row, and removes conflict styling.
     *
     * @param {jQuery} checkbox The checkbox element
     */
    const enableResource = function(checkbox) {
        checkbox.prop('disabled', false);
        checkbox.closest(SELECTORS.RESOURCE_GROUP)
            .show()
            .removeClass(CSS.CONFLICT);
    };

    /**
     * Disable a resource checkbox.
     *
     * Sets disabled state and HIDES the entire resource row.
     * Does not uncheck if already checked (preserves selection for conflict detection).
     *
     * @param {jQuery} checkbox The checkbox element
     */
    const disableResource = function(checkbox) {
        checkbox.prop('disabled', true);
        checkbox.closest(SELECTORS.RESOURCE_GROUP).hide();
    };

    /**
     * Extract resource ID from form element name.
     *
     * Parses the element name attribute to extract the resource ID.
     * Expected format: resource_selected_123
     *
     * @param {jQuery} element The form element
     * @return {Number|null} The resource ID or null if not found
     */
    const getResourceIdFromElement = function(element) {
        const name = element.attr('name');
        const match = name.match(/resource_selected_(\d+)/);
        return match ? parseInt(match[1], 10) : null;
    };

    /**
     * Check for resource-room conflicts.
     *
     * Validates that all selected resources are available in the selected room.
     * If conflicts exist, shows an error notification and highlights conflicting resources.
     *
     * @return {Boolean} True if conflicts exist
     */
    const checkForConflicts = function() {
        const roomId = parseInt($(SELECTORS.ROOM_SELECT).val(), 10);
        if (!roomId) {
            return false;
        }

        const selectedResourceIds = getSelectedResourceIds();
        const availableResources = state.roomResourceMap[roomId] || [];

        const conflicts = selectedResourceIds.filter(id => !availableResources.includes(id));

        if (conflicts.length > 0) {
            showConflictError(conflicts);
            return true;
        }

        return false;
    };

    /**
     * Get IDs of all selected resources.
     *
     * @return {Array} Array of resource IDs
     */
    const getSelectedResourceIds = function() {
        const selected = [];
        $(SELECTORS.RESOURCE_CHECKBOX + ':checked').each(function() {
            const resourceId = getResourceIdFromElement($(this));
            if (resourceId) {
                selected.push(resourceId);
            }
        });
        return selected;
    };

    /**
     * Show conflict error notification.
     *
     * Displays a Moodle error notification and highlights conflicting resources.
     *
     * @param {Array} conflictingIds Array of conflicting resource IDs
     */
    const showConflictError = function(conflictingIds) {
        Str.get_string('booking:resource_room_conflict', 'mod_bookit').then(function(message) {
            Notification.addNotification({
                message: message,
                type: 'error',
            });

            // Highlight conflicting resources.
            conflictingIds.forEach(function(id) {
                $('[name="resource_selected_' + id + '"]')
                    .closest(SELECTORS.RESOURCE_GROUP)
                    .addClass(CSS.CONFLICT);
            });

            return true;
        }).catch(Notification.exception);
    };

    /**
     * Register event listeners.
     *
     * Sets up event listeners for room selection and resource checkbox changes.
     * Uses event delegation for better performance and compatibility with dynamic content.
     */
    const registerEventListeners = function() {
        // Listen to room selection changes.
        $(document).on('change', SELECTORS.ROOM_SELECT, function() {
            const roomId = parseInt($(this).val(), 10);
            state.currentRoom = roomId;
            filterResourcesByRoom(roomId);
            checkForConflicts();
        });

        // Listen to resource checkbox changes.
        $(document).on('change', SELECTORS.RESOURCE_CHECKBOX, function() {
            // Clear conflict styling.
            $(this).closest(SELECTORS.RESOURCE_GROUP).removeClass(CSS.CONFLICT);

            // Check for new conflicts.
            if (state.currentRoom) {
                checkForConflicts();
            }
        });
    };

    /**
     * Initialize the module.
     *
     * Sets up the module with room-resource mapping data and registers event listeners.
     *
     * @param {Object} roomResourceMapData Mapping of room IDs to resource IDs
     * @param {Object} resourceRoomsData Mapping of resource IDs to room details
     */
    const init = function(roomResourceMapData, resourceRoomsData) {
        Log.debug('[BookIt Resources] Initializing booking form resources filter');
        Log.debug('[BookIt Resources] roomResourceMap:', roomResourceMapData);
        Log.debug('[BookIt Resources] resourceRooms:', resourceRoomsData);

        state.roomResourceMap = roomResourceMapData;
        state.resourceRooms = resourceRoomsData;

        // Wait for form to be ready (modal may not be loaded yet).
        const initWhenReady = function() {
            const roomSelect = $(SELECTORS.ROOM_SELECT);
            if (roomSelect.length === 0) {
                Log.debug('[BookIt Resources] Room select not found yet, waiting...');
                setTimeout(initWhenReady, 100);
                return;
            }

            Log.debug('[BookIt Resources] Room select found, setting up event handlers');

            // Get current room if already selected.
            const currentRoomId = parseInt(roomSelect.val(), 10);
            if (currentRoomId) {
                state.currentRoom = currentRoomId;
                filterResourcesByRoom(currentRoomId);
            }

            // Register event listeners.
            registerEventListeners();

            Log.debug('[BookIt Resources] Initialization complete');
        };

        // Start initialization check.
        initWhenReady();
    };

    // Public API.
    return {
        init: init,
    };
});
