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
 * Unit tests for examcourse_manager.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use advanced_testcase;
use context_module;
use mod_bookit\local\entity\bookit_event;
use mod_bookit\local\install_helper;
use mod_bookit\task\create_exam_course_task;

/**
 * Unit tests for examcourse_manager.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\manager\examcourse_manager
 */
final class examcourse_manager_test extends advanced_testcase {
    /**
     * Ensure mapping table exists for Fresh-Install-Only schema on older test DBs.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('bookit_examcourse_map')) {
            $table = new \xmldb_table('bookit_examcourse_map');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('purpose', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('institutionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('semester', XMLDB_TYPE_INTEGER, '8', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('purpose_inst_sem_uk', XMLDB_INDEX_UNIQUE, ['purpose', 'institutionid', 'semester']);
            $dbman->create_table($table);
        }
        install_helper::ensure_examcourse_defaults();
        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 1, 'mod_bookit');
    }

    /**
     * Resolve precedence: semester+institution → institution-wide → site default.
     */
    public function test_resolve_category_precedence(): void {
        $sitecat = $this->getDataGenerator()->create_category(['name' => 'Site default']);
        $instcat = $this->getDataGenerator()->create_category(['name' => 'Institution override']);
        $semcat = $this->getDataGenerator()->create_category(['name' => 'Semester override']);

        set_config(
            install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID,
            $sitecat->id,
            'mod_bookit'
        );

        $institutionid = 7;
        $semester = 20261;

        $this->assertSame(
            (int)$sitecat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, $institutionid, $semester)
        );

        examcourse_manager::upsert_mapping(
            examcourse_manager::PURPOSE_TEMPLATE,
            $institutionid,
            examcourse_manager::SEMESTER_INSTITUTION_WIDE,
            (int)$instcat->id
        );
        $this->assertSame(
            (int)$instcat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, $institutionid, $semester)
        );

        examcourse_manager::upsert_mapping(
            examcourse_manager::PURPOSE_TEMPLATE,
            $institutionid,
            $semester,
            (int)$semcat->id
        );
        $this->assertSame(
            (int)$semcat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, $institutionid, $semester)
        );

        // Other institution falls back to site default.
        $this->assertSame(
            (int)$sitecat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, 99, $semester)
        );
    }

    /**
     * Target purpose uses its own site default config key.
     */
    public function test_resolve_target_site_default(): void {
        $target = $this->getDataGenerator()->create_category();
        set_config(install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID, $target->id, 'mod_bookit');
        $this->assertSame(
            (int)$target->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TARGET, 1, 0)
        );
    }

    /**
     * Autocreate skips when disabled or no template; creates once when enabled.
     */
    public function test_ensure_created_idempotent_and_gates(): void {
        global $DB;

        $this->setAdminUser();
        $templatecat = $this->getDataGenerator()->create_category();
        $targetcat = $this->getDataGenerator()->create_category();
        $template = $this->getDataGenerator()->create_course(['category' => $templatecat->id, 'fullname' => 'Template']);
        set_config(install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID, $targetcat->id, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 0, 'mod_bookit');

        $pic = $this->getDataGenerator()->create_user();
        $event = (object)[
            'id' => 0,
            'name' => 'Exam Event',
            'coursetemplate' => (int)$template->id,
            'refcourseid' => 0,
            'institutionid' => 1,
            'semester' => 20261,
            'personinchargeid' => (int)$pic->id,
            'otherexaminers' => '',
            'usercreated' => (int)$pic->id,
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
            'timemodified' => time(),
        ];
        // Persist a minimal event row for update_record in ensure_created.
        $event->id = (int)$DB->insert_record('bookit_event', (object)[
            'name' => $event->name,
            'semester' => $event->semester,
            'institutionid' => $event->institutionid,
            'starttime' => time() + DAYSECS,
            'endtime' => time() + DAYSECS + HOURSECS,
            'duration' => 60,
            'bookingstatus' => $event->bookingstatus,
            'personinchargeid' => $event->personinchargeid,
            'otherexaminers' => '',
            'coursetemplate' => $event->coursetemplate,
            'refcourseid' => 0,
            'usercreated' => $event->usercreated,
            'usermodified' => $event->usercreated,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $r1 = examcourse_manager::ensure_created_for_event($event);
        $this->assertFalse($r1['created']);
        $this->assertSame('autocreate_disabled', $r1['skipped']);

        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        $event = $DB->get_record('bookit_event', ['id' => $event->id], '*', MUST_EXIST);
        $r2 = examcourse_manager::ensure_created_for_event($event);
        $this->assertTrue($r2['queued']);
        $this->runAdhocTasks(create_exam_course_task::class);

        $event = $DB->get_record('bookit_event', ['id' => $event->id], '*', MUST_EXIST);
        $courseid = (int)$event->refcourseid;
        $this->assertGreaterThan(0, $courseid);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $this->assertSame(0, (int)$course->visible);
        $this->assertSame((int)$targetcat->id, (int)$course->category);

        $r3 = examcourse_manager::ensure_created_for_event($event);
        $this->assertFalse($r3['created']);
        $this->assertFalse($r3['queued']);
        $this->assertSame('already_created', $r3['skipped']);
        $this->assertSame($courseid, $r3['courseid']);
    }

    /**
     * No template → skip create.
     */
    public function test_ensure_created_skips_without_template(): void {
        global $DB;
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        $eventid = (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'No template',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => time() + DAYSECS,
            'endtime' => time() + DAYSECS + HOURSECS,
            'duration' => 60,
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
            'coursetemplate' => 0,
            'refcourseid' => 0,
            'usercreated' => 2,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $event = $DB->get_record('bookit_event', ['id' => $eventid], '*', MUST_EXIST);
        $r = examcourse_manager::ensure_created_for_event($event);
        $this->assertFalse($r['created']);
        $this->assertSame('no_template', $r['skipped']);
    }
    /**
     * Configure exam course creation with a visible template and a target category.
     *
     * @return array{template:\stdClass,targetcat:\stdClass,templatecat:\stdClass}
     */
    private function configure_examcourse(): array {
        $templatecat = $this->getDataGenerator()->create_category(['name' => 'Templates']);
        $targetcat = $this->getDataGenerator()->create_category(['name' => 'Exam courses']);
        $template = $this->getDataGenerator()->create_course(['category' => $templatecat->id, 'fullname' => 'Template']);
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID, $templatecat->id, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID, $targetcat->id, 'mod_bookit');
        return ['template' => $template, 'targetcat' => $targetcat, 'templatecat' => $templatecat];
    }

    /**
     * Create a booking activity and return its module context.
     *
     * @return context_module
     */
    private function create_bookit_context(): context_module {
        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id]);
        return context_module::instance($bookit->cmid);
    }

    /**
     * Insert a booking with the given status and template.
     *
     * @param int $status
     * @param int $templateid
     * @param int $personinchargeid
     * @param string $otherexaminers
     * @return int Event id
     */
    private function create_booking(int $status, int $templateid, int $personinchargeid = 0, string $otherexaminers = ''): int {
        global $DB;
        $roomid = $this->getDataGenerator()->get_plugin_generator('mod_bookit')->create_room(['name' => 'Exam room ' . uniqid()]);
        $start = time() + DAYSECS;
        return (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'Exam booking',
            'semester' => event_manager::get_current_semester($start),
            'institutionid' => 1,
            'starttime' => $start,
            'endtime' => $start + HOURSECS,
            'duration' => 60,
            'roomid' => $roomid,
            'bookingstatus' => $status,
            'personinchargeid' => $personinchargeid,
            'otherexaminers' => $otherexaminers,
            'coursetemplate' => $templateid,
            'refcourseid' => 0,
            'usercreated' => 2,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Confirm a booking through the status transition as the admin.
     *
     * @param int $eventid
     * @param context_module $context
     * @return void
     */
    private function confirm_booking(int $eventid, context_module $context): void {
        event_manager::transition_booking_status(
            event_manager::get_event($eventid),
            event_access_manager::BOOKINGSTATUS_CONFIRMED,
            2,
            $context
        );
    }

    /**
     * Number of queued exam course tasks.
     *
     * @return int
     */
    private function count_queued_tasks(): int {
        return count(\core\task\manager::get_adhoc_tasks(create_exam_course_task::class));
    }

    /**
     * Status button path: confirming queues one task and the task creates the course.
     */
    public function test_transition_to_confirmed_queues_task_that_creates_course(): void {
        global $DB;
        $this->setAdminUser();
        $setup = $this->configure_examcourse();
        $context = $this->create_bookit_context();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_IN_PROGRESS, (int)$setup['template']->id);

        $event = event_manager::get_event($eventid);
        event_manager::transition_booking_status($event, event_access_manager::BOOKINGSTATUS_CONFIRMED, 2, $context);

        $this->assertSame(1, $this->count_queued_tasks());
        $this->assertSame(0, (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]));

        $this->runAdhocTasks(create_exam_course_task::class);

        $refcourseid = (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]);
        $this->assertGreaterThan(0, $refcourseid);
        $this->assertSame(1, $DB->count_records('course', ['category' => $setup['targetcat']->id]));
    }

    /**
     * Booking form path: saving the confirmed status queues the task as well.
     */
    public function test_form_save_with_confirmed_status_queues_task(): void {
        global $DB;
        $this->setAdminUser();
        $setup = $this->configure_examcourse();
        $context = $this->create_bookit_context();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_IN_PROGRESS, (int)$setup['template']->id);

        $previous = bookit_event::from_database($eventid);
        $updated = bookit_event::from_database($eventid);
        $updated->bookingstatus = event_access_manager::BOOKINGSTATUS_CONFIRMED;
        event_manager::save_event_with_lifecycle_tracking($updated, $previous, 2, $context);

        $this->assertSame(1, $this->count_queued_tasks());
        $this->runAdhocTasks(create_exam_course_task::class);
        $this->assertGreaterThan(0, (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]));
    }

    /**
     * Confirming again keeps the single exam course.
     */
    public function test_reconfirm_does_not_create_second_course(): void {
        global $DB;
        $this->setAdminUser();
        $setup = $this->configure_examcourse();
        $context = $this->create_bookit_context();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_IN_PROGRESS, (int)$setup['template']->id);

        $this->confirm_booking($eventid, $context);
        $this->runAdhocTasks(create_exam_course_task::class);
        $first = (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]);

        $event = event_manager::get_event($eventid);
        $result = examcourse_manager::ensure_created_for_event($event, 2);
        $this->assertSame('already_created', $result['skipped']);
        $this->assertSame(0, $this->count_queued_tasks());
        $this->runAdhocTasks(create_exam_course_task::class);

        $this->assertSame($first, (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]));
        $this->assertSame(1, $DB->count_records('course', ['category' => $setup['targetcat']->id]));
    }

    /**
     * Without template or with creation switched off nothing is queued.
     */
    public function test_no_template_or_disabled_queues_nothing(): void {
        $this->setAdminUser();
        $setup = $this->configure_examcourse();
        $context = $this->create_bookit_context();

        $notemplate = $this->create_booking(event_access_manager::BOOKINGSTATUS_IN_PROGRESS, 0);
        $this->confirm_booking($notemplate, $context);
        $this->assertSame(0, $this->count_queued_tasks());

        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 0, 'mod_bookit');
        $disabled = $this->create_booking(event_access_manager::BOOKINGSTATUS_IN_PROGRESS, (int)$setup['template']->id);
        $this->confirm_booking($disabled, $context);
        $this->assertSame(0, $this->count_queued_tasks());
    }

    /**
     * The created course is hidden, starts today and enrols the examiners as editing teachers.
     */
    public function test_created_course_is_hidden_starts_today_and_enrols_examiners(): void {
        global $DB;
        $this->setAdminUser();
        $setup = $this->configure_examcourse();
        $context = $this->create_bookit_context();
        $pic = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $eventid = $this->create_booking(
            event_access_manager::BOOKINGSTATUS_IN_PROGRESS,
            (int)$setup['template']->id,
            (int)$pic->id,
            (string)$other->id
        );

        $this->confirm_booking($eventid, $context);
        $this->runAdhocTasks(create_exam_course_task::class);

        $refcourseid = (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]);
        $course = $DB->get_record('course', ['id' => $refcourseid], '*', MUST_EXIST);
        $this->assertSame(0, (int)$course->visible);
        $this->assertSame(usergetmidnight(time(), \core_date::get_server_timezone()), (int)$course->startdate);
        $coursecontext = \context_course::instance($course->id);
        $teacherroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        foreach ([$pic, $other] as $user) {
            $this->assertTrue(user_has_role_assignment($user->id, $teacherroleid, $coursecontext->id));
        }
    }
    /**
     * Template options follow course visibility: visible courses without enrolment, hidden courses for nobody.
     */
    public function test_template_options_offer_visible_courses_only(): void {
        $setup = $this->configure_examcourse();
        $hidden = $this->getDataGenerator()->create_course([
            'category' => $setup['templatecat']->id,
            'fullname' => 'Hidden template',
            'visible' => 0,
        ]);
        $booker = $this->getDataGenerator()->create_user();
        $serviceteam = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($serviceteam->id, $hidden->id, 'editingteacher');
        $event = (object)[
            'institutionid' => 1,
            'semester' => event_manager::get_current_semester(),
            'usercreated' => (int)$booker->id,
        ];

        // The same visible-only list applies to every selecting user, whatever their enrolment.
        $this->assertSame([(int)$setup['template']->id], array_keys(examcourse_manager::get_template_options($event)));
    }

    /**
     * The template field is hidden without template category or without visible course.
     */
    public function test_template_field_hidden_without_category_or_visible_course(): void {
        global $DB;
        $setup = $this->configure_examcourse();
        $event = (object)['institutionid' => 1, 'semester' => event_manager::get_current_semester(), 'usercreated' => 2];
        $this->assertTrue(examcourse_manager::should_show_template_field($event));

        $DB->set_field('course', 'visible', 0, ['id' => $setup['template']->id]);
        $this->assertFalse(examcourse_manager::should_show_template_field($event));

        set_config(install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID, 0, 'mod_bookit');
        $this->assertFalse(examcourse_manager::should_show_template_field($event));
    }

    /**
     * Optional teacher courses of the booking person never add a hidden course.
     */
    public function test_teacher_courses_option_skips_hidden_courses(): void {
        $this->configure_examcourse();
        set_config(install_helper::CONFIG_EXAMCOURSE_INCLUDE_TEACHER_COURSES, 1, 'mod_bookit');
        $booker = $this->getDataGenerator()->create_user();
        $visible = $this->getDataGenerator()->create_course(['fullname' => 'Own visible course']);
        $hidden = $this->getDataGenerator()->create_course(['fullname' => 'Own hidden course', 'visible' => 0]);
        $this->getDataGenerator()->enrol_user($booker->id, $visible->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($booker->id, $hidden->id, 'editingteacher');
        $event = (object)[
            'institutionid' => 1,
            'semester' => event_manager::get_current_semester(),
            'usercreated' => (int)$booker->id,
        ];

        $options = examcourse_manager::get_template_options($event);
        $this->assertArrayHasKey((int)$visible->id, $options);
        $this->assertArrayNotHasKey((int)$hidden->id, $options);
    }

    /**
     * A missing template is shown to the confirming user right away and nothing is linked.
     */
    public function test_missing_template_shows_notification(): void {
        global $DB;
        $this->setAdminUser();
        $this->configure_examcourse();
        $context = $this->create_bookit_context();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_IN_PROGRESS, 999999);
        \core\notification::fetch();

        $this->confirm_booking($eventid, $context);

        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $reason = get_string('examcourse_reason_template_missing', 'mod_bookit');
        $expected = get_string('examcourse_not_created', 'mod_bookit', $reason);
        $this->assertContains($expected, $messages);
        $this->assertSame(0, $this->count_queued_tasks());
        $this->assertSame(0, (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]));
    }

    /**
     * Two confirmations in a row queue one task, and the task creates one course.
     */
    public function test_two_quick_confirmations_queue_one_task(): void {
        global $DB;
        $this->setAdminUser();
        $setup = $this->configure_examcourse();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_CONFIRMED, (int)$setup['template']->id);

        $first = examcourse_manager::ensure_created_for_event(event_manager::get_event($eventid), 2);
        $second = examcourse_manager::ensure_created_for_event(event_manager::get_event($eventid), 2);

        $this->assertTrue($first['queued']);
        $this->assertSame('already_queued', $second['skipped']);
        $this->assertSame(1, $this->count_queued_tasks());
        $this->runAdhocTasks(create_exam_course_task::class);
        $this->assertSame(1, $DB->count_records('course', ['category' => $setup['targetcat']->id]));
    }

    /**
     * While another request holds the booking lock, no task is queued; after the release one is.
     */
    public function test_held_lock_queues_nothing_until_released(): void {
        global $CFG;
        $this->setAdminUser();
        $setup = $this->configure_examcourse();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_CONFIRMED, (int)$setup['template']->id);

        // Postgres advisory locks are re-entrant within one connection, so the overlap of two
        // requests is shown with the record lock factory, which hands out one owner per lock.
        $CFG->lock_factory = '\core\lock\db_record_lock_factory';
        $held = \core\lock\lock_config::get_lock_factory('mod_bookit')->get_lock('examcourse-' . $eventid, 0);
        $this->assertNotFalse($held);

        $result = examcourse_manager::ensure_created_for_event(event_manager::get_event($eventid), 2);
        $this->assertSame('locked', $result['skipped']);
        $this->assertSame(0, $this->count_queued_tasks());

        $held->release();
        $result = examcourse_manager::ensure_created_for_event(event_manager::get_event($eventid), 2);
        $this->assertTrue($result['queued']);
        $this->assertSame(1, $this->count_queued_tasks());
    }

    /**
     * A copy that fails in the task sends exactly one message to the confirming user.
     */
    public function test_failed_copy_messages_confirmer(): void {
        $this->setAdminUser();
        message_update_providers('mod_bookit');
        $setup = $this->configure_examcourse();
        $confirmer = $this->getDataGenerator()->create_user();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_CONFIRMED, (int)$setup['template']->id);
        examcourse_manager::ensure_created_for_event(event_manager::get_event($eventid), (int)$confirmer->id);
        delete_course($setup['template']->id, false);

        $sink = $this->redirectMessages();
        $this->runAdhocTasks(create_exam_course_task::class);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame('bookit_examcourse_failed', $message->eventtype);
        $this->assertSame((int)$confirmer->id, (int)$message->useridto);
    }

    /**
     * The new feedback strings exist in English and German.
     */
    public function test_feedback_strings_exist_in_both_languages(): void {
        $strings = get_string_manager();
        foreach (['examcourse_not_created', 'examcourse_copy_incomplete'] as $key) {
            foreach (['en', 'de'] as $lang) {
                $this->assertTrue($strings->string_exists($key, 'mod_bookit'));
                $this->assertNotEmpty(trim($strings->get_string($key, 'mod_bookit', 'x', $lang)));
            }
        }
    }

    /**
     * A template activity that the copy does not take along still links the course and reports the incomplete copy.
     */
    public function test_incomplete_copy_links_course_and_messages_confirmer(): void {
        global $DB;
        $this->setAdminUser();
        message_update_providers('mod_bookit');
        $setup = $this->configure_examcourse();
        // The backup takes activities section by section, so an activity outside of every section is not copied.
        $orphan = $this->getDataGenerator()->create_module('label', ['course' => $setup['template']->id]);
        foreach ($DB->get_records('course_sections', ['course' => $setup['template']->id]) as $section) {
            $sequence = array_diff(explode(',', (string)$section->sequence), [(string)$orphan->cmid]);
            $DB->set_field('course_sections', 'sequence', implode(',', $sequence), ['id' => $section->id]);
        }
        $DB->set_field('course_modules', 'section', 0, ['id' => $orphan->cmid]);
        $confirmer = $this->getDataGenerator()->create_user();
        $eventid = $this->create_booking(event_access_manager::BOOKINGSTATUS_CONFIRMED, (int)$setup['template']->id);
        examcourse_manager::ensure_created_for_event(event_manager::get_event($eventid), (int)$confirmer->id);

        $sink = $this->redirectMessages();
        $this->runAdhocTasks(create_exam_course_task::class);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(1, $DB->count_records('course', ['category' => $setup['targetcat']->id]));
        $this->assertGreaterThan(0, (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]));
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int)$confirmer->id, (int)$message->useridto);
        $this->assertStringContainsString(get_string('examcourse_copy_incomplete', 'mod_bookit'), $message->fullmessage);
    }
}
