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
 * Calendar text for a booking whose room was deactivated.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use advanced_testcase;
use context_module;

/**
 * Calendar text for a booking whose room was deactivated.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\manager\event_manager::get_events_in_timerange
 */
final class event_manager_inactive_room_test extends advanced_testcase {
    /**
     * A confirmed booking keeps the room name after that room is inactive.
     *
     * @return void
     */
    public function test_calendar_payload_names_inactive_room(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', [
            'course' => $course->id,
            'name' => 'Room name calendar',
        ]);
        $context = context_module::instance($bookit->cmid);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $roomid = $generator->create_room([
            'name' => 'Closed Hall',
            'shortname' => 'CLOSD',
            'active' => 0,
        ]);

        $DB->insert_record('bookit_event', (object) [
            'name' => 'Morning exam',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => strtotime('2026-05-08 09:00:00'),
            'endtime' => strtotime('2026-05-08 11:00:00'),
            'duration' => 120,
            'roomid' => $roomid,
            'participantsamount' => 10,
            'compensationfordisadvantages' => '',
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
            'personinchargeid' => null,
            'otherexaminers' => '',
            'coursetemplate' => null,
            'notes' => '',
            'internalnotes' => '',
            'supportpersons' => '',
            'extratimebefore' => 0,
            'extratimeafter' => 0,
            'refcourseid' => null,
            'usercreated' => 2,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $events = event_manager::get_events_in_timerange(
            '2026-05-08 00:00',
            '2026-05-08 23:59',
            (int) $context->instanceid
        );

        $this->assertCount(1, $events);
        $this->assertSame('Closed Hall', $events[0]['extendedProps']['room']['roomname']);
        $this->assertStringContainsString('Closed Hall', $events[0]['title']);
    }
}
