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

namespace mod_bookit\local\persistent;

use advanced_testcase;
use core\invalid_persistent_exception;

/**
 * Unit tests for room shortname length validation.
 *
 * @covers \mod_bookit\local\persistent\room
 * @package mod_bookit
 * @copyright 2026 ssystems GmbH <oss@ssystems.de>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class room_shortname_validation_test extends advanced_testcase {
    /**
     * Shortnames longer than 6 characters must be rejected on save.
     *
     * @return void
     */
    public function test_shortname_longer_than_max_is_rejected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $room = new room(0, (object) [
            'name' => 'Validation room',
            'shortname' => 'TOOLONG',
            'description' => '',
            'location' => '',
            'eventcolor' => '#3a87ad',
            'active' => 1,
            'roommode' => room::MODE_FREE,
            'seats' => 10,
            'preventoverlap' => room::OVERLAPPING_ALLOW_NONE,
        ]);

        $this->expectException(invalid_persistent_exception::class);
        $room->create();
    }

    /**
     * Shortnames up to 6 characters (including Unicode) must persist.
     *
     * @return void
     */
    public function test_shortname_at_max_length_is_accepted(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $room = new room(0, (object) [
            'name' => 'Valid room',
            'shortname' => 'ÄÖÜABC',
            'description' => '',
            'location' => '',
            'eventcolor' => '#3a87ad',
            'active' => 1,
            'roommode' => room::MODE_FREE,
            'seats' => 10,
            'preventoverlap' => room::OVERLAPPING_ALLOW_NONE,
        ]);
        $room->create();

        $this->assertSame('ÄÖÜABC', $room->get('shortname'));
        $this->assertSame(6, \core_text::strlen($room->get('shortname')));
    }
}
