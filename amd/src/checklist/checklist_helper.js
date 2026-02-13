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
 * Checklist helper utilities.
 *
 * Shared utility functions for checklist-style components.
 *
 * @module mod_bookit/checklist/checklist_helper
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Parse JSON from dataset attribute safely.
 *
 * @param {string} jsonString - JSON string to parse
 * @param {*} defaultValue - Default value if parsing fails
 * @return {*} Parsed value or default
 */
export const parseDatasetJson = (jsonString, defaultValue = []) => {
    if (!jsonString) {
        return defaultValue;
    }

    try {
        const parsed = JSON.parse(jsonString);
        if (Array.isArray(defaultValue) && !Array.isArray(parsed)) {
            return defaultValue;
        }
        return parsed;
    } catch (e) {
        window.console.warn('Failed to parse JSON:', e, jsonString);
        return defaultValue;
    }
};

/**
 * Extract category data from DOM element.
 *
 * @param {HTMLElement} element - Category element
 * @return {Object} Category data
 */
export const extractCategoryData = (element) => {
    const categoryRow = element.querySelector('[data-region="checklist-category-row"]');
    return {
        id: parseInt(element.dataset.categoryid),
        name: element.dataset.categoryName || categoryRow?.textContent.trim() || '',
        description: element.dataset.categoryDescription || '',
        sortorder: parseInt(element.dataset.categorySortorder) || 0,
    };
};

/**
 * Extract item data from DOM element.
 *
 * @param {HTMLElement} element - Item element
 * @param {Object} additionalFields - Additional field extractors
 * @return {Object} Item data
 */
export const extractItemData = (element, additionalFields = {}) => {
    const baseData = {
        id: parseInt(element.dataset.itemid || element.dataset.bookitItemId),
        name: element.dataset.itemName || '',
        description: element.dataset.itemDescription || '',
        categoryid: parseInt(element.dataset.itemCategoryid || element.dataset.categoryid),
        sortorder: parseInt(element.dataset.itemSortorder) || 0,
    };

    // Apply additional field extractors
    Object.keys(additionalFields).forEach(key => {
        const extractor = additionalFields[key];
        if (typeof extractor === 'function') {
            baseData[key] = extractor(element);
        } else if (typeof extractor === 'string') {
            // Extractor is a dataset key
            baseData[key] = element.dataset[extractor];
        }
    });

    return baseData;
};

/**
 * Initialize categories and items from DOM.
 *
 * @param {HTMLElement} containerElement - Container element
 * @param {Object} config - Configuration object
 * @param {string} config.categoryRegion - Category region selector
 * @param {string} config.itemRegion - Item region selector
 * @param {Object} config.itemFields - Additional item field extractors
 * @return {Object} Object with categories and items arrays
 */
export const initializeFromDOM = (containerElement, config) => {
    const categoriesArray = [];
    const itemsArray = [];

    const categoryElements = containerElement.querySelectorAll(
        `[data-region="${config.categoryRegion || 'checklist-category'}"]`
    );

    categoryElements.forEach(categoryEl => {
        // Extract category data
        const categoryData = extractCategoryData(categoryEl);
        categoriesArray.push(categoryData);

        // Extract items for this category
        const itemElements = categoryEl.querySelectorAll(
            `[data-region="${config.itemRegion || 'checklist-item-row'}"]`
        );

        itemElements.forEach(itemEl => {
            const itemData = extractItemData(itemEl, config.itemFields || {});
            itemsArray.push(itemData);
        });
    });

    return {
        categories: categoriesArray,
        items: itemsArray,
    };
};

/**
 * Debounce function execution.
 *
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @return {Function} Debounced function
 */
export const debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

/**
 * Get nested property safely.
 *
 * @param {Object} obj - Object to get property from
 * @param {string} path - Property path (e.g., 'a.b.c')
 * @param {*} defaultValue - Default value if property not found
 * @return {*} Property value or default
 */
export const getNestedProperty = (obj, path, defaultValue = undefined) => {
    const keys = path.split('.');
    let result = obj;

    for (const key of keys) {
        if (result === null || result === undefined) {
            return defaultValue;
        }
        result = result[key];
    }

    return result !== undefined ? result : defaultValue;
};

/**
 * Format date for display.
 *
 * @param {number} timestamp - Unix timestamp
 * @param {string} locale - Locale string
 * @return {string} Formatted date
 */
export const formatDate = (timestamp, locale = 'en-US') => {
    if (!timestamp) {
        return '';
    }

    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString(locale);
};

/**
 * Format datetime for display.
 *
 * @param {number} timestamp - Unix timestamp
 * @param {string} locale - Locale string
 * @return {string} Formatted datetime
 */
export const formatDateTime = (timestamp, locale = 'en-US') => {
    if (!timestamp) {
        return '';
    }

    const date = new Date(timestamp * 1000);
    return date.toLocaleString(locale);
};

/**
 * Sort array of objects by sortorder field.
 *
 * @param {Array} array - Array to sort
 * @return {Array} Sorted array
 */
export const sortBySortorder = (array) => {
    return array.sort((a, b) => {
        const aOrder = a.sortorder || 0;
        const bOrder = b.sortorder || 0;
        return aOrder - bOrder;
    });
};

/**
 * Filter items by category ID.
 *
 * @param {Array} items - Items array
 * @param {number} categoryId - Category ID
 * @return {Array} Filtered items
 */
export const filterItemsByCategory = (items, categoryId) => {
    return items.filter(item => item.categoryid === categoryId);
};

/**
 * Group items by category ID.
 *
 * @param {Array} items - Items array
 * @return {Map} Map of categoryId -> items array
 */
export const groupItemsByCategory = (items) => {
    const groups = new Map();

    items.forEach(item => {
        if (!groups.has(item.categoryid)) {
            groups.set(item.categoryid, []);
        }
        groups.get(item.categoryid).push(item);
    });

    return groups;
};
