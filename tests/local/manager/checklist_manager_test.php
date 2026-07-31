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
}
