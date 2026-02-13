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
 * Resource checklist manager class.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use dml_exception;
use mod_bookit\local\entity\bookit_resource_checklist;

/**
 * Resource checklist manager class.
 *
 * Manages CRUD operations for resource checklist metadata and automatic
 * generation of checklist entries from the resources table.
 */
class resource_checklist_manager {
    /**
     * Get all resource checklist items.
     *
     * @param bool $activeonly If true, only return active items
     * @return array Array of checklist items with resource data joined
     * @throws dml_exception
     */
    public static function get_all_checklist_items(bool $activeonly = false): array {
        global $DB;

        $conditions = $activeonly ? 'WHERE rc.active = 1' : '';

        $sql = "SELECT rc.id, rc.resourceid, rc.duedate, rc.duedatetype,
                       rc.sortorder, rc.active, rc.beforedueid, rc.whendueid,
                       rc.overdueid, rc.whendoneid,
                       r.name, r.description, r.categoryid, r.amount,
                       r.amountirrelevant, r.active as resource_active
                FROM {bookit_resource_checklist} rc
                JOIN {bookit_resource} r ON r.id = rc.resourceid
                $conditions
                ORDER BY rc.sortorder ASC";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get checklist item by ID.
     *
     * @param int $id Checklist item ID
     * @return bookit_resource_checklist|null
     * @throws dml_exception
     */
    public static function get_checklist_item(int $id): ?bookit_resource_checklist {
        global $DB;

        $record = $DB->get_record('bookit_resource_checklist', ['id' => $id]);
        if (!$record) {
            return null;
        }

        return self::record_to_entity($record);
    }

    /**
     * Get checklist item by ID (alias for get_checklist_item).
     *
     * @param int $id Checklist item ID
     * @return bookit_resource_checklist|null
     * @throws dml_exception
     */
    public static function get_checklist_item_by_id(int $id): ?bookit_resource_checklist {
        return self::get_checklist_item($id);
    }

    /**
     * Update checklist item (alias for save_checklist_item).
     *
     * @param bookit_resource_checklist $item Checklist item
     * @param int $userid User ID
     * @return int Item ID
     * @throws dml_exception
     */
    public static function update_checklist_item(bookit_resource_checklist $item, int $userid): int {
        return self::save_checklist_item($item, $userid);
    }

    /**
     * Get checklist item by resource ID.
     *
     * @param int $resourceid Resource ID
     * @return bookit_resource_checklist|null
     * @throws dml_exception
     */
    public static function get_checklist_item_by_resource(int $resourceid): ?bookit_resource_checklist {
        global $DB;

        $record = $DB->get_record('bookit_resource_checklist', ['resourceid' => $resourceid]);
        if (!$record) {
            return null;
        }

        return self::record_to_entity($record);
    }

    /**
     * Save checklist item (insert or update).
     *
     * @param bookit_resource_checklist $item Checklist item
     * @param int $userid User ID
     * @return int Item ID
     * @throws dml_exception
     */
    public static function save_checklist_item(bookit_resource_checklist $item, int $userid): int {
        global $DB;

        $record = new \stdClass();
        $record->resourceid = $item->get_resourceid();
        $record->duedate = $item->get_duedate();
        $record->duedatetype = $item->get_duedatetype();
        $record->sortorder = $item->get_sortorder();
        $record->active = $item->is_active() ? 1 : 0;
        $record->beforedueid = $item->get_beforedueid();
        $record->whendueid = $item->get_whendueid();
        $record->overdueid = $item->get_overdueid();
        $record->whendoneid = $item->get_whendoneid();
        $record->usermodified = $userid;
        $record->timemodified = time();

        if ($item->get_id()) {
            // Update existing.
            $record->id = $item->get_id();
            $DB->update_record('bookit_resource_checklist', $record);
            return $item->get_id();
        } else {
            // Insert new.
            $record->timecreated = time();
            $id = $DB->insert_record('bookit_resource_checklist', $record);
            $item->set_id($id);
            return $id;
        }
    }

    /**
     * Delete checklist item.
     *
     * @param int $id Checklist item ID
     * @return bool Success
     * @throws dml_exception
     */
    public static function delete_checklist_item(int $id): bool {
        global $DB;
        return $DB->delete_records('bookit_resource_checklist', ['id' => $id]);
    }

    /**
     * Generate checklist entries for all resources that don't have one yet.
     *
     * Creates a checklist entry for each resource in the bookit_resource table
     * that doesn't already have a corresponding entry in bookit_resource_checklist.
     *
     * @param int $userid User ID for audit
     * @return int Number of entries created
     * @throws dml_exception
     */
    public static function auto_generate_checklist(int $userid): int {
        global $DB;

        // Find resources without checklist entries.
        $sql = "SELECT r.id
                FROM {bookit_resource} r
                LEFT JOIN {bookit_resource_checklist} rc ON rc.resourceid = r.id
                WHERE rc.id IS NULL
                ORDER BY r.sortorder ASC";

        $resources = $DB->get_records_sql($sql);

        if (empty($resources)) {
            return 0;
        }

        $count = 0;
        $time = time();

        // Get max sortorder to append new items.
        $maxsortorder = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {bookit_resource_checklist}"
        );
        $sortorder = $maxsortorder ? $maxsortorder + 1 : 0;

        foreach ($resources as $resource) {
            $record = new \stdClass();
            $record->resourceid = $resource->id;
            $record->duedate = null;
            $record->duedatetype = null;
            $record->sortorder = $sortorder++;
            $record->active = 1;
            $record->beforedueid = null;
            $record->whendueid = null;
            $record->overdueid = null;
            $record->whendoneid = null;
            $record->usermodified = $userid;
            $record->timecreated = $time;
            $record->timemodified = $time;

            $DB->insert_record('bookit_resource_checklist', $record);
            $count++;
        }

        return $count;
    }

    /**
     * Create checklist entry for a specific resource.
     *
     * @param int $resourceid Resource ID
     * @param int $userid User ID
     * @return int Checklist item ID
     * @throws dml_exception
     */
    public static function create_checklist_for_resource(int $resourceid, int $userid): int {
        global $DB;

        // Check if already exists.
        if ($DB->record_exists('bookit_resource_checklist', ['resourceid' => $resourceid])) {
            throw new \coding_exception('Checklist entry already exists for resource ' . $resourceid);
        }

        // Get max sortorder.
        $maxsortorder = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {bookit_resource_checklist}"
        );
        $sortorder = $maxsortorder ? $maxsortorder + 1 : 0;

        $record = new \stdClass();
        $record->resourceid = $resourceid;
        $record->duedate = null;
        $record->duedatetype = null;
        $record->sortorder = $sortorder;
        $record->active = 1;
        $record->beforedueid = null;
        $record->whendueid = null;
        $record->overdueid = null;
        $record->whendoneid = null;
        $record->usermodified = $userid;
        $record->timecreated = time();
        $record->timemodified = time();

        return $DB->insert_record('bookit_resource_checklist', $record);
    }

    /**
     * Convert database record to entity object.
     *
     * @param \stdClass $record Database record
     * @return bookit_resource_checklist
     */
    private static function record_to_entity(\stdClass $record): bookit_resource_checklist {
        return new bookit_resource_checklist(
            (int)$record->id,
            (int)$record->resourceid,
            isset($record->duedate) ? (int)$record->duedate : null,
            $record->duedatetype ?? null,
            (int)($record->sortorder ?? 0),
            (bool)($record->active ?? 1),
            isset($record->beforedueid) ? (int)$record->beforedueid : null,
            isset($record->whendueid) ? (int)$record->whendueid : null,
            isset($record->overdueid) ? (int)$record->overdueid : null,
            isset($record->whendoneid) ? (int)$record->whendoneid : null,
            (int)($record->timecreated ?? 0),
            (int)($record->timemodified ?? 0),
            (int)($record->usermodified ?? 0)
        );
    }
}
