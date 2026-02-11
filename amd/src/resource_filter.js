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
 * Resource filter component for multi-select button groups.
 *
 * @module mod_bookit/resource_filter
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    class ResourceFilter {
        /**
         * Constructor
         *
         * @param {string} selector - Selector for the filter container
         */
        constructor(selector) {
            this.container = document.querySelector(selector);
            if (!this.container) {
                return;
            }

            this.selectedRooms = new Set();
            this.initEventListeners();
        }

    /**
     * Initialize event listeners for filter buttons
     */
    initEventListeners() {
        const selectedRow = this.container.querySelector('[data-row="selected"]');
        const unselectedRow = this.container.querySelector('[data-row="unselected"]');

        if (!selectedRow || !unselectedRow) {
            return;
        }

        // Event delegation on both rows
        [selectedRow, unselectedRow].forEach(row => {
            row.addEventListener('click', (e) => {
                if (!e.target.matches('button') && !e.target.closest('button')) {
                    return;
                }

                const button = e.target.closest('button');
                this.toggleButton(button, selectedRow, unselectedRow);
                this.applyFilter();
            });
        });
    }

    /**
     * Toggle button state and move between rows
     *
     * @param {HTMLElement} button - Button element to toggle
     * @param {HTMLElement} selectedRow - Selected row container
     * @param {HTMLElement} unselectedRow - Unselected row container
     */
    toggleButton(button, selectedRow, unselectedRow) {
        const value = button.dataset.value;
        const isPressed = button.getAttribute('aria-pressed') === 'true';
        const icon = button.querySelector('.filter-icon');
        const filterSection = button.closest('.filter-section');
        const hiddenSelect = filterSection ? filterSection.querySelector('select[multiple]') : null;

        if (isPressed) {
            // Deselect: Move to unselected row, show plus, unselected color
            button.style.backgroundColor = button.dataset.colorUnselected;
            button.setAttribute('aria-pressed', 'false');
            if (icon) {
                icon.textContent = '+';
            }
            this.selectedRooms.delete(value);
            unselectedRow.appendChild(button);

            // Update hidden select
            if (hiddenSelect) {
                const option = hiddenSelect.querySelector(`option[value="${value}"]`);
                if (option) {
                    option.selected = false;
                }
            }
        } else {
            // Select: Move to selected row, show checkmark, selected color
            button.style.backgroundColor = button.dataset.colorSelected;
            button.setAttribute('aria-pressed', 'true');
            if (icon) {
                icon.textContent = '✓';
            }
            this.selectedRooms.add(value);
            selectedRow.appendChild(button);

            // Update hidden select
            if (hiddenSelect) {
                const option = hiddenSelect.querySelector(`option[value="${value}"]`);
                if (option) {
                    option.selected = true;
                }
            }
        }
    }

    /**
     * Apply the current filter to the resource table
     */
    applyFilter() {
        const allCategories = document.querySelectorAll('[data-region="resource-category"]');

        if (this.selectedRooms.size === 0) {
            allCategories.forEach(category => {
                category.style.display = '';
            });
            return;
        }

        allCategories.forEach(category => {
            const resourceRows = category.querySelectorAll('[data-region="resource-row"]');

            let hasVisibleResources = false;

            resourceRows.forEach(row => {
                const resourceRooms = this.getResourceRooms(row);

                if (this.hasMatchingRoom(resourceRooms)) {
                    row.style.display = '';
                    hasVisibleResources = true;
                } else {
                    row.style.display = 'none';
                }
            });

            if (hasVisibleResources || resourceRows.length === 0) {
                category.style.display = '';
            } else {
                category.style.display = 'none';
            }
        });
    }

    /**
     * Get room IDs for a resource row
     *
     * @param {HTMLElement} row - Resource row element
     * @return {Array} Array of room IDs
     */
    getResourceRooms(row) {
        const roomsData = row.dataset.rooms;
        if (!roomsData) {
            return [];
        }

        try {
            return JSON.parse(roomsData);
        } catch (e) {
            return [];
        }
    }

    /**
     * Check if resource has any matching room
     *
     * @param {Array} resourceRooms - Array of room IDs
     * @return {boolean} True if any room matches
     */
    hasMatchingRoom(resourceRooms) {
        if (resourceRooms.length === 0) {
            return true;
        }

        return resourceRooms.some(roomId =>
            this.selectedRooms.has(String(roomId))
        );
        }
    }

    /**
     * Initialize the resource filter
     *
     * @param {string} selector - Selector for the filter container
     * @return {ResourceFilter} Filter instance
     */
    return {
        init: function(selector) {
            return new ResourceFilter(selector);
        }
    };
});
