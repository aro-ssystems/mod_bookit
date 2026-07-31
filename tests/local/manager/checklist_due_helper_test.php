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
 * Unit tests for checklist due/overdue helpers (WP-8).
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\manager\checklist_manager
 */

namespace mod_bookit\local\manager;

use advanced_testcase;

/**
 * Due / overdue helper tests.
 *
 * @covers \mod_bookit\local\manager\checklist_manager
 */
final class checklist_due_helper_test extends advanced_testcase {
    /**
     * Reset state.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Absolute due and overdue rules for none/before/after and done items.
     */
    public function test_absolute_due_and_overdue_cases(): void {
        $start = 1_700_000_000; // Fixed anchor.
        $now = $start;

        $this->assertNull(checklist_manager::get_absolute_due_timestamp($start, 'none', 3));
        $this->assertNull(checklist_manager::get_absolute_due_timestamp($start, null, 3));
        $this->assertFalse(checklist_manager::is_checklist_item_overdue($start, 'none', 3, false, $now));

        $beforedue = checklist_manager::get_absolute_due_timestamp($start, 'before', 2);
        $this->assertSame($start - (2 * DAYSECS), $beforedue);
        // Past due, open → overdue.
        $this->assertTrue(checklist_manager::is_checklist_item_overdue($start, 'before', 2, false, $now));
        // Past due, done → not overdue.
        $this->assertFalse(checklist_manager::is_checklist_item_overdue($start, 'before', 2, true, $now));

        $afterdue = checklist_manager::get_absolute_due_timestamp($start, 'after', 5);
        $this->assertSame($start + (5 * DAYSECS), $afterdue);
        // Future due, open → not overdue.
        $this->assertFalse(checklist_manager::is_checklist_item_overdue($start, 'after', 5, false, $now));
        // After due date passed → overdue.
        $this->assertTrue(checklist_manager::is_checklist_item_overdue(
            $start,
            'after',
            5,
            false,
            $start + (5 * DAYSECS) + 1
        ));
    }

    /**
     * Zero-day before means due at event start.
     */
    public function test_zero_offset_before_at_start(): void {
        $start = 1_700_000_000;
        $this->assertSame($start, checklist_manager::get_absolute_due_timestamp($start, 'before', 0));
        $this->assertFalse(checklist_manager::is_checklist_item_overdue($start, 'before', 0, false, $start));
        $this->assertTrue(checklist_manager::is_checklist_item_overdue($start, 'before', 0, false, $start + 1));
    }
}
