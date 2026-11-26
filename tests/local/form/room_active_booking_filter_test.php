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

namespace mod_bookit\local\form;

use advanced_testcase;
use mod_bookit\local\persistent\room;

/**
 * Booking room sources must exclude inactive rooms.
 *
 * @covers \mod_bookit\local\formelement\roomfilter
 * @covers \mod_bookit\local\persistent\room
 * @package mod_bookit
 * @copyright 2026 ssystems GmbH <oss@ssystems.de>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class room_active_booking_filter_test extends advanced_testcase {
    /**
     * Inactive rooms are absent from active room queries used by booking UI.
     *
     * @return void
     */
    public function test_inactive_rooms_excluded_from_active_queries(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $activeid = $generator->create_room([
            'name' => 'Active hall',
            'shortname' => 'ACT1',
            'active' => 1,
        ]);
        $inactiveid = $generator->create_room([
            'name' => 'Inactive hall',
            'shortname' => 'INA1',
            'active' => 0,
        ]);

        $activerooms = room::get_records(['active' => true]);
        $activeids = array_map(static fn(room $r): int => (int) $r->get('id'), $activerooms);

        $this->assertContains($activeid, $activeids);
        $this->assertNotContains($inactiveid, $activeids);

        // Roomfilter caches active rooms the same way as the booking filter UI.
        $filterrooms = room::get_records(['active' => 1], 'name', 'ASC');
        $filterids = array_map(static fn(room $r): int => (int) $r->get('id'), $filterrooms);
        $this->assertContains($activeid, $filterids);
        $this->assertNotContains($inactiveid, $filterids);
    }
}
