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

use mod_bookit\local\entity\masterchecklist\bookit_checklist_category;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_item;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_master;
use mod_bookit\local\entity\resource\bookit_resource_status;
use mod_bookit\local\manager\checklist_manager;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\local\manager\event_checklist_state_manager;
use mod_bookit\local\manager\event_manager;
use mod_bookit\local\manager\event_resource_manager;
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

        if ($DB->count_records('bookit_institution') !== 1) {
            return false;
        }
        $namematches = $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name');
        if (!$DB->record_exists_select('bookit_institution', $namematches, ['name' => install_helper::DEFAULT_INSTITUTION_NAME])) {
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
     * Everything is created in one transaction, so a failure leaves no partial demo data behind.
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

        $transaction = $DB->start_delegated_transaction();
        try {
            install_helper::create_default_resources(false, false);
            self::seed_demo_rooms();
            self::seed_demo_checklist();
            self::seed_current_week_events();
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            return self::rollback_report($transaction, $e);
        }

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
     * Deleting and restoring the baseline run in one transaction. If the baseline cannot be
     * restored, nothing is deleted and the result reports the failure.
     *
     * @return array{status:string,message:string,counts:array<string,int>,errors?:string[]}
     */
    public static function reset(): array {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            self::purge_non_baseline_content();
            $baseline = install_helper::ensure_fresh_install_baseline(false);
            // Any reported error means the baseline is incomplete (status partial or failed).
            if (!empty($baseline['errors'])) {
                throw new \moodle_exception(
                    'demoreset_result_failed',
                    'mod_bookit',
                    '',
                    null,
                    implode(' ', $baseline['errors'])
                );
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            return self::rollback_report($transaction, $e);
        }

        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 0, 'mod_bookit');
        set_config(install_helper::CONFIG_CHECKLIST_ENABLED, 0, 'mod_bookit');

        return [
            'status' => 'success',
            'message' => 'reset',
            'counts' => self::collect_counts(),
        ];
    }

    /**
     * Roll back a failed generate or reset and describe the failure.
     *
     * @param \moodle_transaction $transaction
     * @param \Throwable $e
     * @return array{status:string,message:string,counts:array<string,int>,errors:string[]}
     */
    private static function rollback_report(\moodle_transaction $transaction, \Throwable $e): array {
        if (!$transaction->is_disposed()) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $rethrown) {
                // Rollback always rethrows; keep the exception it hands back.
                $e = $rethrown;
            }
        }
        $errors = [$e->getMessage()];
        if ($e instanceof \moodle_exception && !empty($e->debuginfo)) {
            $errors[] = $e->debuginfo;
        }

        return [
            'status' => 'failed',
            'message' => 'failed',
            'counts' => self::collect_counts(),
            'errors' => $errors,
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
            'institutions' => $DB->count_records('bookit_institution'),
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

        $now = self::now();
        $userid = self::actor_userid();
        $weekstart = self::week_start();

        // The demo week starts on Monday, the installation may have assigned the default room later.
        $defaultroomid = $DB->get_field_select(
            'bookit_room',
            'id',
            $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name'),
            ['name' => install_helper::DEFAULT_ROOM_NAME],
            IGNORE_MULTIPLE
        );
        if ($defaultroomid) {
            $DB->set_field_select(
                'bookit_weekplan_room',
                'starttime',
                $weekstart,
                'roomid = :roomid AND starttime > :weekstart',
                ['roomid' => (int)$defaultroomid, 'weekstart' => $weekstart]
            );
        }

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
            // The weekplan must already apply on Monday, the first day of the demo week.
            $DB->insert_record('bookit_weekplan_room', (object)[
                'weekplanid' => $weekplanid,
                'roomid' => $roomid,
                'starttime' => self::week_start(),
                'endtime' => null,
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Seed the demo events of the current calendar week with resources and checklist state.
     *
     * @return void
     */
    private static function seed_current_week_events(): void {
        global $DB;

        $rooms = [
            'default' => $DB->get_field_select(
                'bookit_room',
                'id',
                $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name'),
                ['name' => install_helper::DEFAULT_ROOM_NAME]
            ),
            'a' => $DB->get_field('bookit_room', 'id', ['shortname' => self::DEMO_ROOM_SHORTNAME_PREFIX . 'A']),
            'b' => $DB->get_field('bookit_room', 'id', ['shortname' => self::DEMO_ROOM_SHORTNAME_PREFIX . 'B']),
        ];
        $resourceids = $DB->get_fieldset_sql('SELECT id FROM {bookit_resource} WHERE active = 1 ORDER BY id ASC');
        $master = checklist_manager::get_default_master();
        $itemids = $master
            ? $DB->get_fieldset_sql('SELECT id FROM {bookit_checklist_item} WHERE masterid = ? ORDER BY id ASC', [$master->id])
            : [];

        $institutionid = (int)$DB->get_field_select(
            'bookit_institution',
            'id',
            $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name'),
            ['name' => install_helper::DEFAULT_INSTITUTION_NAME]
        );
        $userid = self::actor_userid();
        $now = self::now();
        $monday = (new \DateTimeImmutable('@' . self::week_start()))->setTimezone(\core_date::get_user_timezone_object());

        // One event per combination: every booking status, every resource status, events without
        // resources, and a checklist that is complete, partly done, or open with a past due date.
        // Times lie inside the weekplan slots of each room (default room 09:00-17:00, demo rooms from 08:00).
        $events = [
            [
                'name' => 'DEMO Mathematics Exam', 'day' => 1, 'hour' => 9, 'hours' => 3, 'room' => 'default',
                'status' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
                'resource' => bookit_resource_status::REQUESTED, 'checklistdone' => 'all',
            ],
            [
                'name' => 'DEMO Computer Science Exam', 'day' => 2, 'hour' => 8, 'hours' => 2, 'room' => 'a',
                'status' => event_access_manager::BOOKINGSTATUS_IN_PROGRESS,
                'resource' => bookit_resource_status::INPROGRESS, 'checklistdone' => 'some',
            ],
            [
                'name' => 'DEMO Literature Seminar', 'day' => 0, 'hour' => 8, 'hours' => 3, 'room' => 'b',
                'status' => event_access_manager::BOOKINGSTATUS_NEW,
                'resource' => bookit_resource_status::CONFIRMED, 'checklistdone' => 'none',
            ],
            [
                'name' => 'DEMO Physics Exam', 'day' => 3, 'hour' => 8, 'hours' => 2, 'room' => 'a',
                'status' => event_access_manager::BOOKINGSTATUS_REJECTED,
                'resource' => bookit_resource_status::REJECTED, 'checklistdone' => 'none',
            ],
            [
                'name' => 'DEMO History Exam', 'day' => 4, 'hour' => 8, 'hours' => 2, 'room' => 'b',
                'status' => event_access_manager::BOOKINGSTATUS_CANCELED,
                'resource' => null, 'checklistdone' => 'none',
            ],
            [
                'name' => 'DEMO Chemistry Exam', 'day' => 0, 'hour' => 8, 'hours' => 3, 'room' => 'a',
                'status' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
                'resource' => null, 'checklistdone' => 'none',
            ],
        ];

        $resourceindex = 0;
        foreach ($events as $data) {
            $start = $monday->modify('+' . $data['day'] . ' days')->setTime($data['hour'], 0)->getTimestamp();
            $end = $start + ($data['hours'] * HOURSECS);
            // Request lists filter by term and faculty, so every demo event needs both.
            $eventid = (int)$DB->insert_record('bookit_event', (object)[
                'name' => $data['name'],
                'semester' => event_manager::get_current_semester($start),
                'institutionid' => $institutionid ?: null,
                'starttime' => $start,
                'endtime' => $end,
                'duration' => $end - $start,
                'roomid' => (int)$rooms[$data['room']],
                'participantsamount' => 30,
                'bookingstatus' => $data['status'],
                'personinchargeid' => $userid,
                'usercreated' => $userid,
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);

            if ($data['resource'] !== null && !empty($resourceids)) {
                $resourceid = (int)$resourceids[$resourceindex % count($resourceids)];
                $resourceindex++;
                event_resource_manager::add_resource_to_event($eventid, $resourceid, 1, $userid, $data['resource']);
                self::allow_resource_in_room($resourceid, (int)$rooms[$data['room']]);
            }

            $doneitems = match ($data['checklistdone']) {
                'all' => $itemids,
                'some' => array_slice($itemids, 0, 2),
                default => [],
            };
            foreach ($doneitems as $itemid) {
                event_checklist_state_manager::set_item_state($eventid, (int)$itemid, $userid, true);
            }
        }
    }

    /**
     * Allow a booked demo resource in the room of its demo event.
     *
     * The default resources are created before the demo rooms exist, so their room list may miss the
     * room of the event. A resource without room list (null) is available in all rooms and stays so.
     *
     * @param int $resourceid
     * @param int $roomid
     * @return void
     */
    private static function allow_resource_in_room(int $resourceid, int $roomid): void {
        global $DB;

        $stored = $DB->get_field('bookit_resource', 'roomids', ['id' => $resourceid]);
        if ($stored === null || $stored === false) {
            return;
        }
        $roomids = array_map('intval', json_decode((string)$stored, true) ?? []);
        if (in_array($roomid, $roomids, true)) {
            return;
        }
        $roomids[] = $roomid;
        $DB->set_field('bookit_resource', 'roomids', json_encode($roomids), ['id' => $resourceid]);
    }

    /**
     * Create a fixed demo checklist: two categories with two items each.
     *
     * The values are fixed instead of random, so every generate run looks the same. Items apply to
     * all demo event rooms and to the BookIt roles except observers, who never get checklist items.
     * "Prepare exam material" is due seven days before an event, so it is overdue for the open
     * Monday event on every day of the week.
     *
     * @return void
     */
    private static function seed_demo_checklist(): void {
        global $DB;

        // Generate only runs on a baseline instance, which has no master checklist yet.
        $master = new bookit_checklist_master(
            null,
            'DEMO Examination checklist',
            'Demo checklist for planning and concluding exams.',
            1
        );
        $masterid = $master->save();

        $roomids = array_map('strval', array_values($DB->get_fieldset_select('bookit_room', 'id', 'active = 1')));
        $roleids = [];
        foreach (checklist_manager::get_bookit_roles() as $role) {
            if ($role->shortname !== 'bookit_observer') {
                $roleids[] = (string)$role->id;
            }
        }

        $categories = [
            [
                'name' => 'DEMO Before the exam',
                'items' => [
                    ['title' => 'Prepare exam material', 'offset' => 7, 'relation' => 'before'],
                    ['title' => 'Send room information', 'offset' => 1, 'relation' => 'before'],
                ],
            ],
            [
                'name' => 'DEMO After the exam',
                'items' => [
                    ['title' => 'Collect exam papers', 'offset' => 1, 'relation' => 'after'],
                    ['title' => 'Archive results', 'offset' => 7, 'relation' => 'after'],
                ],
            ],
        ];

        $categoryids = [];
        foreach ($categories as $catindex => $categorydata) {
            $category = new bookit_checklist_category(null, $masterid, $categorydata['name'], '', '', $catindex + 1);
            $categoryid = $category->save();
            $categoryids[] = $categoryid;

            $itemids = [];
            foreach ($categorydata['items'] as $itemindex => $itemdata) {
                $item = new bookit_checklist_item(
                    null,
                    $masterid,
                    $categoryid,
                    null,
                    $roomids,
                    $roleids,
                    $itemdata['title'],
                    '',
                    1,
                    null,
                    $itemindex + 1,
                    1,
                    null,
                    $itemdata['offset'],
                    $itemdata['relation'],
                    null,
                    null,
                    null
                );
                $itemids[] = $item->save();
            }
            $category->checklistitems = implode(',', $itemids);
            $category->save();
        }

        $master->mastercategoryorder = implode(',', $categoryids);
        $master->save();
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

        // Self-created institutions are user data as well. Duplicates of the default name stay,
        // the baseline check reports them.
        $DB->delete_records_select(
            'bookit_institution',
            $DB->sql_compare_text('name') . ' <> ' . $DB->sql_compare_text(':name'),
            ['name' => install_helper::DEFAULT_INSTITUTION_NAME]
        );
        // Exam course mappings of removed institutions would stay in the mapping list without a name.
        if ($DB->get_manager()->table_exists('bookit_examcourse_map')) {
            $DB->delete_records_select(
                'bookit_examcourse_map',
                'institutionid NOT IN (SELECT id FROM {bookit_institution})'
            );
        }

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
     * Current time from the Moodle clock, so tests can freeze it.
     *
     * @return int
     */
    private static function now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * Monday 00:00 of the current week in the timezone of the current user.
     *
     * @return int
     */
    private static function week_start(): int {
        return (new \DateTimeImmutable('@' . self::now()))
            ->setTimezone(\core_date::get_user_timezone_object())
            ->modify('monday this week')
            ->setTime(0, 0)
            ->getTimestamp();
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
