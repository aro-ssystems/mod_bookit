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
 * Unit tests for checklist_manager delete/deactivate guards (WP-7).
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\manager\checklist_manager
 */

namespace mod_bookit\local\manager;

use advanced_testcase;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_category;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_item;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_master;
use mod_bookit\local\form\masterchecklist\edit_checklist_master_form;

/**
 * Unit tests for checklist_manager delete/deactivate guards.
 *
 * @covers \mod_bookit\local\manager\checklist_manager
 */
final class checklist_manager_test extends advanced_testcase {
    /**
     * Ensure active column exists on older Fresh-Install test DBs.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);
        $this->ensure_active_column();
        $this->setAdminUser();
    }

    /**
     * Add bookit_checklist_item.active if missing (Fresh-Install schema on older DBs).
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
     * Category with items cannot be deleted; empty category can.
     */
    public function test_category_delete_guards(): void {
        $master = new bookit_checklist_master(null, 'Master', 'Desc');
        $masterid = $master->save();
        $category = new bookit_checklist_category(null, $masterid, 'Cat', 'Desc');
        $categoryid = $category->save();
        $item = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [],
            'Item',
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
            1
        );
        $item->save();

        $this->assertTrue(checklist_manager::category_has_items($categoryid));
        try {
            checklist_manager::assert_can_delete_category($categoryid);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('checklistcategory_has_items', $e->errorcode);
        }

        checklist_manager::assert_can_delete_item($item->id);
        $item->delete();
        $this->assertFalse(checklist_manager::category_has_items($categoryid));
        checklist_manager::assert_can_delete_category($categoryid);
    }

    /**
     * Item with event state cannot be deleted; deactivate works.
     */
    public function test_item_delete_and_deactivate(): void {
        global $DB, $USER;

        $master = new bookit_checklist_master(null, 'Master2', 'Desc');
        $masterid = $master->save();
        $category = new bookit_checklist_category(null, $masterid, 'Cat2', 'Desc');
        $categoryid = $category->save();
        $item = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [],
            'Item2',
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
            1
        );
        $itemid = $item->save();

        checklist_manager::assert_can_delete_item($itemid);

        $DB->insert_record('bookit_event_checklist_state', (object)[
            'eventid' => 999001,
            'checklistitemid' => $itemid,
            'userid' => $USER->id,
            'done' => 1,
            'usermodified' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertTrue(checklist_manager::item_has_event_states($itemid));
        try {
            checklist_manager::assert_can_delete_item($itemid);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('checklistitem_has_event_states', $e->errorcode);
        }

        checklist_manager::set_item_active($itemid, false);
        $reloaded = bookit_checklist_item::from_database($itemid);
        $this->assertSame(0, (int)$reloaded->active);
    }

    /**
     * Assignable checklist roles exclude Observer; strip removes Observer ids.
     */
    public function test_assignable_roles_exclude_observer(): void {
        $all = checklist_manager::get_bookit_roles();
        $assignable = checklist_manager::get_assignable_checklist_roles();

        $allshort = array_map(static fn($r) => $r->shortname, $all);
        $assignshort = array_map(static fn($r) => $r->shortname, $assignable);

        if (in_array('bookit_observer', $allshort, true)) {
            $this->assertNotContains('bookit_observer', $assignshort);
            $observerid = checklist_manager::get_observer_role_id();
            $this->assertNotNull($observerid);
            $stripped = checklist_manager::strip_observer_roleids([(int)$observerid, 999]);
            $this->assertSame([999], $stripped);
        } else {
            $this->assertSame($allshort, $assignshort);
            $this->assertNull(checklist_manager::get_observer_role_id());
            $this->assertSame([1, 2], checklist_manager::strip_observer_roleids([1, 2]));
        }
    }

    /**
     * An active item of the default checklist is assigned once a booking is confirmed.
     */
    public function test_confirmed_booking_assigns_active_item(): void {
        global $DB;

        [$masterid, $itemid] = $this->create_default_item();
        $this->insert_event(event_access_manager::BOOKINGSTATUS_NEW);
        checklist_manager::assert_can_delete_item($itemid);

        $this->insert_event(event_access_manager::BOOKINGSTATUS_CONFIRMED);
        $this->assertTrue(checklist_manager::item_is_assigned($itemid));
        try {
            checklist_manager::assert_can_delete_item($itemid);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('checklistitem_has_event_states', $e->errorcode);
        }

        checklist_manager::set_item_active($itemid, false);
        $this->assertFalse(checklist_manager::item_is_assigned($itemid));
        checklist_manager::assert_can_delete_item($itemid);

        $form = new edit_checklist_master_form();
        $DB->set_field('bookit_checklist_item', 'active', 1, ['id' => $itemid]);
        try {
            $form->process_delete_request($masterid);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('checklistmaster_has_assigned_items', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('bookit_checklist_master', ['id' => $masterid]));
    }

    /**
     * Create the default master, one category and one active item.
     *
     * @return int[] master id and item id
     */
    private function create_default_item(): array {
        $master = new bookit_checklist_master(null, 'Default master', 'Desc', 1);
        $masterid = $master->save();
        $category = new bookit_checklist_category(null, $masterid, 'Cat', 'Desc');
        $categoryid = $category->save();
        $item = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [],
            'Shown item',
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
            1
        );
        return [$masterid, $item->save()];
    }

    /**
     * Insert a booking with the given status.
     *
     * @param int $status Booking status
     * @return int
     */
    private function insert_event(int $status): int {
        global $DB;
        return (int) $DB->insert_record('bookit_event', (object) [
            'name' => 'Checklist booking',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => strtotime('2026-05-20 09:00:00'),
            'endtime' => strtotime('2026-05-20 11:00:00'),
            'duration' => 120,
            'roomid' => null,
            'participantsamount' => 12,
            'compensationfordisadvantages' => '',
            'bookingstatus' => $status,
            'personinchargeid' => 0,
            'otherexaminers' => '',
            'coursetemplate' => null,
            'notes' => '',
            'internalnotes' => '',
            'supportpersons' => '',
            'extratimebefore' => 0,
            'extratimeafter' => 0,
            'refcourseid' => null,
            'usercreated' => 0,
            'usermodified' => get_admin()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}
