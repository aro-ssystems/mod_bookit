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
 * Manager for event checklist state (per-event check-off state for master checklist items).
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use stdClass;

/**
 * Manages per-event check-off state for master checklist items.
 */
class event_checklist_state_manager {
    /** @var string DB table name */
    const TABLE = 'bookit_event_checklist_state';

    /**
     * Get all state records for an event.
     *
     * Returns a map of checklistitemid => done (bool).
     *
     * @param int $eventid
     * @param int $userid
     * @return array Map of checklistitemid => bool
     */
    public static function get_state_for_event(int $eventid, int $userid): array {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['eventid' => $eventid, 'userid' => $userid]);
        $state = [];
        foreach ($records as $record) {
            $state[(int)$record->checklistitemid] = (bool)$record->done;
        }
        return $state;
    }

    /**
     * Set the done state for one checklist item in an event.
     *
     * Creates or updates the state record.
     *
     * @param int $eventid
     * @param int $checklistitemid
     * @param int $userid
     * @param bool $done
     * @return void
     */
    public static function set_item_state(int $eventid, int $checklistitemid, int $userid, bool $done): void {
        global $DB, $USER;
        $now = time();

        $existing = $DB->get_record(self::TABLE, [
            'eventid'         => $eventid,
            'checklistitemid' => $checklistitemid,
            'userid'          => $userid,
        ]);

        if ($existing) {
            $existing->done         = (int)$done;
            $existing->usermodified = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
        } else {
            $record = new stdClass();
            $record->eventid         = $eventid;
            $record->checklistitemid = $checklistitemid;
            $record->userid          = $userid;
            $record->done            = (int)$done;
            $record->usermodified    = $USER->id;
            $record->timecreated     = $now;
            $record->timemodified    = $now;
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Get progress for an event: how many items are done vs total.
     *
     * Total is the count of checklist items applicable to the event's master checklist.
     *
     * @param int $eventid
     * @param int $masterid Master checklist ID
     * @return array ['done' => int, 'total' => int, 'percent' => int]
     */
    public static function get_progress_for_event(int $eventid, int $masterid): array {
        global $DB;

        $total = $DB->count_records('bookit_checklist_item', ['masterid' => $masterid]);
        if ($total === 0) {
            return ['done' => 0, 'total' => 0, 'percent' => 0];
        }

        $done = $DB->count_records(self::TABLE, ['eventid' => $eventid, 'done' => 1]);

        return [
            'done'    => $done,
            'total'   => $total,
            'percent' => (int)round(($done / $total) * 100),
        ];
    }
}
