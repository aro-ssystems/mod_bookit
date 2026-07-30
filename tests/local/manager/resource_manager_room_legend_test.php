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

namespace mod_bookit\local\manager;

use advanced_testcase;

/**
 * Tests for booking-facing room legend / overview helpers.
 *
 * @covers \mod_bookit\local\manager\resource_manager::get_active_rooms_for_legend
 * @covers \mod_bookit\local\manager\resource_manager::get_active_rooms_with_resources
 * @covers \mod_bookit\local\manager\resource_manager::get_rooms
 * @package mod_bookit
 * @copyright 2026 ssystems GmbH <oss@ssystems.de>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resource_manager_room_legend_test extends advanced_testcase {
    /**
     * Legend helper returns only active rooms with description and seats.
     *
     * @return void
     */
    public function test_get_active_rooms_for_legend_excludes_inactive(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $activeid = $generator->create_room([
            'name' => 'Legend Active',
            'shortname' => 'LACT',
            'description' => 'Has beamer',
            'seats' => 42,
            'active' => 1,
        ]);
        $inactiveid = $generator->create_room([
            'name' => 'Legend Inactive',
            'shortname' => 'LINA',
            'description' => 'Hidden',
            'seats' => 5,
            'active' => 0,
        ]);

        $rooms = resource_manager::get_active_rooms_for_legend();
        $ids = array_column($rooms, 'id');

        $this->assertContains($activeid, $ids);
        $this->assertNotContains($inactiveid, $ids);

        $active = null;
        foreach ($rooms as $room) {
            if ((int) $room['id'] === $activeid) {
                $active = $room;
                break;
            }
        }
        $this->assertNotNull($active);
        $this->assertSame('LACT', $active['shortname']);
        $this->assertSame('Has beamer', $active['description']);
        $this->assertSame(42, $active['seats']);
        $this->assertArrayHasKey('name', $active);
        $this->assertArrayHasKey('eventcolor', $active);
    }

    /**
     * Calendar filter source get_rooms() returns only active rooms.
     *
     * @return void
     */
    public function test_get_rooms_excludes_inactive(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $activeid = $generator->create_room([
            'name' => 'Filter Active',
            'shortname' => 'FACT',
            'active' => 1,
        ]);
        $inactiveid = $generator->create_room([
            'name' => 'Filter Inactive',
            'shortname' => 'FINA',
            'active' => 0,
        ]);

        $rooms = resource_manager::get_rooms();

        $this->assertArrayHasKey($activeid, $rooms);
        $this->assertArrayNotHasKey($inactiveid, $rooms);
        $this->assertSame('Filter Active', $rooms[$activeid]);
    }

    /**
     * Overview helper maps resources by roomids; empty roomids ⇒ all active rooms.
     *
     * @return void
     */
    public function test_get_active_rooms_with_resources_association_rules(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $rooma = $generator->create_room([
            'name' => 'Assoc Room A',
            'shortname' => 'ARA',
            'active' => 1,
        ]);
        $roomb = $generator->create_room([
            'name' => 'Assoc Room B',
            'shortname' => 'ARB',
            'active' => 1,
        ]);
        $inactive = $generator->create_room([
            'name' => 'Assoc Inactive',
            'shortname' => 'ARI',
            'active' => 0,
        ]);

        $generator->create_resource_category(['name' => 'Legend Cat']);
        $linked = $generator->create_resource([
            'name' => 'Linked Mic',
            'category_name' => 'Legend Cat',
            'rooms' => 'Assoc Room A',
            'active' => 1,
        ]);
        $allrooms = $generator->create_resource([
            'name' => 'Shared Projector',
            'category_name' => 'Legend Cat',
            'active' => 1,
        ]);
        $generator->create_resource([
            'name' => 'Inactive Resource',
            'category_name' => 'Legend Cat',
            'rooms' => 'Assoc Room A',
            'active' => 0,
        ]);

        $rows = resource_manager::get_active_rooms_with_resources();
        $byid = [];
        foreach ($rows as $row) {
            $byid[(int) $row['id']] = $row;
        }

        $this->assertArrayHasKey($rooma, $byid);
        $this->assertArrayHasKey($roomb, $byid);
        $this->assertArrayNotHasKey($inactive, $byid);

        $resourceidsa = array_column($byid[$rooma]['resources'], 'id');
        $resourceidsb = array_column($byid[$roomb]['resources'], 'id');

        $this->assertContains($linked, $resourceidsa);
        $this->assertNotContains($linked, $resourceidsb);
        $this->assertContains($allrooms, $resourceidsa);
        $this->assertContains($allrooms, $resourceidsb);
        $this->assertStringContainsString('Linked Mic', $byid[$rooma]['resourceslabel']);
        $this->assertStringContainsString('Shared Projector', $byid[$roomb]['resourceslabel']);
    }
}
