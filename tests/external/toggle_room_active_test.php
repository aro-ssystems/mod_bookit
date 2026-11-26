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

namespace mod_bookit\external;

use advanced_testcase;
use mod_bookit\local\persistent\room;
use required_capability_exception;

/**
 * Tests for toggling room active state.
 *
 * @covers \mod_bookit\external\toggle_room_active
 * @package mod_bookit
 * @copyright 2026 ssystems GmbH <oss@ssystems.de>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class toggle_room_active_test extends advanced_testcase {
    /**
     * Admins with managebasics can toggle and persist active state.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_execute_persists_active_flag(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $roomid = $generator->create_room([
            'name' => 'Toggle room',
            'shortname' => 'TGL1',
            'active' => 1,
        ]);

        $response = toggle_room_active::execute($roomid, false);
        $this->assertSame($roomid, (int) $response['roomid']);
        $this->assertSame(0, (int) $response['active']);

        $room = new room($roomid);
        $this->assertFalse((bool) $room->get('active'));

        $response = toggle_room_active::execute($roomid, true);
        $this->assertSame(1, (int) $response['active']);
        $room = new room($roomid);
        $this->assertTrue((bool) $room->get('active'));
    }

    /**
     * Users without managebasics must not toggle rooms.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_execute_requires_managebasics_capability(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $roomid = $generator->create_room([
            'name' => 'Denied room',
            'shortname' => 'DENY',
            'active' => 1,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(required_capability_exception::class);
        toggle_room_active::execute($roomid, false);
    }
}
