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
use context_module;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_category;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_item;
use mod_bookit\local\entity\masterchecklist\bookit_checklist_master;
use mod_bookit\local\install_helper;
use mod_bookit\local\manager\checklist_manager;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\local\manager\event_checklist_state_manager;
use mod_bookit\local\manager\event_manager;

/**
 * Regression tests for checklist toggles during governed-read rollout.
 *
 * @covers \mod_bookit\external\toggle_event_checklist_item
 * @package mod_bookit
 * @copyright 2026 ssystems GmbH <oss@ssystems.de>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class toggle_event_checklist_item_test extends advanced_testcase {
    /**
     * Checklist state changes must coexist with governed overview reads.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_execute_toggles_state_without_breaking_governed_reads(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config(install_helper::CONFIG_CHECKLIST_ENABLED, 1, 'mod_bookit');

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id, 'name' => 'Checklist command']);
        $context = context_module::instance($bookit->cmid);

        $eventid = (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'Checklist event',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => strtotime('2026-05-20 09:00:00'),
            'endtime' => strtotime('2026-05-20 11:00:00'),
            'duration' => 120,
            'roomid' => null,
            'participantsamount' => 12,
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
            'usercreated' => 0,
            'usermodified' => get_admin()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $master = new bookit_checklist_master(null, 'Governed checklist', 'Read-rollout regression');
        $masterid = $master->save();
        $category = new bookit_checklist_category(null, $masterid, 'Preparation', 'Preparation tasks');
        $categoryid = $category->save();
        $item = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [],
            'Bring cables',
            'Checklist regression',
            1,
            null,
            0,
            1,
            null,
            null,
            null,
            null,
            null,
            null
        );
        $itemid = $item->save();

        $governedbefore = event_manager::get_governed_calendar_events(
            $context,
            (int)get_admin()->id,
            '2026-05-20 00:00',
            '2026-05-20 23:59',
            []
        );
        $this->assertCount(1, $governedbefore);
        $this->assertSame($eventid, (int)$governedbefore[0]['id']);

        $response = toggle_event_checklist_item::execute($bookit->cmid, $eventid, $itemid, true);

        $this->assertTrue($response['done']);

        $state = $DB->get_record('bookit_event_checklist_state', [
            'eventid' => $eventid,
            'checklistitemid' => $itemid,
            'userid' => get_admin()->id,
        ], 'done', MUST_EXIST);
        $this->assertSame(1, (int)$state->done);

        $governedafter = event_manager::get_governed_calendar_events(
            $context,
            (int)get_admin()->id,
            '2026-05-20 00:00',
            '2026-05-20 23:59',
            []
        );
        $this->assertCount(1, $governedafter);
        $this->assertSame($eventid, (int)$governedafter[0]['id']);
    }

    /**
     * Support-only users follow roleids intersection (WP-7 R3.3).
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_support_only_respects_roleids_intersection(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config(install_helper::CONFIG_CHECKLIST_ENABLED, 1, 'mod_bookit');

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('bookit_checklist_item');
        $field = new \xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id, 'name' => 'Support checklist']);
        $context = context_module::instance($bookit->cmid);

        $supportrole = $DB->get_record('role', ['shortname' => 'bookit_supportonsite']);
        if (!$supportrole) {
            $supportroleid = \create_role('Bookit support on site', 'bookit_supportonsite', '');
        } else {
            $supportroleid = (int)$supportrole->id;
        }
        $bookingrole = $DB->get_record('role', ['shortname' => 'bookit_bookingperson']);
        if (!$bookingrole) {
            $bookingroleid = \create_role('Bookit booking person', 'bookit_bookingperson', '');
        } else {
            $bookingroleid = (int)$bookingrole->id;
        }

        \assign_capability('mod/bookit:view', CAP_ALLOW, $supportroleid, $context->id, true);
        \assign_capability('mod/bookit:viewownoverview', CAP_ALLOW, $supportroleid, $context->id, true);
        \assign_capability('mod/bookit:viewalldetailsofownevent', CAP_ALLOW, $supportroleid, $context->id, true);
        // Explicitly ensure no Service bypass caps.
        \assign_capability('mod/bookit:managebasics', CAP_PREVENT, $supportroleid, $context->id, true);
        \assign_capability('mod/bookit:viewalldetailsofevent', CAP_PREVENT, $supportroleid, $context->id, true);

        $supportuser = $this->getDataGenerator()->create_user();
        \role_assign($supportroleid, $supportuser->id, $context->id);
        $this->getDataGenerator()->enrol_user($supportuser->id, $course->id);

        $eventid = (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'Support checklist event',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => strtotime('2026-05-20 09:00:00'),
            'endtime' => strtotime('2026-05-20 11:00:00'),
            'duration' => 120,
            'roomid' => null,
            'participantsamount' => 12,
            'compensationfordisadvantages' => '',
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
            'personinchargeid' => 0,
            'otherexaminers' => '',
            'coursetemplate' => null,
            'notes' => '',
            'internalnotes' => '',
            'supportpersons' => (string)$supportuser->id,
            'extratimebefore' => 0,
            'extratimeafter' => 0,
            'refcourseid' => null,
            'usercreated' => 0,
            'usermodified' => get_admin()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $master = new bookit_checklist_master(null, 'Support master', '');
        $masterid = $master->save();
        $category = new bookit_checklist_category(null, $masterid, 'Cat', '');
        $categoryid = $category->save();

        $ownitem = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [$supportroleid],
            'Support item',
            '',
            1,
            null,
            0,
            1,
            null,
            null,
            null,
            get_admin()->id,
            time(),
            time(),
            1
        );
        $ownitemid = $ownitem->save();

        $foreignitem = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [$bookingroleid],
            'Booking only',
            '',
            1,
            null,
            0,
            1,
            null,
            null,
            null,
            get_admin()->id,
            time(),
            time(),
            1
        );
        $foreignitemid = $foreignitem->save();

        $emptyroles = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [],
            'Empty roles',
            '',
            1,
            null,
            0,
            1,
            null,
            null,
            null,
            get_admin()->id,
            time(),
            time(),
            1
        );
        $emptyrolesid = $emptyroles->save();

        $event = $DB->get_record('bookit_event', ['id' => $eventid], '*', MUST_EXIST);
        $userroles = checklist_manager::get_user_bookit_role_ids($supportuser->id);

        $this->assertTrue(event_access_manager::can_toggle_event_checklist_item(
            $event,
            [$supportroleid],
            $context,
            $supportuser->id,
            $userroles
        ));
        $this->assertFalse(event_access_manager::can_toggle_event_checklist_item(
            $event,
            [$bookingroleid],
            $context,
            $supportuser->id,
            $userroles
        ));
        $this->assertTrue(event_access_manager::can_toggle_event_checklist_item(
            $event,
            [],
            $context,
            $supportuser->id,
            $userroles
        ));

        $this->setUser($supportuser);
        $ok = toggle_event_checklist_item::execute($bookit->cmid, $eventid, $ownitemid, true);
        $this->assertTrue($ok['done']);

        $this->expectException(\moodle_exception::class);
        toggle_event_checklist_item::execute($bookit->cmid, $eventid, $foreignitemid, true);
    }

    /**
     * An inactive item without a saved state cannot be checked off.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_inactive_item_without_state_does_not_change_progress(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config(install_helper::CONFIG_CHECKLIST_ENABLED, 1, 'mod_bookit');

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id, 'name' => 'Inactive item']);
        $eventid = $this->insert_confirmed_event();
        [$masterid, $itemid] = $this->insert_item('Inactive cables');
        checklist_manager::set_item_active($itemid, false);

        $before = event_checklist_state_manager::get_progress_for_event($eventid, $masterid);
        $this->expectException(\moodle_exception::class);
        try {
            toggle_event_checklist_item::execute($bookit->cmid, $eventid, $itemid, true);
        } finally {
            $this->assertFalse($DB->record_exists('bookit_event_checklist_state', [
                'eventid' => $eventid,
                'checklistitemid' => $itemid,
            ]));
            $after = event_checklist_state_manager::get_progress_for_event($eventid, $masterid);
            $this->assertSame($before, $after);
        }
    }

    /**
     * An inactive item that already has a state can still be changed.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_inactive_item_with_state_can_be_changed(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config(install_helper::CONFIG_CHECKLIST_ENABLED, 1, 'mod_bookit');

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id, 'name' => 'Inactive state']);
        $eventid = $this->insert_confirmed_event();
        [$masterid, $itemid] = $this->insert_item('Already checked');
        checklist_manager::set_item_active($itemid, false);
        $DB->insert_record('bookit_event_checklist_state', (object) [
            'eventid' => $eventid,
            'checklistitemid' => $itemid,
            'userid' => $USER->id,
            'done' => 0,
            'usermodified' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $response = toggle_event_checklist_item::execute($bookit->cmid, $eventid, $itemid, true);
        $this->assertTrue($response['done']);
        $progress = event_checklist_state_manager::get_progress_for_event($eventid, $masterid);
        $this->assertSame(1, $progress['total']);
        $this->assertSame(1, $progress['done']);
    }

    /**
     * Confirmed booking used by the toggle cases.
     *
     * @return int
     */
    private function insert_confirmed_event(): int {
        global $DB;
        return (int) $DB->insert_record('bookit_event', (object) [
            'name' => 'Inactive checklist event',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => strtotime('2026-05-20 09:00:00'),
            'endtime' => strtotime('2026-05-20 11:00:00'),
            'duration' => 120,
            'roomid' => null,
            'participantsamount' => 12,
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
            'usercreated' => 0,
            'usermodified' => get_admin()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * One active item on its own master checklist.
     *
     * @param string $title
     * @return int[] master id and item id
     */
    private function insert_item(string $title): array {
        $master = new bookit_checklist_master(null, 'Toggle master', 'Desc');
        $masterid = $master->save();
        $category = new bookit_checklist_category(null, $masterid, 'Prep', 'Desc');
        $categoryid = $category->save();
        $item = new bookit_checklist_item(
            null,
            $masterid,
            $categoryid,
            null,
            [],
            [],
            $title,
            '',
            1,
            null,
            0,
            1,
            null,
            null,
            null,
            null,
            null,
            null
        );
        return [$masterid, $item->save()];
    }
}
