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
 * Booking form tests for the exam course template and the exam course link.
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
use mod_bookit\local\install_helper;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\local\manager\event_manager;
use mod_bookit\task\create_exam_course_task;

/**
 * Booking form tests for the exam course template and the exam course link.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\form\edit_event_form
 * @runTestsInSeparateProcesses
 */
final class edit_event_form_examcourse_test extends advanced_testcase {
    /** @var context_module Booking activity context. */
    private context_module $context;

    /** @var int Room used by all bookings. */
    private int $roomid;

    /** @var \stdClass Visible template course in the template category. */
    private \stdClass $template;

    /** @var \stdClass Course outside the template selection. */
    private \stdClass $foreign;

    /** @var \stdClass Booking person with the template selection right. */
    private \stdClass $booker;

    /** @var \stdClass Service team user. */
    private \stdClass $serviceteam;

    /** @var int Booking person role id. */
    private int $bookerroleid;

    /**
     * Prepare categories, courses, the booking activity and both roles.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        message_update_providers('mod_bookit');
        install_helper::ensure_booking_status_notification_defaults();
        install_helper::ensure_examcourse_defaults();

        $templatecat = $this->getDataGenerator()->create_category();
        $targetcat = $this->getDataGenerator()->create_category();
        $this->template = $this->getDataGenerator()->create_course(['category' => $templatecat->id, 'fullname' => 'Template']);
        $this->foreign = $this->getDataGenerator()->create_course(['fullname' => 'Foreign course']);
        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 1, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID, $templatecat->id, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID, $targetcat->id, 'mod_bookit');

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id]);
        $this->context = context_module::instance($bookit->cmid);
        $this->roomid = $this->getDataGenerator()->get_plugin_generator('mod_bookit')->create_room(['name' => 'Exam form room']);

        \update_capabilities('mod_bookit');
        $this->booker = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->booker->id, $course->id);
        $this->bookerroleid = \create_role('Exam form booker', 'examformbooker', 'student');
        foreach (['view', 'addevent', 'viewownoverview', 'viewalldetailsofownevent', 'selectexamcoursetemplate'] as $cap) {
            \assign_capability('mod/bookit:' . $cap, CAP_ALLOW, $this->bookerroleid, $this->context->id, true);
        }
        \role_assign($this->bookerroleid, $this->booker->id, $this->context->id);

        $this->serviceteam = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->serviceteam->id, $course->id);
        $stroleid = \create_role('Exam form service team', 'examformserviceteam', 'student');
        $caps = ['view', 'addevent', 'editevent', 'viewownoverview', 'viewalldetailsofevent', 'managebasics', 'editinternal',
            'selectexamcoursetemplate'];
        foreach ($caps as $cap) {
            \assign_capability('mod/bookit:' . $cap, CAP_ALLOW, $stroleid, $this->context->id, true);
        }
        \role_assign($stroleid, $this->serviceteam->id, $this->context->id);
        \accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Build a complete form payload.
     *
     * @param array $overrides
     * @return array
     */
    private function payload(array $overrides): array {
        $start = strtotime('+14 days 09:00');
        $data = [
            'cmid' => $this->context->instanceid,
            'id' => 0,
            '_qf__mod_bookit_form_edit_event_form' => 1,
            'name' => 'Exam form booking',
            'semester' => event_manager::get_current_semester($start),
            'institutionid' => 1,
            'roomid' => $this->roomid,
            'duration' => 120,
            'participantsamount' => 10,
            'personinchargeid' => 0,
            'otherexaminers' => '',
            'supportpersons' => '',
            'coursetemplate' => 0,
            'compensationfordisadvantages' => '',
            'notes' => '',
            'internalnotes' => '',
            'refcourseid' => 0,
            'extratimebefore' => 0,
            'extratimeafter' => 0,
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_NEW,
            'starttime' => $start,
            'startdate' => [
                'year' => (int)userdate($start, '%Y'),
                'month' => (int)userdate($start, '%m'),
                'day' => (int)userdate($start, '%d'),
                'hour' => (int)userdate($start, '%H'),
                'minute' => 0,
            ],
            'submitbutton' => 'Save changes',
        ];
        return array_merge($data, $overrides);
    }

    /**
     * Submit the form as the given user and return the validation errors (empty when saved).
     *
     * @param \stdClass $user
     * @param array $data
     * @return array
     */
    private function submit(\stdClass $user, array $data): array {
        $this->setUser($user);
        $data['sesskey'] = sesskey();
        $_POST['sesskey'] = $data['sesskey'];
        $form = new edit_event_form(null, null, 'post', '', [], true, $data, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_submitted());
        if (!$form->is_validated()) {
            $reflection = new \ReflectionClass($form);
            $property = $reflection->getProperty('_form');
            $property->setAccessible(true);
            return $property->getValue($form)->_errors ?? [];
        }
        $form->process_dynamic_submission();
        return [];
    }

    /**
     * Find the stored booking by name.
     *
     * @param string $name
     * @return \stdClass|null
     */
    private function booking(string $name): ?\stdClass {
        global $DB;
        return $DB->get_record_select(
            'bookit_event',
            $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name'),
            ['name' => $name],
            '*',
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * A template outside the selection is rejected and nothing is stored.
     */
    public function test_foreign_template_is_rejected(): void {
        $errors = $this->submit($this->booker, $this->payload([
            'name' => 'Foreign template booking',
            'coursetemplate' => (int)$this->foreign->id,
        ]));

        $this->assertArrayHasKey('coursetemplate', $errors);
        $this->assertNull($this->booking('Foreign template booking'));
    }

    /**
     * Without any offered template the field is hidden, and a sent course id is still rejected.
     */
    public function test_foreign_template_rejected_when_no_template_is_offered(): void {
        set_config(install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID, 0, 'mod_bookit');

        $errors = $this->submit($this->booker, $this->payload([
            'name' => 'Hidden field template booking',
            'coursetemplate' => (int)$this->foreign->id,
        ]));

        $this->assertArrayHasKey('coursetemplate', $errors);
        $this->assertNull($this->booking('Hidden field template booking'));
    }

    /**
     * A foreign exam course link is dropped for a new booking.
     */
    public function test_foreign_link_dropped_for_new_booking(): void {
        $this->assertSame([], $this->submit($this->booker, $this->payload([
            'name' => 'New booking with link',
            'refcourseid' => (int)$this->foreign->id,
        ])));

        $this->assertSame(0, (int)$this->booking('New booking with link')->refcourseid);
    }

    /**
     * A foreign exam course link is dropped for an existing booking without exam course.
     */
    public function test_foreign_link_dropped_for_existing_booking(): void {
        $this->submit($this->booker, $this->payload(['name' => 'Existing booking']));
        $booking = $this->booking('Existing booking');

        $this->submit($this->booker, $this->payload([
            'id' => (int)$booking->id,
            'name' => 'Existing booking',
            'refcourseid' => (int)$this->foreign->id,
        ]));

        $this->assertSame(0, (int)$this->booking('Existing booking')->refcourseid);
    }

    /**
     * The service team cannot set the exam course link through the form either.
     */
    public function test_service_team_cannot_set_link(): void {
        $this->submit($this->serviceteam, $this->payload(['name' => 'Service booking']));
        $booking = $this->booking('Service booking');

        $this->submit($this->serviceteam, $this->payload([
            'id' => (int)$booking->id,
            'name' => 'Service booking',
            'refcourseid' => (int)$this->foreign->id,
        ]));

        $this->assertSame(0, (int)$this->booking('Service booking')->refcourseid);
    }

    /**
     * Confirming with an allowed template links the new exam course, not the template.
     */
    public function test_confirming_allowed_template_links_new_course(): void {
        $this->submit($this->booker, $this->payload([
            'name' => 'Allowed template booking',
            'coursetemplate' => (int)$this->template->id,
        ]));
        $booking = $this->booking('Allowed template booking');
        $this->assertSame((int)$this->template->id, (int)$booking->coursetemplate);

        event_manager::transition_booking_status(
            event_manager::get_event((int)$booking->id),
            event_access_manager::BOOKINGSTATUS_CONFIRMED,
            (int)$this->serviceteam->id,
            $this->context
        );
        $this->runAdhocTasks(create_exam_course_task::class);

        $refcourseid = (int)$this->booking('Allowed template booking')->refcourseid;
        $this->assertGreaterThan(0, $refcourseid);
        $this->assertNotSame((int)$this->template->id, $refcourseid);
        $this->assertNotSame((int)$this->foreign->id, $refcourseid);
    }

    /**
     * Without the selection right the template is read-only and a sent value is not stored.
     */
    public function test_template_is_read_only_without_selection_right(): void {
        global $DB;
        $this->submit($this->booker, $this->payload([
            'name' => 'Read-only template booking',
            'coursetemplate' => (int)$this->template->id,
        ]));
        $booking = $this->booking('Read-only template booking');

        \assign_capability('mod/bookit:selectexamcoursetemplate', CAP_PROHIBIT, $this->bookerroleid, $this->context->id, true);
        \accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->booker);
        $ajaxdata = ['cmid' => $this->context->instanceid, 'id' => (int)$booking->id];
        $form = new edit_event_form(null, null, 'post', '', [], true, $ajaxdata, true);
        $form->set_data_for_dynamic_submission();
        $reflection = new \ReflectionClass($form);
        $property = $reflection->getProperty('_form');
        $property->setAccessible(true);
        $mform = $property->getValue($form);
        $this->assertTrue($mform->elementExists('coursetemplate_readonly'));
        $this->assertSame('hidden', $mform->getElement('coursetemplate')->getType());

        $this->submit($this->booker, $this->payload([
            'id' => (int)$booking->id,
            'name' => 'Read-only template booking',
            'coursetemplate' => 0,
        ]));
        $this->assertSame((int)$this->template->id, (int)$DB->get_field('bookit_event', 'coursetemplate', ['id' => $booking->id]));
    }
}
