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
 * Unit tests for event checklist progress parity.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\manager\event_checklist_state_manager
 */

namespace mod_bookit\local\manager;

use advanced_testcase;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_category;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_item;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_master;

/**
 * Progress countable-set tests with at least five fixtures.
 *
 * @covers \mod_bookit\local\manager\event_checklist_state_manager
 */
final class event_checklist_state_manager_test extends advanced_testcase {
    /** @var int */
    private int $masterid;
    /** @var int */
    private int $categoryid;
    /** @var int */
    private int $eventid;

    protected function setUp(): void {
        global $DB, $USER;
        parent::setUp();
        $this->resetAfterTest(true);
        $this->ensure_active_column();
        $this->setAdminUser();

        $master = new bookit_checklist_master(null, 'Progress master', 'Desc');
        $this->masterid = $master->save();
        $category = new bookit_checklist_category(null, $this->masterid, 'Progress cat', 'Desc');
        $this->categoryid = $category->save();

        $this->eventid = (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'Progress event',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => time() + DAYSECS,
            'endtime' => time() + DAYSECS + HOURSECS,
            'duration' => 60,
            'roomid' => null,
            'participantsamount' => 10,
            'compensationfordisadvantages' => '',
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
            'personinchargeid' => 0,
            'otherexaminers' => '',
            'coursetemplate' => null,
            'notes' => '',
            'internalnotes' => '',
            'supportpersons' => '',
            'extratimebefore' => 0,
            'extratimeafter' => 0,
            'refcourseid' => null,
            'usercreated' => $USER->id,
            'usermodified' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Ensure active column exists.
     */
    private function ensure_active_column(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('bookit_checklist_item');
        $field = new \xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    /**
     * Create a checklist item under the test category.
     *
     * @param string $title
     * @param int $active
     * @param int|null $categoryid
     * @return int
     */
    private function create_item(string $title, int $active = 1, ?int $categoryid = null): int {
        $item = new bookit_checklist_item(
            null,
            $this->masterid,
            $categoryid ?? $this->categoryid,
            null,
            [],
            [],
            $title,
            '',
            1,
            null,
            0,
            0,
            null,
            null,
            null,
            get_admin()->id,
            time(),
            time(),
            $active
        );
        return $item->save();
    }

    /**
     * Mark item done for the test event.
     *
     * @param int $itemid
     */
    private function mark_done(int $itemid): void {
        global $USER;
        event_checklist_state_manager::set_item_state($this->eventid, $itemid, $USER->id, true);
    }

    /**
     * Fixture 1: mixed progress 2/5.
     */
    public function test_mixed_progress(): void {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->create_item('Mixed ' . $i);
        }
        $this->mark_done($ids[0]);
        $this->mark_done($ids[1]);

        $progress = event_checklist_state_manager::get_progress_for_event($this->eventid, $this->masterid);
        $this->assertSame(5, $progress['total']);
        $this->assertSame(2, $progress['done']);
        $this->assertSame(40, $progress['percent']);

        $batch = event_checklist_state_manager::get_progress_percent_for_events([$this->eventid], $this->masterid);
        $this->assertSame(40, $batch[$this->eventid]);
    }

    /**
     * Fixture 2: orphan item (no category) excluded from totals.
     */
    public function test_orphan_item_excluded(): void {
        global $DB;
        $good = $this->create_item('Good');
        $orphan = $this->create_item('Orphan');
        $DB->set_field('bookit_checklist_item', 'categoryid', null, ['id' => $orphan]);
        $this->mark_done($good);

        $progress = event_checklist_state_manager::get_progress_for_event($this->eventid, $this->masterid);
        $this->assertSame(1, $progress['total']);
        $this->assertSame(1, $progress['done']);
        $this->assertSame(100, $progress['percent']);
        $ids = event_checklist_state_manager::get_countable_item_ids($this->eventid, $this->masterid);
        $this->assertSame([$good], $ids);
    }

    /**
     * Fixture 3: inactive without state excluded.
     */
    public function test_inactive_without_state_excluded(): void {
        $active = $this->create_item('Active', 1);
        $this->create_item('Inactive', 0);
        $this->mark_done($active);

        $progress = event_checklist_state_manager::get_progress_for_event($this->eventid, $this->masterid);
        $this->assertSame(1, $progress['total']);
        $this->assertSame(1, $progress['done']);
    }

    /**
     * Fixture 4: inactive with state included.
     */
    public function test_inactive_with_state_included(): void {
        global $USER;
        $inactive = $this->create_item('Was active', 1);
        event_checklist_state_manager::set_item_state($this->eventid, $inactive, $USER->id, false);
        checklist_manager::set_item_active($inactive, false);

        $progress = event_checklist_state_manager::get_progress_for_event($this->eventid, $this->masterid);
        $this->assertSame(1, $progress['total']);
        $this->assertSame(0, $progress['done']);
        $this->assertSame(0, $progress['percent']);
    }

    /**
     * Fixture 5: empty checklist.
     */
    public function test_empty_checklist(): void {
        $progress = event_checklist_state_manager::get_progress_for_event($this->eventid, $this->masterid);
        $this->assertSame(['done' => 0, 'total' => 0, 'percent' => 0], $progress);
    }
}
