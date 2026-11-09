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
 * Unit tests for demo_data_helper.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local;

use advanced_testcase;
use mod_bookit\local\entity\resource\bookit_resource_status;
use mod_bookit\local\manager\checklist_manager;
use mod_bookit\local\manager\event_checklist_state_manager;

/**
 * Unit tests for demo_data_helper generate/reset invariants.
 *
 * @covers \mod_bookit\local\demo_data_helper
 */
final class demo_data_helper_test extends advanced_testcase {
    /**
     * Freeze the clock at a local time of the current user and prepare the baseline as admin.
     *
     * @param string $localtime Date and time in the admin's timezone, e.g. '2026-11-11 12:00'.
     * @return int The frozen timestamp.
     */
    private function prepare_baseline(string $localtime = '2026-11-11 12:00'): int {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $time = (new \DateTimeImmutable($localtime, \core_date::get_user_timezone_object()))->getTimestamp();
        $this->mock_clock_with_frozen($time);
        install_helper::ensure_fresh_install_baseline();
        return $time;
    }

    /**
     * Insert a BookIt institution.
     *
     * @param string $name
     * @return void
     */
    private function add_institution(string $name): void {
        global $DB;

        $DB->insert_record('bookit_institution', (object)[
            'name' => $name,
            'internalnotes' => '',
            'active' => 1,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Baseline-only is true after ensure_fresh_install_baseline on empty data.
     *
     * @return void
     */
    public function test_is_baseline_only_true_after_ensure_baseline(): void {
        $this->prepare_baseline();
        $this->assertTrue(demo_data_helper::is_baseline_only());
    }

    /**
     * Extra room makes the instance non-baseline.
     *
     * @return void
     */
    public function test_is_baseline_only_false_with_extra_room(): void {
        global $DB;

        $this->prepare_baseline();
        $DB->insert_record('bookit_room', (object)[
            'name' => 'Extra room',
            'shortname' => 'XTRA',
            'description' => '',
            'location' => '',
            'eventcolor' => '',
            'active' => 1,
            'roommode' => 0,
            'seats' => 0,
            'preventoverlap' => 0,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->assertFalse(demo_data_helper::is_baseline_only());
    }

    /**
     * A self-created institution makes the instance non-baseline.
     *
     * @return void
     */
    public function test_is_baseline_only_false_with_second_institution(): void {
        $this->prepare_baseline();
        $this->add_institution('Faculty of Law');
        $this->assertFalse(demo_data_helper::is_baseline_only());
    }

    /**
     * Generate seeds the minimum demo dataset and enables modules; second generate changes nothing.
     *
     * @return void
     */
    public function test_generate_seeds_and_refuses_dirty(): void {
        global $DB;

        $this->prepare_baseline();

        $first = demo_data_helper::generate();
        $this->assertSame('success', $first['status']);
        $this->assertGreaterThanOrEqual(3, $DB->count_records('bookit_room'));
        $this->assertGreaterThanOrEqual(2, $DB->count_records('bookit_resource_category'));
        $this->assertGreaterThan(0, $DB->count_records('bookit_resource'));
        $this->assertGreaterThanOrEqual(2, $DB->count_records('bookit_checklist_category'));
        $this->assertGreaterThanOrEqual(4, $DB->count_records('bookit_checklist_item'));
        $this->assertGreaterThanOrEqual(6, $DB->count_records('bookit_event'));
        $this->assertSame(1, (int)get_config('mod_bookit', install_helper::CONFIG_RESOURCES_ENABLED));
        $this->assertSame(1, (int)get_config('mod_bookit', install_helper::CONFIG_CHECKLIST_ENABLED));

        $countsbefore = demo_data_helper::collect_counts();
        $second = demo_data_helper::generate();
        $this->assertSame('refused', $second['status']);
        $this->assertSame($countsbefore, demo_data_helper::collect_counts());
    }

    /**
     * Generate on an instance with own data but without demo data creates nothing.
     *
     * @return void
     */
    public function test_generate_refused_with_own_event(): void {
        global $DB;

        $this->prepare_baseline();
        $DB->insert_record('bookit_event', (object)[
            'name' => 'Own exam',
            'starttime' => time() + DAYSECS,
            'endtime' => time() + DAYSECS + HOURSECS,
            'duration' => HOURSECS,
            'roomid' => $DB->get_field('bookit_room', 'id', []),
            'bookingstatus' => 0,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $countsbefore = demo_data_helper::collect_counts();
        $report = demo_data_helper::generate();
        $this->assertSame('refused', $report['status']);
        $this->assertSame($countsbefore, demo_data_helper::collect_counts());
    }

    /**
     * Generate does not require prior role import.
     *
     * @return void
     */
    public function test_generate_without_role_install(): void {
        $this->prepare_baseline();
        $report = demo_data_helper::generate();
        $this->assertSame('success', $report['status']);
    }

    /**
     * The demo week covers every booking status, resource status and checklist state.
     *
     * @return void
     */
    public function test_generate_covers_all_combinations(): void {
        $this->prepare_baseline();
        install_helper::import_default_roles_with_report();
        $this->assertSame('success', demo_data_helper::generate()['status']);
        $this->assert_demo_combinations();
    }

    /**
     * On Monday morning, before any demo event has started, an open item is already overdue.
     *
     * @return void
     */
    public function test_generate_has_overdue_item_on_monday_morning(): void {
        $this->prepare_baseline('2026-11-09 07:00');
        $this->assertSame('success', demo_data_helper::generate()['status']);
        $this->assert_demo_combinations();
    }

    /**
     * Check the demo week against the minimum combinations.
     *
     * @return void
     */
    private function assert_demo_combinations(): void {
        global $DB;

        $now = \core\di::get(\core\clock::class)->time();
        $weekstart = (new \DateTimeImmutable('@' . $now))
            ->setTimezone(\core_date::get_user_timezone_object())
            ->modify('monday this week')->setTime(0, 0)->getTimestamp();

        $rooms = $DB->get_records('bookit_room');
        $this->assertGreaterThanOrEqual(3, count($rooms));
        $this->assertGreaterThanOrEqual(2, count(array_unique(array_column($rooms, 'roommode'))));
        $this->assertSame(2, $DB->count_records('bookit_weekplan'));

        $events = $DB->get_records('bookit_event');
        $this->assertGreaterThanOrEqual(6, count($events));
        $statuses = array_unique(array_map('intval', array_column($events, 'bookingstatus')));
        sort($statuses);
        $this->assertSame([0, 1, 2, 3, 4], $statuses);

        foreach ($events as $event) {
            $this->assertGreaterThanOrEqual($weekstart, (int)$event->starttime, $event->name);
            $this->assertSame(
                \mod_bookit\local\manager\event_manager::get_current_semester((int)$event->starttime),
                (int)$event->semester,
                $event->name . ' needs a term to appear in request lists'
            );
            $this->assertNotEmpty($event->institutionid, $event->name . ' needs a faculty');
            $this->assertLessThan($weekstart + 7 * DAYSECS, (int)$event->starttime, $event->name);
            $weekday = (int)(new \DateTimeImmutable('@' . $event->starttime))
                ->setTimezone(\core_date::get_user_timezone_object())->format('N');
            $this->assertLessThanOrEqual(5, $weekday, $event->name . ' is on a weekend');

            $assignment = $DB->get_record_select(
                'bookit_weekplan_room',
                'roomid = ? AND starttime <= ?',
                [$event->roomid, $event->starttime],
                '*',
                IGNORE_MULTIPLE
            );
            $this->assertNotEmpty($assignment, $event->name . ' has no valid weekplan');
            $offset = (int)$event->starttime - $weekstart;
            $this->assertTrue($DB->record_exists_select(
                'bookit_weekplanslot',
                'weekplanid = ? AND starttime <= ? AND endtime >= ?',
                [$assignment->weekplanid, $offset, $offset + (int)$event->duration]
            ), $event->name . ' lies outside the weekplan slots');
        }

        $resourcestatuses = $DB->get_fieldset_sql('SELECT DISTINCT status FROM {bookit_event_resource}');
        sort($resourcestatuses);
        $expected = array_map(fn($case) => $case->value, bookit_resource_status::cases());
        sort($expected);
        $this->assertSame($expected, $resourcestatuses);
        $withoutresource = $DB->count_records_sql(
            'SELECT COUNT(e.id) FROM {bookit_event} e
          LEFT JOIN {bookit_event_resource} r ON r.eventid = e.id
              WHERE r.id IS NULL'
        );
        $this->assertGreaterThanOrEqual(1, $withoutresource);

        $master = checklist_manager::get_default_master();
        $this->assertNotNull($master);
        $items = $DB->get_records('bookit_checklist_item', ['masterid' => $master->id]);
        $this->assertGreaterThanOrEqual(4, count($items));
        $observerid = $DB->get_field('role', 'id', ['shortname' => 'bookit_observer']);
        foreach ($items as $item) {
            $roleids = json_decode((string)$item->roleids, true) ?? [];
            $this->assertNotContains((string)$observerid, array_map('strval', $roleids), $item->title);
        }

        $complete = 0;
        $partial = 0;
        $overdue = 0;
        foreach ($events as $event) {
            $progress = event_checklist_state_manager::get_progress_for_event((int)$event->id, (int)$master->id);
            if ($progress['done'] === $progress['total']) {
                $complete++;
            } else if ($progress['done'] > 0) {
                $partial++;
            }
            $done = $DB->get_fieldset_select(
                'bookit_event_checklist_state',
                'checklistitemid',
                'eventid = ? AND done = 1',
                [$event->id]
            );
            foreach ($items as $item) {
                if ($item->duedaysrelation !== 'before' || in_array($item->id, $done)) {
                    continue;
                }
                if ((int)$event->starttime - (int)$item->duedaysoffset * DAYSECS < $now) {
                    $overdue++;
                }
            }
        }
        $this->assertGreaterThanOrEqual(1, $complete, 'complete checklist');
        $this->assertGreaterThanOrEqual(1, $partial, 'partly done checklist');
        $this->assertGreaterThanOrEqual(1, $overdue, 'overdue open item');
    }

    /**
     * Reset returns to baseline and disables modules; idempotent on baseline.
     *
     * @return void
     */
    public function test_reset_to_baseline_and_idempotent(): void {
        global $DB;

        $this->prepare_baseline();
        demo_data_helper::generate();
        $this->add_institution('Faculty of Law');

        $reset = demo_data_helper::reset();
        $this->assertSame('success', $reset['status']);
        $this->assertTrue(demo_data_helper::is_baseline_only());
        $this->assertSame(1, $DB->count_records('bookit_room'));
        $this->assertSame(1, $DB->count_records('bookit_weekplan'));
        $this->assertSame(1, $DB->count_records('bookit_institution'));
        $this->assertSame(install_helper::DEFAULT_ROOM_NAME, $DB->get_field('bookit_room', 'name', []));
        $this->assertSame(0, (int)get_config('mod_bookit', install_helper::CONFIG_RESOURCES_ENABLED));
        $this->assertSame(0, (int)get_config('mod_bookit', install_helper::CONFIG_CHECKLIST_ENABLED));

        $again = demo_data_helper::reset();
        $this->assertSame('success', $again['status']);
        $this->assertTrue(demo_data_helper::is_baseline_only());
    }

    /**
     * A reset that cannot restore the baseline changes nothing and reports the failure.
     *
     * @return void
     */
    public function test_reset_failure_rolls_back(): void {
        // PHPUnit wraps each test in a transaction; the rollback of the reset must be a real one.
        $this->preventResetByRollback();
        $this->prepare_baseline();
        demo_data_helper::generate();
        // A second institution with the default name cannot be resolved by the reset.
        $this->add_institution(install_helper::DEFAULT_INSTITUTION_NAME);

        $countsbefore = demo_data_helper::collect_counts();
        $reset = demo_data_helper::reset();

        $this->assertSame('failed', $reset['status']);
        $this->assertNotEmpty($reset['errors']);
        $this->assertSame($countsbefore, demo_data_helper::collect_counts());
        $this->assertSame(1, (int)get_config('mod_bookit', install_helper::CONFIG_RESOURCES_ENABLED));
    }
}
