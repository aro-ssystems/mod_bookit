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
 * Demo dataset generate/reset helper.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local;

use mod_bookit\local\manager\weekplan_manager;
use mod_bookit\local\persistent\room;

/**
 * Admin DX helper for demo generate and reset (separate from role install).
 */
class demo_data_helper {
    /** Prefix for demo room shortnames. */
    public const DEMO_ROOM_SHORTNAME_PREFIX = 'DEMO';

    /**
     * Whether only fresh-install baseline plugin content is present.
     *
     * @return bool
     */
    public static function is_baseline_only(): bool {
        global $DB;

        $roomcount = $DB->count_records('bookit_room');
        if ($roomcount !== 1) {
            return false;
        }
        $room = $DB->get_record('bookit_room', [], '*', MUST_EXIST);
        if ((string)$room->name !== install_helper::DEFAULT_ROOM_NAME) {
            return false;
        }

        $weekplancount = $DB->count_records('bookit_weekplan');
        if ($weekplancount !== 1) {
            return false;
        }
        $weekplan = $DB->get_record('bookit_weekplan', [], '*', MUST_EXIST);
        if ((string)$weekplan->name !== install_helper::DEFAULT_WEEKPLAN_NAME) {
            return false;
        }

        if ($DB->count_records('bookit_event') > 0) {
            return false;
        }
        if ($DB->count_records('bookit_resource') > 0 || $DB->count_records('bookit_resource_category') > 0) {
            return false;
        }
        if (
            $DB->count_records('bookit_checklist_master') > 0
                || $DB->count_records('bookit_checklist_category') > 0
                || $DB->count_records('bookit_checklist_item') > 0
        ) {
            return false;
        }

        return true;
    }

    /**
     * Generate demo data when the instance is baseline-only.
     *
     * @return array{status:string,message:string,counts:array<string,int>}
     */
    public static function generate(): array {
        global $DB, $USER;

        install_helper::ensure_fresh_install_baseline(false);

        if (!self::is_baseline_only()) {
            return [
                'status' => 'refused',
                'message' => 'refuse_dirty',
                'counts' => self::collect_counts(),
            ];
        }

        install_helper::create_default_resources(false, false);
        install_helper::create_default_checklists(false, false);
        self::seed_demo_rooms();
        self::seed_current_week_events();

        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 1, 'mod_bookit');
        set_config(install_helper::CONFIG_CHECKLIST_ENABLED, 1, 'mod_bookit');

        return [
            'status' => 'success',
            'message' => 'generated',
            'counts' => self::collect_counts(),
            'actor' => (int)($USER->id ?? 0),
        ];
    }

    /**
     * Reset plugin domain content to baseline and disable optional modules.
     *
     * @return array{status:string,message:string,counts:array<string,int>}
     */
    public static function reset(): array {
        global $DB;

        self::purge_non_baseline_content();
        install_helper::ensure_fresh_install_baseline(false);

        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 0, 'mod_bookit');
        set_config(install_helper::CONFIG_CHECKLIST_ENABLED, 0, 'mod_bookit');

        return [
            'status' => 'success',
            'message' => 'reset',
            'counts' => self::collect_counts(),
        ];
    }

    /**
     * Collect simple domain counts for reports/tests.
     *
     * @return array<string,int>
     */
    public static function collect_counts(): array {
        global $DB;

        return [
            'rooms' => $DB->count_records('bookit_room'),
            'weekplans' => $DB->count_records('bookit_weekplan'),
            'resource_categories' => $DB->count_records('bookit_resource_category'),
            'resources' => $DB->count_records('bookit_resource'),
            'checklist_categories' => $DB->count_records('bookit_checklist_category'),
            'checklist_items' => $DB->count_records('bookit_checklist_item'),
            'events' => $DB->count_records('bookit_event'),
            'resourcesenabled' => (int)get_config('mod_bookit', install_helper::CONFIG_RESOURCES_ENABLED),
            'checklistenabled' => (int)get_config('mod_bookit', install_helper::CONFIG_CHECKLIST_ENABLED),
        ];
    }

    /**
     * Create additional DEMO rooms (and a second weekplan) so ≥2 rooms exist.
     *
     * @return void
     */
    private static function seed_demo_rooms(): void {
        global $DB;

        $now = time();
        $userid = self::actor_userid();

        $demorooms = [
            [
                'name' => 'DEMO Lecture Hall A',
                'shortname' => self::DEMO_ROOM_SHORTNAME_PREFIX . 'A',
                'roommode' => room::MODE_SLOTS,
                'seats' => 120,
            ],
            [
                'name' => 'DEMO Seminar Room B',
                'shortname' => self::DEMO_ROOM_SHORTNAME_PREFIX . 'B',
                'roommode' => room::MODE_TOP_TO_BOTTOM,
                'seats' => 40,
            ],
        ];

        $weekplan = (object)[
            'name' => 'DEMO Weekplan Extended',
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $weekplanid = (int)$DB->insert_record('bookit_weekplan', $weekplan);
        weekplan_manager::save_string_weekplan_to_db(
            "Mo 08:00-18:00\nDi 08:00-18:00\nMi 08:00-18:00\nDo 08:00-18:00\nFr 08:00-16:00",
            $weekplanid,
            $userid
        );

        foreach ($demorooms as $demo) {
            if ($DB->record_exists('bookit_room', ['shortname' => $demo['shortname']])) {
                continue;
            }
            $record = (object)[
                'name' => $demo['name'],
                'shortname' => $demo['shortname'],
                'description' => 'Demo room for BookIt training.',
                'location' => 'DEMO',
                'eventcolor' => '',
                'active' => 1,
                'roommode' => $demo['roommode'],
                'seats' => $demo['seats'],
                'extratimebefore' => null,
                'extratimeafter' => null,
                'preventoverlap' => room::OVERLAPPING_ALLOW_NONE,
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $roomid = (int)$DB->insert_record('bookit_room', $record);
            $DB->insert_record('bookit_weekplan_room', (object)[
                'weekplanid' => $weekplanid,
                'roomid' => $roomid,
                'starttime' => $now - DAYSECS,
                'endtime' => null,
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Seed a few events in the current calendar week.
     *
     * @return void
     */
    private static function seed_current_week_events(): void {
        global $DB, $USER;

        $rooms = array_values($DB->get_records('bookit_room', ['active' => 1], 'id ASC'));
        if (empty($rooms)) {
            return;
        }

        $userid = self::actor_userid();
        $weekstart = strtotime('monday this week');
        if ($weekstart === false) {
            $weekstart = strtotime('today');
        }

        $events = [
            [
                'name' => 'DEMO Mathematics Exam',
                'dayoffset' => 0,
                'starthour' => 9,
                'durationhours' => 3,
                'status' => 1,
                'roomidx' => 0,
            ],
            [
                'name' => 'DEMO Computer Science Exam',
                'dayoffset' => 1,
                'starthour' => 10,
                'durationhours' => 2,
                'status' => 0,
                'roomidx' => 1,
            ],
            [
                'name' => 'DEMO Literature Seminar',
                'dayoffset' => 2,
                'starthour' => 14,
                'durationhours' => 3,
                'status' => 1,
                'roomidx' => 2,
            ],
        ];

        $now = time();
        foreach ($events as $data) {
            $room = $rooms[$data['roomidx'] % count($rooms)];
            $start = $weekstart + ($data['dayoffset'] * DAYSECS) + ($data['starthour'] * HOURSECS);
            $end = $start + ($data['durationhours'] * HOURSECS);
            $event = (object)[
                'name' => $data['name'],
                'starttime' => $start,
                'endtime' => $end,
                'duration' => $end - $start,
                'roomid' => $room->id,
                'participantsamount' => 30,
                'bookingstatus' => $data['status'],
                'personinchargeid' => $userid,
                'usercreated' => $userid,
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('bookit_event', $event);
        }
    }

    /**
     * Delete non-baseline plugin content (events, resources, checklists, extra rooms/weekplans).
     *
     * @return void
     */
    private static function purge_non_baseline_content(): void {
        global $DB;

        $DB->delete_records('bookit_event_checklist_state');
        $DB->delete_records('bookit_event_resource');
        if ($DB->get_manager()->table_exists('bookit_event_history')) {
            $DB->delete_records('bookit_event_history');
        }
        $DB->delete_records('bookit_event');

        if ($DB->get_manager()->table_exists('bookit_notification_slots')) {
            $DB->delete_records('bookit_notification_slots');
        }
        $DB->delete_records('bookit_checklist_item');
        $DB->delete_records('bookit_checklist_category');
        $DB->delete_records('bookit_checklist_master');

        if ($DB->get_manager()->table_exists('bookit_resource_settings')) {
            $DB->delete_records('bookit_resource_settings');
        }
        $DB->delete_records('bookit_resource');
        $DB->delete_records('bookit_resource_category');

        if ($DB->get_manager()->table_exists('bookit_blocker')) {
            $DB->delete_records('bookit_blocker');
        }

        $defaultroom = null;
        foreach ($DB->get_records('bookit_room') as $roomcandidate) {
            if ((string)$roomcandidate->name === install_helper::DEFAULT_ROOM_NAME) {
                $defaultroom = $roomcandidate;
                break;
            }
        }
        $defaultroomid = $defaultroom ? (int)$defaultroom->id : 0;

        $defaultweekplan = null;
        foreach ($DB->get_records('bookit_weekplan') as $weekplancandidate) {
            if ((string)$weekplancandidate->name === install_helper::DEFAULT_WEEKPLAN_NAME) {
                $defaultweekplan = $weekplancandidate;
                break;
            }
        }
        $defaultweekplanid = $defaultweekplan ? (int)$defaultweekplan->id : 0;

        // Drop weekplan-room links that are not baseline.
        $assignments = $DB->get_records('bookit_weekplan_room');
        foreach ($assignments as $assignment) {
            $keep = $defaultroomid > 0
                && $defaultweekplanid > 0
                && (int)$assignment->roomid === $defaultroomid
                && (int)$assignment->weekplanid === $defaultweekplanid;
            if (!$keep) {
                $DB->delete_records('bookit_weekplan_room', ['id' => $assignment->id]);
            }
        }

        // Delete non-default rooms.
        foreach ($DB->get_records('bookit_room') as $roomrecord) {
            if ((string)$roomrecord->name === install_helper::DEFAULT_ROOM_NAME) {
                continue;
            }
            $DB->delete_records('bookit_room', ['id' => $roomrecord->id]);
        }

        // Delete non-default weekplans and their slots.
        foreach ($DB->get_records('bookit_weekplan') as $weekplan) {
            if ((string)$weekplan->name === install_helper::DEFAULT_WEEKPLAN_NAME) {
                continue;
            }
            $DB->delete_records('bookit_weekplanslot', ['weekplanid' => $weekplan->id]);
            $DB->delete_records('bookit_weekplan_room', ['weekplanid' => $weekplan->id]);
            $DB->delete_records('bookit_weekplan', ['id' => $weekplan->id]);
        }
    }

    /**
     * Current actor user id for seeded records (fallback guest/admin id 2).
     *
     * @return int
     */
    private static function actor_userid(): int {
        global $USER;
        return !empty($USER->id) ? (int)$USER->id : 2;
    }
}
