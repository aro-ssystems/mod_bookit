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
 * CSV import rules for the master checklist.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use advanced_testcase;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_master;

/**
 * Tests observer stripping and all-or-nothing checklist import.
 *
 * @covers \mod_bookit\local\manager\sharing_manager
 */
final class sharing_manager_checklist_import_test extends advanced_testcase {
    /** @var int */
    private $masterid;

    /** @var int */
    private $serviceid;

    /** @var int */
    private $observerid;

    /**
     * Fresh master and the two roles named in the fixture files.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $master = new bookit_checklist_master(null, 'Import master', 'Desc');
        $this->masterid = $master->save();
        $this->serviceid = $this->role_id('bookit_serviceteam', 'Service team');
        $this->observerid = $this->role_id('bookit_observer', 'Observer');
    }

    /**
     * Observer is removed when another role is named. An empty role list stays "all roles".
     */
    public function test_import_strips_observer_and_keeps_empty_role_list(): void {
        $csv = "type,name,description,role_shortnames\n" .
            "category,Imported,Desc,\n" .
            "item,Mixed,Desc,\"[\"\"bookit_observer\"\",\"\"bookit_serviceteam\"\"]\"\n" .
            "item,Everyone,Desc,\n";

        $result = sharing_manager::import_master_checklist_csv($this->masterid, $csv, false);
        $this->assertTrue($result['success']);

        $bytitle = $this->items_by_title();
        $roles = $this->role_ids($bytitle['Mixed']->roleids);
        $this->assertContains($this->serviceid, $roles);
        $this->assertNotContains($this->observerid, $roles);
        $this->assertSame([0], $this->role_ids($bytitle['Everyone']->roleids));
    }

    /**
     * A row that only names Observer writes nothing and reports the line.
     */
    public function test_import_rejects_observer_only_without_writing(): void {
        global $DB;

        $csv = "type,name,description,role_shortnames\n" .
            "category,Kept out,Desc,\n" .
            "item,Only observer,Desc,\"[\"\"bookit_observer\"\"]\"\n";

        $result = sharing_manager::import_master_checklist_csv($this->masterid, $csv, false);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('3', $result['message']);
        $this->assertFalse($DB->record_exists('bookit_checklist_category', ['masterid' => $this->masterid]));
        $this->assertFalse($DB->record_exists('bookit_checklist_item', ['masterid' => $this->masterid]));
    }

    /**
     * A short row fails the whole import.
     */
    public function test_import_rejects_unreadable_line_without_writing(): void {
        global $DB;

        $csv = "type,name,description\ncategory\n";
        $result = sharing_manager::import_master_checklist_csv($this->masterid, $csv, false);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('2', $result['message']);
        $this->assertFalse($DB->record_exists('bookit_checklist_category', ['masterid' => $this->masterid]));
    }

    /**
     * A row with more fields than the header fails the whole import with its line number.
     */
    public function test_import_rejects_line_with_extra_fields_without_writing(): void {
        global $DB;

        $csv = "type,name,description\ncategory,Kept out,Desc\nitem,Too many,Desc,extra\n";
        $result = sharing_manager::import_master_checklist_csv($this->masterid, $csv, false);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('3', $result['message']);
        $this->assertFalse($DB->record_exists('bookit_checklist_category', ['masterid' => $this->masterid]));
        $this->assertFalse($DB->record_exists('bookit_checklist_item', ['masterid' => $this->masterid]));
    }

    /**
     * Items of the fixture master, keyed by title.
     *
     * @return \stdClass[]
     */
    private function items_by_title(): array {
        global $DB;
        $bytitle = [];
        foreach ($DB->get_records('bookit_checklist_item', ['masterid' => $this->masterid]) as $item) {
            $bytitle[$item->title] = $item;
        }
        return $bytitle;
    }

    /**
     * Role id for a shortname, creating the role when the site does not have it yet.
     *
     * @param string $shortname
     * @param string $name
     * @return int
     */
    private function role_id(string $shortname, string $name): int {
        global $DB;
        $existing = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        if ($existing) {
            return (int) $existing;
        }
        return (int) create_role($name, $shortname, '');
    }

    /**
     * Decode stored role ids.
     *
     * @param mixed $stored
     * @return int[]
     */
    private function role_ids($stored): array {
        if (is_array($stored)) {
            $ids = $stored;
        } else {
            $decoded = json_decode((string) $stored, true);
            $ids = is_array($decoded) ? $decoded : array_filter(explode(',', (string) $stored), 'strlen');
        }
        return array_map('intval', $ids);
    }
}
