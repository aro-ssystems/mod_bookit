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
     * Get the global done state for an event (any user marks an item done counts it as done).
     *
     * Returns a map of checklistitemid => bool (true if any user marked it done).
     *
     * @param int $eventid
     * @return array checklistitemid => bool
     */
    public static function get_global_state_for_event(int $eventid): array {
        global $DB;
        $records = $DB->get_records_select(self::TABLE, 'eventid = :eventid AND done = 1', ['eventid' => $eventid]);
        $state = [];
        foreach ($records as $record) {
            $state[(int)$record->checklistitemid] = true;
        }
        return $state;
    }

    /**
     * Checklist item IDs that count for an event (detail and overview parity).
     *
     * Rule: item has a category AND (active=1 OR any state row exists for this event+item).
     *
     * @param int $eventid
     * @param int $masterid
     * @return int[]
     */
    public static function get_countable_item_ids(int $eventid, int $masterid): array {
        global $DB;

        if ($eventid <= 0 || $masterid <= 0) {
            return [];
        }

        $sql = "SELECT i.id
                  FROM {bookit_checklist_item} i
                  JOIN {bookit_checklist_category} c ON c.id = i.categoryid
                 WHERE i.masterid = :masterid
                   AND (
                        COALESCE(i.active, 1) = 1
                        OR EXISTS (
                            SELECT 1
                              FROM {bookit_event_checklist_state} s
                             WHERE s.eventid = :eventid
                               AND s.checklistitemid = i.id
                        )
                   )";

        $ids = $DB->get_fieldset_sql($sql, [
            'masterid' => $masterid,
            'eventid' => $eventid,
        ]);

        return array_map('intval', $ids ?: []);
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
     * Get progress for an event: how many distinct countable items are done vs total.
     *
     * State is global (not per-user): an item counts as done if any user marked it done.
     * Countable set matches event checklist detail (category + active-or-has-state).
     *
     * @param int $eventid
     * @param int $masterid Master checklist ID
     * @return array ['done' => int, 'total' => int, 'percent' => int]
     */
    public static function get_progress_for_event(int $eventid, int $masterid): array {
        global $DB;

        $itemids = self::get_countable_item_ids($eventid, $masterid);
        $total = count($itemids);
        if ($total === 0) {
            return ['done' => 0, 'total' => 0, 'percent' => 0];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'iid');
        $inparams['eventid'] = $eventid;
        $sql = "SELECT COUNT(DISTINCT s.checklistitemid)
                  FROM {bookit_event_checklist_state} s
                 WHERE s.eventid = :eventid
                   AND s.done = 1
                   AND s.checklistitemid $insql";
        $done = (int)$DB->count_records_sql($sql, $inparams);

        return [
            'done'    => $done,
            'total'   => $total,
            'percent' => (int)round(($done / $total) * 100),
        ];
    }

    /**
     * Get progress percent for multiple events in two queries for all events.
     *
     * Returns a map of eventid => percent (0-100). The counted items follow the same rule as
     * get_countable_item_ids(): an item with a category counts when it is active or when the
     * event has a state row for it. An item counts as done if any user marked it done.
     *
     * @param int[] $eventids List of event IDs
     * @param int $masterid Master checklist ID
     * @return array Map of eventid => percent
     */
    public static function get_progress_percent_for_events(array $eventids, int $masterid): array {
        global $DB;

        if (empty($eventids) || $masterid <= 0) {
            return [];
        }

        $eventids = array_map('intval', $eventids);
        $result = array_fill_keys($eventids, 0);

        $items = $DB->get_records_sql(
            "SELECT i.id, COALESCE(i.active, 1) AS active
               FROM {bookit_checklist_item} i
               JOIN {bookit_checklist_category} c ON c.id = i.categoryid
              WHERE i.masterid = :masterid",
            ['masterid' => $masterid]
        );
        if (empty($items)) {
            return $result;
        }

        $activecount = 0;
        foreach ($items as $item) {
            $activecount += (int)$item->active === 1 ? 1 : 0;
        }

        [$eventsql, $params] = $DB->get_in_or_equal($eventids, SQL_PARAMS_NAMED, 'eid');
        [$itemsql, $itemparams] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED, 'iid');
        $states = $DB->get_recordset_sql(
            "SELECT s.eventid, s.checklistitemid, MAX(s.done) AS done
               FROM {bookit_event_checklist_state} s
              WHERE s.eventid $eventsql
                AND s.checklistitemid $itemsql
           GROUP BY s.eventid, s.checklistitemid",
            $params + $itemparams
        );

        // Inactive items count for an event once it has a state row for them.
        $totals = array_fill_keys($eventids, $activecount);
        $done = array_fill_keys($eventids, 0);
        foreach ($states as $state) {
            $eventid = (int)$state->eventid;
            if ((int)$items[$state->checklistitemid]->active !== 1) {
                $totals[$eventid]++;
            }
            if ((int)$state->done === 1) {
                $done[$eventid]++;
            }
        }
        $states->close();

        foreach ($eventids as $eventid) {
            if ($totals[$eventid] > 0) {
                $result[$eventid] = (int)round(($done[$eventid] / $totals[$eventid]) * 100);
            }
        }
        return $result;
    }
}
