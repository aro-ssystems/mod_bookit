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
 * Tests for retaining booked inactive resources on edit_event_form save.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\form;

use advanced_testcase;
use context_module;
use mod_bookit\form\edit_event_form;
use mod_bookit\local\entity\bookit_event;
use mod_bookit\local\install_helper;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\local\manager\event_manager;
use mod_bookit\local\manager\resource_manager;

/**
 * Inactive booked resources must survive saves unless explicitly unchecked.
 *
 * @covers \mod_bookit\form\edit_event_form::process_dynamic_submission
 * @runTestsInSeparateProcesses
 */
final class edit_event_form_inactive_resource_test extends advanced_testcase {
    /** @var int */
    private int $resourceid;

    /** @var int */
    private int $resourceid2;

    /** @var int */
    private int $roomid;

    /**
     * Set up resources-enabled Bookit fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        message_update_providers('mod_bookit');
        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 1, 'mod_bookit');

        /** @var \mod_bookit_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bookit');
        $this->roomid = $generator->create_room([
            'name' => 'Inactive resource room',
            'shortname' => 'INR001',
        ]);
        $generator->create_resource_category(['name' => 'Inactive equipment']);
        $this->resourceid = $generator->create_resource([
            'name' => 'Inactive projector',
            'category_name' => 'Inactive equipment',
            'amount' => 5,
            'amountirrelevant' => true,
        ]);
        $this->resourceid2 = $generator->create_resource([
            'name' => 'Inactive laptop',
            'category_name' => 'Inactive equipment',
            'amount' => 3,
            'amountirrelevant' => true,
        ]);
    }

    /**
     * Create a service-team editor context.
     */
    private function create_editor_context(int $userid): context_module {
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($userid, $course->id);
        $bookit = $this->getDataGenerator()->create_module('bookit', [
            'course' => $course->id,
            'name' => 'Inactive resource test',
        ]);
        $context = context_module::instance($bookit->cmid);

        \update_capabilities('mod_bookit');
        $roleid = \create_role('Bookit inactive editor', 'bookitinactiveeditor', 'student');
        \assign_capability('mod/bookit:view', CAP_ALLOW, $roleid, $context->id, true);
        \assign_capability('mod/bookit:addevent', CAP_ALLOW, $roleid, $context->id, true);
        \assign_capability('mod/bookit:editevent', CAP_ALLOW, $roleid, $context->id, true);
        \assign_capability('mod/bookit:editinternal', CAP_ALLOW, $roleid, $context->id, true);
        \role_assign($roleid, $userid, $context->id);
        \accesslib_clear_all_caches_for_unit_testing();

        return $context;
    }

    /**
     * Build form payload from a persisted event.
     *
     * @param \stdClass $event
     * @param context_module $context
     * @param array $overrides
     * @return array
     */
    private function build_formdata_from_event(\stdClass $event, context_module $context, array $overrides = []): array {
        $startdate = [
            'year' => (int)userdate((int)$event->starttime, '%Y'),
            'month' => (int)userdate((int)$event->starttime, '%m'),
            'day' => (int)userdate((int)$event->starttime, '%d'),
        ];

        $data = [
            'cmid' => $context->instanceid,
            'id' => $event->id,
            '_qf__mod_bookit_form_edit_event_form' => 1,
            'editevent' => 1,
            'editinternal' => 1,
            'editinternalnotes' => 1,
            'editbookingstatus' => 1,
            'bookit_resources_section' => '1',
            'name' => $event->name,
            'semester' => $event->semester,
            'institutionid' => $event->institutionid,
            'roomid' => $event->roomid,
            'duration' => $event->duration,
            'participantsamount' => $event->participantsamount,
            'personinchargeid' => $event->personinchargeid,
            'otherexaminers' => $event->otherexaminers,
            'supportpersons' => $event->supportpersons,
            'coursetemplate' => $event->coursetemplate,
            'compensationfordisadvantages' => $event->compensationfordisadvantages,
            'notes' => $event->notes,
            'internalnotes' => $event->internalnotes,
            'refcourseid' => $event->refcourseid,
            'extratimebefore' => $event->extratimebefore,
            'extratimeafter' => $event->extratimeafter,
            'bookingstatus' => $event->bookingstatus,
            'starttime' => $event->starttime,
            'startdate' => $startdate,
            'usermodified' => $event->usermodified,
            'submitbutton' => 'Save changes',
        ];

        return array_merge($data, $overrides);
    }

    /**
     * Submit the edit event form.
     */
    private function submit_edit_event_form(
        context_module $context,
        int $eventid,
        int $userid,
        array $overrides = []
    ): void {
        $this->setUser($userid);

        $event = event_manager::get_event($eventid);
        $formdata = $this->build_formdata_from_event($event, $context, $overrides);
        $formdata['sesskey'] = sesskey();
        $_POST['sesskey'] = $formdata['sesskey'];

        foreach ($event as $key => $value) {
            if (str_starts_with((string)$key, 'checkbox_') && !array_key_exists($key, $formdata)) {
                $formdata[$key] = $value;
            }
        }

        $form = new edit_event_form(null, null, 'post', '', [], true, $formdata, true);
        $form->set_data_for_dynamic_submission();

        $submission = (object) array_merge((array) event_manager::get_event($eventid), $formdata);
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($submission->$key);
            } else {
                $submission->$key = $value;
            }
        }
        $form->set_data($submission);

        $this->assertTrue($form->is_submitted(), 'Form was not marked as submitted.');
        if (!$form->is_validated()) {
            $reflection = new \ReflectionClass($form);
            $mformproperty = $reflection->getProperty('_form');
            $mformproperty->setAccessible(true);
            $mform = $mformproperty->getValue($form);
            $this->fail('Form validation failed: ' . json_encode($mform->_errors ?? []));
        }
        $form->process_dynamic_submission();
    }

    /**
     * Return resource IDs assigned to an event.
     *
     * @return int[]
     */
    private function get_assigned_resource_ids(int $eventid): array {
        global $DB;
        $rows = $DB->get_records('bookit_event_resource', ['eventid' => $eventid]);
        return array_map(static fn(\stdClass $row): int => (int) $row->resourceid, array_values($rows));
    }

    /**
     * Create a persisted event with optional resource mappings.
     *
     * @param int[] $resourceids
     */
    private function create_event_with_resources(
        context_module $context,
        int $userid,
        int $bookingstatus,
        array $resourceids = []
    ): bookit_event {
        $examiner = $this->getDataGenerator()->create_user();
        $starttime = strtotime('+14 days 09:00:00');
        $endtime = strtotime('+14 days 11:00:00');
        $mappings = [];
        foreach ($resourceids as $rid) {
            $mappings[] = (object)['resourceid' => (int)$rid, 'amount' => 1];
        }

        $this->setUser($userid);
        $event = new bookit_event(
            0,
            'Inactive resource event',
            20261,
            1,
            $starttime,
            $endtime,
            120,
            $this->roomid,
            10,
            '',
            event_access_manager::BOOKINGSTATUS_NEW,
            (int)$examiner->id,
            '',
            0,
            'Notes',
            '',
            '',
            0,
            0,
            null,
            $userid,
            $userid,
            time(),
            time(),
            $mappings,
        );

        $saved = event_manager::save_event_with_lifecycle_tracking($event, null, $userid, $context);
        if ($bookingstatus !== event_access_manager::BOOKINGSTATUS_NEW) {
            $record = event_manager::get_event((int)$saved->id);
            event_manager::transition_booking_status($record, $bookingstatus, $userid, $context);
        }

        return bookit_event::from_database((int)$saved->id);
    }

    /**
     * Mark a catalog resource inactive in the database.
     *
     * @param int $resourceid
     */
    private function deactivate_resource(int $resourceid): void {
        global $DB;
        $DB->set_field('bookit_resource', 'active', 0, ['id' => $resourceid]);
    }

    /**
     * Return IDs from the active-only grouped catalog list.
     *
     * @return int[]
     */
    private function get_grouped_active_resource_ids(): array {
        $ids = [];
        foreach (resource_manager::get_active_resources_grouped() as $group) {
            foreach ($group['resources'] as $resource) {
                $ids[] = (int)$resource['id'];
            }
        }
        return $ids;
    }

    public function test_save_keeps_both_mappings_when_one_resource_is_deactivated(): void {
        global $DB;

        $editor = $this->getDataGenerator()->create_user();
        $context = $this->create_editor_context((int)$editor->id);
        $event = $this->create_event_with_resources(
            $context,
            (int)$editor->id,
            event_access_manager::BOOKINGSTATUS_IN_PROGRESS,
            [$this->resourceid, $this->resourceid2]
        );

        $this->deactivate_resource($this->resourceid2);

        $this->submit_edit_event_form(
            $context,
            (int)$event->id,
            (int)$editor->id,
            [
                'checkbox_' . $this->resourceid => 1,
                'checkbox_' . $this->resourceid2 => 1,
                'notes' => 'After deactivate',
            ]
        );

        $assigned = $this->get_assigned_resource_ids((int)$event->id);
        $this->assertCount(2, $DB->get_records('bookit_event_resource', ['eventid' => $event->id]));
        $this->assertContains($this->resourceid, $assigned);
        $this->assertContains($this->resourceid2, $assigned);
    }

    public function test_explicit_uncheck_removes_only_inactive_mapping(): void {
        global $DB;

        $editor = $this->getDataGenerator()->create_user();
        $context = $this->create_editor_context((int)$editor->id);
        $event = $this->create_event_with_resources(
            $context,
            (int)$editor->id,
            event_access_manager::BOOKINGSTATUS_IN_PROGRESS,
            [$this->resourceid, $this->resourceid2]
        );

        $this->deactivate_resource($this->resourceid2);

        $this->submit_edit_event_form(
            $context,
            (int)$event->id,
            (int)$editor->id,
            [
                'checkbox_' . $this->resourceid => 1,
                'checkbox_' . $this->resourceid2 => 0,
            ]
        );

        $assigned = $this->get_assigned_resource_ids((int)$event->id);
        $this->assertCount(1, $DB->get_records('bookit_event_resource', ['eventid' => $event->id]));
        $this->assertContains($this->resourceid, $assigned);
        $this->assertNotContains($this->resourceid2, $assigned);
    }

    public function test_single_inactive_resource_mapping_is_retained_on_save(): void {
        global $DB;

        $editor = $this->getDataGenerator()->create_user();
        $context = $this->create_editor_context((int)$editor->id);
        $event = $this->create_event_with_resources(
            $context,
            (int)$editor->id,
            event_access_manager::BOOKINGSTATUS_IN_PROGRESS,
            [$this->resourceid]
        );

        $this->deactivate_resource($this->resourceid);

        $this->submit_edit_event_form(
            $context,
            (int)$event->id,
            (int)$editor->id,
            [
                'checkbox_' . $this->resourceid => 1,
            ]
        );

        $this->assertCount(1, $DB->get_records('bookit_event_resource', ['eventid' => $event->id]));
        $this->assertContains($this->resourceid, $this->get_assigned_resource_ids((int)$event->id));
    }

    public function test_confirmed_booking_keeps_inactive_resource_mapping(): void {
        global $DB;

        $editor = $this->getDataGenerator()->create_user();
        $context = $this->create_editor_context((int)$editor->id);
        $event = $this->create_event_with_resources(
            $context,
            (int)$editor->id,
            event_access_manager::BOOKINGSTATUS_CONFIRMED,
            [$this->resourceid, $this->resourceid2]
        );

        $this->deactivate_resource($this->resourceid2);

        $this->submit_edit_event_form(
            $context,
            (int)$event->id,
            (int)$editor->id,
            ['internalnotes' => 'Confirmed save']
        );

        $this->assertCount(2, $DB->get_records('bookit_event_resource', ['eventid' => $event->id]));
    }

    public function test_new_booking_offers_only_active_resources(): void {
        $this->deactivate_resource($this->resourceid2);

        $activeids = $this->get_grouped_active_resource_ids();
        $this->assertContains($this->resourceid, $activeids);
        $this->assertNotContains($this->resourceid2, $activeids);
    }
}
