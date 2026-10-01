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
 * Unit tests for the exam course creation task.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\task;

use advanced_testcase;
use context_module;
use mod_bookit\local\install_helper;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\local\manager\event_manager;

/**
 * Unit tests for the exam course creation task.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\task\create_exam_course_task
 * @covers      \mod_bookit\local\manager\examcourse_manager
 */
final class create_exam_course_task_test extends advanced_testcase {
    /** @var context_module Booking activity context. */
    private context_module $context;

    /** @var \stdClass Template course. */
    private \stdClass $template;

    /** @var \stdClass Service team user who confirms. */
    private \stdClass $serviceteam;

    /**
     * Configure exam course creation and a service team user with the confirming capabilities.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        message_update_providers('mod_bookit');

        install_helper::ensure_examcourse_defaults();
        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 1, 'mod_bookit');
        $templatecat = $this->getDataGenerator()->create_category();
        $targetcat = $this->getDataGenerator()->create_category();
        $this->template = $this->getDataGenerator()->create_course(['category' => $templatecat->id]);
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID, $templatecat->id, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID, $targetcat->id, 'mod_bookit');

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id]);
        $this->context = context_module::instance($bookit->cmid);
        $this->serviceteam = $this->getDataGenerator()->create_user();
    }

    /**
     * Insert a booking in progress with the template.
     *
     * @return int
     */
    private function create_booking(): int {
        global $DB;
        $roomid = $this->getDataGenerator()->get_plugin_generator('mod_bookit')->create_room(['name' => 'Task room ' . uniqid()]);
        $start = time() + DAYSECS;
        return (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'Task booking',
            'semester' => event_manager::get_current_semester($start),
            'institutionid' => 1,
            'starttime' => $start,
            'endtime' => $start + HOURSECS,
            'duration' => 60,
            'roomid' => $roomid,
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_IN_PROGRESS,
            'coursetemplate' => (int)$this->template->id,
            'refcourseid' => 0,
            'usercreated' => 2,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Confirming in the web request keeps the signed-in user and only queues the copy.
     */
    public function test_confirming_keeps_the_signed_in_user_and_queues_as_admin(): void {
        global $DB, $USER;
        $this->setUser($this->serviceteam);
        $eventid = $this->create_booking();

        event_manager::transition_booking_status(
            event_manager::get_event($eventid),
            event_access_manager::BOOKINGSTATUS_CONFIRMED,
            (int)$this->serviceteam->id,
            $this->context
        );

        $this->assertSame((int)$this->serviceteam->id, (int)$USER->id);
        $this->assertSame(0, (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]));

        $tasks = \core\task\manager::get_adhoc_tasks(create_exam_course_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertSame((int)get_admin()->id, (int)$task->get_userid());
        $data = (object)$task->get_custom_data();
        $this->assertSame($eventid, (int)$data->eventid);
        $this->assertSame((int)$this->serviceteam->id, (int)$data->confirmeruserid);
    }

    /**
     * A copy that fails in the task leaves the web user untouched, creates no course and messages the confirmer.
     */
    public function test_failed_copy_keeps_user_creates_no_course_and_messages_confirmer(): void {
        global $DB, $USER;
        $this->setUser($this->serviceteam);
        $eventid = $this->create_booking();

        event_manager::transition_booking_status(
            event_manager::get_event($eventid),
            event_access_manager::BOOKINGSTATUS_CONFIRMED,
            (int)$this->serviceteam->id,
            $this->context
        );
        $this->assertSame((int)$this->serviceteam->id, (int)$USER->id);

        // The template disappears before the task runs.
        delete_course($this->template->id, false);
        $coursesbefore = $DB->count_records('course');

        $sink = $this->redirectMessages();
        $this->runAdhocTasks(create_exam_course_task::class);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame($coursesbefore, $DB->count_records('course'));
        $this->assertSame(0, (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]));
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame('bookit_examcourse_failed', $message->eventtype);
        $this->assertSame((int)$this->serviceteam->id, (int)$message->useridto);
    }
}
