<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Resource manager class.
 *
 * @package     mod_bookit
 * @copyright   2024 Melanie Treitinger, Ruhr-Universität Bochum <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_bookit\local\manager;

use dml_exception;
use mod_bookit\local\entity\bookit_resource;
use mod_bookit\local\entity\bookit_resource_categories;

/**
 * Resource manager class.
 *
 * @package     mod_bookit
 * @copyright   2024 Melanie Treitinger, Ruhr-Universität Bochum <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_manager {
    /**
     * Get resources of event.
     * @param int $eventid
     * @return array
     * @throws dml_exception
     */
    public static function get_resources_of_event(int $eventid) {
        global $DB;
        $resources = $DB->get_records_sql('
            SELECT er.resourceid, er.amount, r.name, r.categoryid
            FROM {bookit_resource} r JOIN {bookit_event_resources} er ON er.resourceid = r.id
            WHERE er.eventid = :eventid', ['eventid' => $eventid]);
        return $resources;
    }

    /**
     * Get resources.
     *
     * @return array[]
     * @throws dml_exception
     */
    public static function get_resources(): array {
        global $DB;
        $records = $DB->get_records_sql(
            'SELECT r.id resource_id, r.name resource_name, r.description resource_desc, r.amount resource_amount,
                    c.id category_id, c.name category_name, c.description category_desc
                    FROM {bookit_resource} r LEFT JOIN {bookit_resource_categories} c ON c.id = r.categoryid'
        );
        $resources = [];
        foreach ($records as $record) {
            if (!isset($resources[$record->category_name])) {
                $resources[$record->category_name] = [
                        'category_id' => $record->category_id,
                        'category_desc' => $record->category_desc,
                        'resources' => [$record->resource_id => [
                                'name' => $record->resource_name,
                                'desc' => $record->resource_desc,
                                'amount' => $record->resource_amount,
                        ]],
                ];
            } else {
                $resources[$record->category_name]['resources'][$record->resource_id] = [
                        'name' => $record->resource_name,
                        'desc' => $record->resource_desc,
                        'amount' => $record->resource_amount,
                ];
            }
        }
        return $resources;
    }
        /**
         * Get list of rooms as [id => name].
         *
         * @return array
         * @throws \dml_exception
         */
    public static function get_rooms(): array {
        global $DB;

        // Source (new, 02.02.2026): bookit_room table (persistent room).
        $rooms = [];
        $records = $DB->get_records('bookit_room', null, 'name ASC', 'id, name');
        foreach ($records as $r) {
            $rooms[(int)$r->id] = $r->name;
        }
        if (!empty($rooms)) {
            return $rooms;
        }

        // Fallback: legacy rooms stored as resources.
        $resources = self::get_resources();
        if (!empty($resources['Rooms']['resources'])) {
            foreach ($resources['Rooms']['resources'] as $rid => $r) {
                $rooms[$rid] = $r['name'];
            }
        }
        return $rooms;
    }

    // Category CRUD Methods.

    /**
     * Get all resource categories.
     *
     * @param bool $activeonly Filter only active categories
     * @return array Array of bookit_resource_categories objects
     * @throws dml_exception
     */
    public static function get_all_categories(bool $activeonly = false): array {
        global $DB;

        $conditions = [];
        if ($activeonly) {
            $conditions['active'] = 1;
        }

        $records = $DB->get_records('bookit_resource_categories', $conditions, 'sortorder ASC');

        $categories = [];
        foreach ($records as $record) {
            $categories[] = self::category_from_record($record);
        }

        return $categories;
    }

    /**
     * Get a single category by ID.
     *
     * @param int $id Category ID
     * @return bookit_resource_categories|null Category object or null if not found
     * @throws dml_exception
     */
    public static function get_category(int $id): ?bookit_resource_categories {
        global $DB;

        $record = $DB->get_record('bookit_resource_categories', ['id' => $id]);

        if (!$record) {
            return null;
        }

        return self::category_from_record($record);
    }

    /**
     * Save a category (insert or update).
     *
     * @param bookit_resource_categories $category Category to save
     * @param int $userid User performing the action
     * @return int Category ID
     * @throws \moodle_exception If validation fails
     * @throws dml_exception
     */
    public static function save_category(bookit_resource_categories $category, int $userid): int {
        global $DB;

        self::validate_category($category);

        $record = new \stdClass();
        $record->name = $category->get_name();
        $record->description = $category->get_description();
        $record->sortorder = $category->get_sortorder();
        $record->active = $category->is_active() ? 1 : 0;
        $record->usermodified = $userid;

        if ($category->get_id() === null) {
            // Insert new category.
            $record->timecreated = time();
            $record->timemodified = time();
            $id = $DB->insert_record('bookit_resource_categories', $record);
        } else {
            // Update existing category.
            $record->id = $category->get_id();
            $record->timemodified = time();
            $DB->update_record('bookit_resource_categories', $record);
            $id = $record->id;
        }

        return $id;
    }

    /**
     * Delete a category.
     *
     * @param int $id Category ID
     * @return void
     * @throws \moodle_exception If category has resources
     * @throws dml_exception
     */
    public static function delete_category(int $id): void {
        global $DB;

        // Check if category has resources.
        $count = $DB->count_records('bookit_resource', ['categoryid' => $id]);
        if ($count > 0) {
            throw new \moodle_exception('category_has_resources', 'mod_bookit');
        }

        $DB->delete_records('bookit_resource_categories', ['id' => $id]);
    }

    /**
     * Update category sort order.
     *
     * @param array|int $categoryids Array of category IDs in desired order (key = sortorder, value = categoryid)
     *                               or single category ID
     * @param int|null $sortorder If $categoryids is int, this is the new sortorder value
     * @return void
     * @throws dml_exception
     */
    public static function update_category_sortorder($categoryids, ?int $sortorder = null): void {
        global $DB;

        // Handle single ID + sortorder.
        if (is_int($categoryids) && $sortorder !== null) {
            $DB->set_field('bookit_resource_categories', 'sortorder', $sortorder, ['id' => $categoryids]);
            return;
        }

        // Handle array.
        if (is_array($categoryids)) {
            foreach ($categoryids as $sort => $categoryid) {
                $DB->set_field('bookit_resource_categories', 'sortorder', $sort, ['id' => $categoryid]);
            }
        }
    }

    // Resource CRUD Methods.

    /**
     * Get all resources.
     *
     * @param int|null $categoryid Optional filter by category
     * @param bool $activeonly Filter only active resources
     * @return array Array of bookit_resource objects
     * @throws dml_exception
     */
    public static function get_all_resources(?int $categoryid = null, bool $activeonly = false): array {
        global $DB;

        $conditions = [];
        if ($categoryid !== null) {
            $conditions['categoryid'] = $categoryid;
        }
        if ($activeonly) {
            $conditions['active'] = 1;
        }

        $records = $DB->get_records('bookit_resource', $conditions, 'sortorder ASC, name ASC');

        $resources = [];
        foreach ($records as $record) {
            $resources[] = self::resource_from_record($record);
        }

        return $resources;
    }

    /**
     * Get a single resource by ID.
     *
     * @param int $id Resource ID
     * @return bookit_resource|null Resource object or null if not found
     * @throws dml_exception
     */
    public static function get_resource_by_id(int $id): ?bookit_resource {
        global $DB;

        $record = $DB->get_record('bookit_resource', ['id' => $id]);

        if (!$record) {
            return null;
        }

        return self::resource_from_record($record);
    }

    /**
     * Save a resource (insert or update).
     *
     * @param bookit_resource $resource Resource to save
     * @param int $userid User performing the action
     * @return int Resource ID
     * @throws \moodle_exception If validation fails
     * @throws dml_exception
     */
    public static function save_resource(bookit_resource $resource, int $userid): int {
        global $DB;

        self::validate_resource($resource);

        $record = new \stdClass();
        $record->name = $resource->get_name();
        $record->description = $resource->get_description();
        $record->categoryid = $resource->get_categoryid();
        $record->amount = $resource->get_amount();
        $record->amountirrelevant = $resource->is_amountirrelevant() ? 1 : 0;
        $record->sortorder = $resource->get_sortorder();
        $record->active = $resource->is_active() ? 1 : 0;
        $record->roomids = ($resource->get_roomids() !== null) ? json_encode($resource->get_roomids()) : null;
        $record->usermodified = $userid;

        if ($resource->get_id() === null) {
            // Insert new resource.
            $record->timecreated = time();
            $record->timemodified = time();
            $id = $DB->insert_record('bookit_resource', $record);
        } else {
            // Update existing resource.
            $record->id = $resource->get_id();
            $record->timemodified = time();
            $DB->update_record('bookit_resource', $record);
            $id = $record->id;
        }

        return $id;
    }

    /**
     * Delete a resource.
     *
     * @param int $id Resource ID
     * @return void
     * @throws dml_exception
     */
    public static function delete_resource(int $id): void {
        global $DB;

        $DB->delete_records('bookit_resource', ['id' => $id]);
    }

    /**
     * Update resource sort order.
     *
     * @param array|int $resourceids Array of resource IDs in desired order (key = sortorder, value = resourceid)
     *                               or single resource ID
     * @param int $categoryid Category ID for validation (required if array) or sortorder value (if single ID)
     * @param int|null $sortorder If $resourceids is int, this is the new sortorder value
     * @return void
     * @throws dml_exception
     */
    public static function update_resource_sortorder($resourceids, int $categoryid, ?int $sortorder = null): void {
        global $DB;

        // Handle single ID + sortorder.
        if (is_int($resourceids) && $sortorder !== null) {
            $DB->set_field('bookit_resource', 'sortorder', $sortorder, ['id' => $resourceids]);
            return;
        }

        // Handle array.
        if (is_array($resourceids)) {
            foreach ($resourceids as $sort => $resourceid) {
                $DB->set_field('bookit_resource', 'sortorder', $sort, ['id' => $resourceid, 'categoryid' => $categoryid]);
            }
        }
    }

    // Helper Methods.

    /**
     * Create category entity from database record.
     *
     * @param \stdClass $record Database record
     * @return bookit_resource_categories Category entity
     */
    private static function category_from_record(\stdClass $record): bookit_resource_categories {
        return new bookit_resource_categories(
            isset($record->id) ? (int)$record->id : null,
            $record->name ?? '',
            $record->description ?? null,
            (int)($record->sortorder ?? 0),
            (bool)($record->active ?? 1),
            (int)($record->timecreated ?? 0),
            (int)($record->timemodified ?? 0),
            (int)($record->usermodified ?? 0)
        );
    }

    /**
     * Create resource entity from database record.
     *
     * @param \stdClass $record Database record
     * @return bookit_resource Resource entity
     */
    private static function resource_from_record(\stdClass $record): bookit_resource {
        $roomids = null;
        if (isset($record->roomids) && $record->roomids !== null) {
            $decoded = json_decode($record->roomids, true);
            $roomids = is_array($decoded) ? $decoded : null;
        }

        return new bookit_resource(
            isset($record->id) ? (int)$record->id : null,
            $record->name ?? '',
            $record->description ?? null,
            (int)($record->categoryid ?? 0),
            (int)($record->amount ?? 0),
            (bool)($record->amountirrelevant ?? 0),
            (int)($record->sortorder ?? 0),
            (bool)($record->active ?? 1),
            $roomids,
            (int)($record->timecreated ?? 0),
            (int)($record->timemodified ?? 0),
            (int)($record->usermodified ?? 0)
        );
    }

    // Validation Methods.

    /**
     * Validate category data.
     *
     * @param bookit_resource_categories $category Category to validate
     * @return void
     * @throws \moodle_exception If validation fails
     */
    private static function validate_category(bookit_resource_categories $category): void {
        if (empty(trim($category->get_name()))) {
            throw new \moodle_exception('category_name_required', 'mod_bookit');
        }

        if ($category->get_sortorder() < 0) {
            throw new \moodle_exception('sortorder_must_be_positive', 'mod_bookit');
        }

        // Check for duplicate category name.
        global $DB;
        $params = ['name' => $category->get_name()];
        if ($category->get_id() !== null) {
            // Exclude current category when editing.
            $sql = "SELECT id FROM {bookit_resource_categories} WHERE name = :name AND id != :id";
            $params['id'] = $category->get_id();
        } else {
            $sql = "SELECT id FROM {bookit_resource_categories} WHERE name = :name";
        }

        if ($DB->record_exists_sql($sql, $params)) {
            throw new \moodle_exception('error_category_name_exists', 'mod_bookit');
        }
    }

    /**
     * Validate resource data.
     *
     * @param bookit_resource $resource Resource to validate
     * @return void
     * @throws \moodle_exception If validation fails
     * @throws dml_exception
     */
    private static function validate_resource(bookit_resource $resource): void {
        if (empty(trim($resource->get_name()))) {
            throw new \moodle_exception('resource_name_required', 'mod_bookit');
        }

        if ($resource->get_categoryid() <= 0) {
            throw new \moodle_exception('resource_category_required', 'mod_bookit');
        }

        // Check if category exists.
        $category = self::get_category($resource->get_categoryid());
        if ($category === null) {
            throw new \moodle_exception('resource_category_not_found', 'mod_bookit');
        }

        if (!$resource->is_amountirrelevant() && $resource->get_amount() < 0) {
            throw new \moodle_exception('resource_amount_must_be_positive', 'mod_bookit');
        }

        if ($resource->get_sortorder() < 0) {
            throw new \moodle_exception('sortorder_must_be_positive', 'mod_bookit');
        }
    }

    // Booking Form Helper Methods.

    /**
     * Get active resources grouped by category for booking form.
     *
     * @return array Structured data for template [
     *     [
     *         'category' => ['id' => int, 'name' => string, 'sortorder' => int],
     *         'resources' => [
     *             ['id' => int, 'name' => string, 'description' => string, 'amount' => int,
     *              'amountirrelevant' => bool, 'sortorder' => int],
     *             ...
     *         ]
     *     ],
     *     ...
     * ]
     * @throws dml_exception
     */
    public static function get_active_resources_grouped(): array {
        global $DB;

        $sql = "
            SELECT
                r.id as resource_id,
                r.name as resource_name,
                r.description as resource_description,
                r.amount as resource_amount,
                r.amountirrelevant as resource_amountirrelevant,
                r.sortorder as resource_sortorder,
                c.id as category_id,
                c.name as category_name,
                c.sortorder as category_sortorder
            FROM {bookit_resource} r
            LEFT JOIN {bookit_resource_categories} c ON c.id = r.categoryid
            WHERE r.active = 1 AND c.active = 1
            ORDER BY c.sortorder ASC, r.sortorder ASC
        ";

        $records = $DB->get_records_sql($sql);

        $grouped = [];
        foreach ($records as $record) {
            $catid = $record->category_id;

            if (!isset($grouped[$catid])) {
                $grouped[$catid] = [
                    'category' => [
                        'id' => $catid,
                        'name' => $record->category_name,
                        'sortorder' => $record->category_sortorder,
                    ],
                    'resources' => [],
                ];
            }

            $grouped[$catid]['resources'][] = [
                'id' => $record->resource_id,
                'name' => $record->resource_name,
                'description' => $record->resource_description,
                'amount' => $record->resource_amount,
                'amountirrelevant' => (bool)$record->resource_amountirrelevant,
                'sortorder' => $record->resource_sortorder,
            ];
        }

        return array_values($grouped);
    }

    /**
     * Get room-resource mapping for JavaScript filtering.
     *
     * @return array [room_id => [resource_id, resource_id, ...], ...]
     * @throws dml_exception
     */
    public static function get_room_resource_map(): array {
        global $DB;

        $records = $DB->get_records('bookit_resource_room', null, '', 'roomid, resourceid');

        $map = [];
        foreach ($records as $record) {
            if (!isset($map[$record->roomid])) {
                $map[$record->roomid] = [];
            }
            $map[$record->roomid][] = (int)$record->resourceid;
        }

        return $map;
    }

    /**
     * Get room details for each resource (for room icon display).
     *
     * @return array [resource_id => [['id' => int, 'name' => string, 'shortname' => string, 'color' => string], ...], ...]
     * @throws dml_exception
     */
    public static function get_resource_rooms(): array {
        global $DB;

        $sql = "
            SELECT
                rr.resourceid,
                ro.id as room_id,
                ro.name as room_name,
                ro.shortname as room_shortname,
                ro.color as room_color
            FROM {bookit_resource_room} rr
            LEFT JOIN {bookit_room} ro ON ro.id = rr.roomid
            ORDER BY rr.resourceid ASC, ro.name ASC
        ";

        $records = $DB->get_records_sql($sql);

        $rooms = [];
        foreach ($records as $record) {
            $resid = $record->resourceid;

            if (!isset($rooms[$resid])) {
                $rooms[$resid] = [];
            }

            $rooms[$resid][] = [
                'id' => $record->room_id,
                'name' => $record->room_name,
                'shortname' => $record->room_shortname,
                'color' => $record->room_color,
            ];
        }

        return $rooms;
    }
}
