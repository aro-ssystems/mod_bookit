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
 * Event-resource relationship manager.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use dml_exception;
use mod_bookit\local\entity\bookit_event_resource;

/**
 * Event-resource relationship manager.
 *
 * Manages CRUD operations for event-resource relationships (junction table).
 */
class event_resource_manager {
    /**
     * Get all resources for an event.
     *
     * @param int $eventid Event ID
     * @return array Array of event_resource entities
     * @throws dml_exception
     */
    public static function get_resources_for_event(int $eventid): array {
        global $DB;

        $records = $DB->get_records('bookit_event_resource', ['eventid' => $eventid]);

        $entities = [];
        foreach ($records as $record) {
            $entities[] = self::record_to_entity($record);
        }

        return $entities;
    }

    /**
     * Get all events using a resource.
     *
     * @param int $resourceid Resource ID
     * @return array Array of event_resource entities
     * @throws dml_exception
     */
    public static function get_events_for_resource(int $resourceid): array {
        global $DB;

        $records = $DB->get_records('bookit_event_resource', ['resourceid' => $resourceid]);

        $entities = [];
        foreach ($records as $record) {
            $entities[] = self::record_to_entity($record);
        }

        return $entities;
    }

    /**
     * Get a specific event-resource relationship.
     *
     * @param int $eventid Event ID
     * @param int $resourceid Resource ID
     * @return bookit_event_resource|null
     * @throws dml_exception
     */
    public static function get_event_resource(int $eventid, int $resourceid): ?bookit_event_resource {
        global $DB;

        $record = $DB->get_record('bookit_event_resource', [
            'eventid' => $eventid,
            'resourceid' => $resourceid,
        ]);

        if (!$record) {
            return null;
        }

        return self::record_to_entity($record);
    }

    /**
     * Add a resource to an event.
     *
     * @param int $eventid Event ID
     * @param int $resourceid Resource ID
     * @param int $quantity Quantity
     * @param int $userid User ID
     * @param string $status Status
     * @return int Record ID
     * @throws dml_exception
     */
    public static function add_resource_to_event(
        int $eventid,
        int $resourceid,
        int $quantity,
        int $userid,
        string $status = 'requested'
    ): int {
        global $DB;

        $time = time();
        $record = new \stdClass();
        $record->eventid = $eventid;
        $record->resourceid = $resourceid;
        $record->quantity = $quantity;
        $record->status = $status;
        $record->usermodified = $userid;
        $record->timecreated = $time;
        $record->timemodified = $time;

        return $DB->insert_record('bookit_event_resource', $record);
    }

    /**
     * Update resource quantity for an event.
     *
     * @param int $eventid Event ID
     * @param int $resourceid Resource ID
     * @param int $quantity New quantity
     * @param int $userid User ID
     * @param string|null $status Optional new status
     * @return bool Success
     * @throws dml_exception
     */
    public static function update_resource_quantity(
        int $eventid,
        int $resourceid,
        int $quantity,
        int $userid,
        ?string $status = null
    ): bool {
        global $DB;

        $record = $DB->get_record('bookit_event_resource', [
            'eventid' => $eventid,
            'resourceid' => $resourceid,
        ]);

        if (!$record) {
            return false;
        }

        $record->quantity = $quantity;
        if ($status !== null) {
            $record->status = $status;
        }
        $record->usermodified = $userid;
        $record->timemodified = time();

        return $DB->update_record('bookit_event_resource', $record);
    }

    /**
     * Remove a resource from an event.
     *
     * @param int $eventid Event ID
     * @param int $resourceid Resource ID
     * @return bool Success
     * @throws dml_exception
     */
    public static function remove_resource_from_event(int $eventid, int $resourceid): bool {
        global $DB;

        return $DB->delete_records('bookit_event_resource', [
            'eventid' => $eventid,
            'resourceid' => $resourceid,
        ]);
    }

    /**
     * Remove all resources from an event.
     *
     * @param int $eventid Event ID
     * @return bool Success
     * @throws dml_exception
     */
    public static function remove_all_resources_from_event(int $eventid): bool {
        global $DB;

        return $DB->delete_records('bookit_event_resource', ['eventid' => $eventid]);
    }

    /**
     * Convert database record to entity object.
     *
     * @param \stdClass $record Database record
     * @return bookit_event_resource
     */
    private static function record_to_entity(\stdClass $record): bookit_event_resource {
        return new bookit_event_resource(
            (int)$record->id,
            (int)$record->eventid,
            (int)$record->resourceid,
            (int)($record->quantity ?? 1),
            $record->status ?? 'requested',
            (int)($record->usermodified ?? 0),
            (int)($record->timecreated ?? 0),
            (int)($record->timemodified ?? 0)
        );
    }
}
