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
 * Exam course mapping, template options, and auto-create on confirmed.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use backup;
use backup_controller;
use backup_setting;
use dml_exception;
use mod_bookit\local\install_helper;
use mod_bookit\task\create_exam_course_task;
use restore_controller;
use restore_dbops;
use stdClass;

/**
 * Exam course workflow manager (WP-5).
 */
class examcourse_manager {
    /** Purpose: template course category. */
    public const PURPOSE_TEMPLATE = 'template';

    /** Purpose: target category for created exam courses. */
    public const PURPOSE_TARGET = 'target';

    /** Institution-wide override sentinel (no semester). */
    public const SEMESTER_INSTITUTION_WIDE = 0;

    /** Seconds to wait for the per-booking creation lock. */
    protected const LOCK_TIMEOUT = 3;

    /**
     * Whether autocreate-on-confirmed is enabled.
     *
     * @return bool
     */
    public static function is_autocreate_enabled(): bool {
        return (int)get_config('mod_bookit', install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED) === 1;
    }

    /**
     * Whether booking-person teacher courses are included in template options.
     *
     * @return bool
     */
    public static function include_teacher_courses(): bool {
        return (int)get_config('mod_bookit', install_helper::CONFIG_EXAMCOURSE_INCLUDE_TEACHER_COURSES) === 1;
    }

    /**
     * Resolve category id for a purpose given institution and semester.
     *
     * Precedence: institution+semester → institution-wide (semester 0) → site default → 0.
     *
     * @param string $purpose PURPOSE_TEMPLATE|PURPOSE_TARGET
     * @param int $institutionid
     * @param int $semester Event semester encoding; 0 skips semester-specific row
     * @return int Category id or 0 if unresolved
     * @throws dml_exception
     */
    public static function resolve_category(string $purpose, int $institutionid, int $semester = 0): int {
        global $DB;

        if ($purpose !== self::PURPOSE_TEMPLATE && $purpose !== self::PURPOSE_TARGET) {
            return 0;
        }

        if ($institutionid > 0 && $semester !== self::SEMESTER_INSTITUTION_WIDE) {
            $row = $DB->get_record('bookit_examcourse_map', [
                'purpose' => $purpose,
                'institutionid' => $institutionid,
                'semester' => $semester,
            ]);
            if ($row && (int)$row->categoryid > 0) {
                return (int)$row->categoryid;
            }
        }

        if ($institutionid > 0) {
            $row = $DB->get_record('bookit_examcourse_map', [
                'purpose' => $purpose,
                'institutionid' => $institutionid,
                'semester' => self::SEMESTER_INSTITUTION_WIDE,
            ]);
            if ($row && (int)$row->categoryid > 0) {
                return (int)$row->categoryid;
            }
        }

        $configkey = $purpose === self::PURPOSE_TEMPLATE
            ? install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID
            : install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID;
        $default = (int)get_config('mod_bookit', $configkey);
        return $default > 0 ? $default : 0;
    }

    /**
     * List mapping rows, optionally filtered by purpose.
     *
     * @param string|null $purpose
     * @return stdClass[]
     * @throws dml_exception
     */
    public static function list_mappings(?string $purpose = null): array {
        global $DB;
        $params = [];
        $select = '1=1';
        if ($purpose !== null) {
            $select = 'purpose = :purpose';
            $params['purpose'] = $purpose;
        }
        return array_values($DB->get_records_select(
            'bookit_examcourse_map',
            $select,
            $params,
            'purpose ASC, institutionid ASC, semester ASC'
        ));
    }

    /**
     * Upsert a mapping row.
     *
     * @param string $purpose
     * @param int $institutionid
     * @param int $semester Use 0 for institution-wide
     * @param int $categoryid
     * @return int Record id
     * @throws dml_exception
     */
    public static function upsert_mapping(string $purpose, int $institutionid, int $semester, int $categoryid): int {
        global $DB;

        if ($purpose !== self::PURPOSE_TEMPLATE && $purpose !== self::PURPOSE_TARGET) {
            throw new \invalid_parameter_exception('Invalid exam course map purpose');
        }
        if ($institutionid <= 0 || $categoryid <= 0) {
            throw new \invalid_parameter_exception('Institution and category are required');
        }

        $semester = max(0, $semester);
        $now = time();
        $existing = $DB->get_record('bookit_examcourse_map', [
            'purpose' => $purpose,
            'institutionid' => $institutionid,
            'semester' => $semester,
        ]);
        if ($existing) {
            $existing->categoryid = $categoryid;
            $existing->timemodified = $now;
            $DB->update_record('bookit_examcourse_map', $existing);
            return (int)$existing->id;
        }

        $record = (object)[
            'purpose' => $purpose,
            'institutionid' => $institutionid,
            'semester' => $semester,
            'categoryid' => $categoryid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int)$DB->insert_record('bookit_examcourse_map', $record);
    }

    /**
     * Delete a mapping by id.
     *
     * @param int $id
     * @return bool
     * @throws dml_exception
     */
    public static function delete_mapping(int $id): bool {
        global $DB;
        return $DB->delete_records('bookit_examcourse_map', ['id' => $id]);
    }

    /**
     * Whether the Exam course template field should be shown for an event context.
     *
     * @param stdClass $event
     * @return bool
     */
    public static function should_show_template_field(stdClass $event): bool {
        if (!install_helper::is_resources_enabled() || !self::is_autocreate_enabled()) {
            return false;
        }
        $options = self::get_template_options($event);
        return count($options) > 0;
    }

    /**
     * Build template course options for the form (id => name).
     *
     * Only visible courses are offered, independent of the selecting user's enrolment, so hidden
     * courses are never available as a template.
     *
     * @param stdClass $event
     * @return array<int,string>
     */
    public static function get_template_options(stdClass $event): array {
        global $DB;

        $options = [];
        $institutionid = (int)($event->institutionid ?? 0);
        $semester = (int)($event->semester ?? 0);
        $categoryid = self::resolve_category(self::PURPOSE_TEMPLATE, $institutionid, $semester);

        if ($categoryid > 0) {
            $courses = $DB->get_records('course', ['category' => $categoryid], 'fullname ASC', 'id,fullname,visible');
            foreach ($courses as $course) {
                if ((int)$course->id === SITEID) {
                    continue;
                }
                if ((int)$course->visible !== 1) {
                    continue;
                }
                $options[(int)$course->id] = format_string($course->fullname);
            }
        }

        if (self::include_teacher_courses()) {
            $bookingpersonid = (int)($event->usercreated ?? 0);
            if ($bookingpersonid > 0) {
                foreach (self::get_teacher_course_ids($bookingpersonid) as $courseid) {
                    if (isset($options[$courseid])) {
                        continue;
                    }
                    $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname,visible');
                    if ($course && (int)$course->visible === 1) {
                        $options[$courseid] = format_string($course->fullname);
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Check a confirmed booking and queue its exam course creation (idempotent, fail-soft).
     *
     * The checks run in the current request so a missing template or target category is shown to the
     * confirming user right away. The copy itself runs later in an adhoc task, so the signed-in user
     * is never switched in this request.
     *
     * @param stdClass $event Booking record
     * @param int $confirmeruserid User who confirmed the booking (receives failure messages)
     * @return array{created:bool,courseid:int,skipped:string|null,queued:bool}
     */
    public static function ensure_created_for_event(stdClass $event, int $confirmeruserid = 0): array {
        global $DB;

        $result = ['created' => false, 'courseid' => 0, 'skipped' => null, 'queued' => false];

        try {
            if (!install_helper::is_resources_enabled()) {
                $result['skipped'] = 'resources_disabled';
                return $result;
            }
            if (!self::is_autocreate_enabled()) {
                $result['skipped'] = 'autocreate_disabled';
                return $result;
            }

            $templateid = (int)($event->coursetemplate ?? 0);
            if ($templateid <= 0) {
                $result['skipped'] = 'no_template';
                return $result;
            }

            $existingref = (int)($event->refcourseid ?? 0);
            if ($existingref > 0 && $DB->record_exists('course', ['id' => $existingref])) {
                $result['courseid'] = $existingref;
                $result['skipped'] = 'already_created';
                return $result;
            }

            $targetcategory = self::resolve_category(
                self::PURPOSE_TARGET,
                (int)($event->institutionid ?? 0),
                (int)($event->semester ?? 0)
            );
            if ($targetcategory <= 0) {
                self::notify_not_created('examcourse_reason_target_unresolved');
                $result['skipped'] = 'target_unresolved';
                return $result;
            }

            if (!$DB->record_exists('course', ['id' => $templateid])) {
                self::notify_not_created('examcourse_reason_template_missing');
                $result['skipped'] = 'template_missing';
                return $result;
            }

            return array_merge($result, self::queue_creation($event, $confirmeruserid));
        } catch (\Throwable $e) {
            debugging('examcourse_manager::ensure_created_for_event failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            self::notify_not_created('examcourse_reason_failed');
            $result['skipped'] = 'exception';
            return $result;
        }
    }

    /**
     * Queue the exam course creation task once per booking.
     *
     * A lock per booking serialises overlapping confirmations. Under the lock the booking is read
     * again, and no task is queued when a course is already linked or a task is already waiting.
     *
     * @param stdClass $event Booking record
     * @param int $confirmeruserid User who confirmed the booking
     * @return array{queued:bool,skipped:string|null,courseid?:int}
     */
    public static function queue_creation(stdClass $event, int $confirmeruserid): array {
        global $DB;

        $eventid = (int)$event->id;
        $factory = \core\lock\lock_config::get_lock_factory('mod_bookit');
        $lock = $factory->get_lock('examcourse-' . $eventid, self::LOCK_TIMEOUT);
        if (!$lock) {
            return ['queued' => false, 'skipped' => 'locked'];
        }

        try {
            $refcourseid = (int)$DB->get_field('bookit_event', 'refcourseid', ['id' => $eventid]);
            if ($refcourseid > 0 && $DB->record_exists('course', ['id' => $refcourseid])) {
                return ['queued' => false, 'skipped' => 'already_created', 'courseid' => $refcourseid];
            }
            if (self::has_queued_task($eventid)) {
                return ['queued' => false, 'skipped' => 'already_queued'];
            }

            $task = new create_exam_course_task();
            $task->set_custom_data(['eventid' => $eventid, 'confirmeruserid' => $confirmeruserid]);
            // Backup and restore check the capabilities of the task user, so the task runs as admin.
            $task->set_userid((int)get_admin()->id);
            \core\task\manager::queue_adhoc_task($task);

            return ['queued' => true, 'skipped' => null];
        } finally {
            $lock->release();
        }
    }

    /**
     * Copy the template into the target category and link the booking (runs in the adhoc task).
     *
     * @param int $eventid
     * @param int $confirmeruserid User who confirmed the booking (receives failure messages)
     * @return array{created:bool,courseid:int,skipped:string|null,incomplete:bool}
     */
    public static function run_creation(int $eventid, int $confirmeruserid): array {
        global $DB;

        $result = ['created' => false, 'courseid' => 0, 'skipped' => null, 'incomplete' => false];

        $event = $DB->get_record('bookit_event', ['id' => $eventid], '*', IGNORE_MISSING);
        if (!$event) {
            $result['skipped'] = 'event_missing';
            return $result;
        }

        $existingref = (int)($event->refcourseid ?? 0);
        if ($existingref > 0 && $DB->record_exists('course', ['id' => $existingref])) {
            $result['courseid'] = $existingref;
            $result['skipped'] = 'already_created';
            return $result;
        }

        $templateid = (int)($event->coursetemplate ?? 0);
        if ($templateid <= 0 || !$DB->record_exists('course', ['id' => $templateid])) {
            self::send_creation_message($event, $confirmeruserid, 'examcourse_reason_template_missing');
            $result['skipped'] = 'template_missing';
            return $result;
        }

        $targetcategory = self::resolve_category(
            self::PURPOSE_TARGET,
            (int)($event->institutionid ?? 0),
            (int)($event->semester ?? 0)
        );
        if ($targetcategory <= 0) {
            self::send_creation_message($event, $confirmeruserid, 'examcourse_reason_target_unresolved');
            $result['skipped'] = 'target_unresolved';
            return $result;
        }

        try {
            $newcourseid = self::duplicate_template_into_category($templateid, $targetcategory, $event);
        } catch (\Throwable $e) {
            debugging('examcourse_manager::run_creation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $newcourseid = 0;
        }
        if ($newcourseid <= 0) {
            self::send_creation_message($event, $confirmeruserid, 'examcourse_reason_failed');
            $result['skipped'] = 'duplicate_failed';
            return $result;
        }

        self::enrol_exam_teachers($newcourseid, $event);
        $DB->update_record('bookit_event', (object)['id' => $eventid, 'refcourseid' => $newcourseid, 'timemodified' => time()]);

        $result['created'] = true;
        $result['courseid'] = $newcourseid;

        // A copy with fewer activities than the template is incomplete (e.g. an activity without backup support).
        if (self::count_course_modules($newcourseid) < self::count_course_modules($templateid)) {
            $result['incomplete'] = true;
            self::send_creation_message($event, $confirmeruserid, null);
        }
        return $result;
    }

    /**
     * Whether a creation task for the booking is already waiting.
     *
     * @param int $eventid
     * @return bool
     */
    protected static function has_queued_task(int $eventid): bool {
        foreach (\core\task\manager::get_adhoc_tasks(create_exam_course_task::class) as $task) {
            $data = (object)$task->get_custom_data();
            if ((int)($data->eventid ?? 0) === $eventid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Show the confirming user that no exam course was created (current request).
     *
     * @param string $reasonkey Language string key of the reason
     * @return void
     */
    protected static function notify_not_created(string $reasonkey): void {
        \core\notification::error(
            get_string('examcourse_not_created', 'mod_bookit', get_string($reasonkey, 'mod_bookit'))
        );
    }

    /**
     * Message the confirming user about a failed or incomplete exam course copy.
     *
     * @param stdClass $event Booking record
     * @param int $userid Recipient (confirming user)
     * @param string|null $reasonkey Language string key of the failure reason, null for an incomplete copy
     * @return void
     */
    protected static function send_creation_message(stdClass $event, int $userid, ?string $reasonkey): void {
        $user = $userid > 0 ? \core_user::get_user($userid, '*', IGNORE_MISSING) : null;
        if (!$user || !empty($user->deleted)) {
            return;
        }

        $strings = get_string_manager();
        $lang = $user->lang ?: null;
        $eventname = format_string((string)$event->name);
        if ($reasonkey === null) {
            $subject = $eventname . ': ' . $strings->get_string('examcourse_copy_incomplete', 'mod_bookit', null, $lang);
            $body = $strings->get_string('examcourse_copy_incomplete', 'mod_bookit', null, $lang);
        } else {
            $subject = $strings->get_string('examcourse_failed_subject', 'mod_bookit', $eventname, $lang);
            $body = $strings->get_string('examcourse_failed_body', 'mod_bookit', (object)[
                'eventname' => $eventname,
                'reason' => $strings->get_string($reasonkey, 'mod_bookit', null, $lang),
            ], $lang);
        }

        $message = new \core\message\message();
        $message->component = 'mod_bookit';
        $message->name = 'bookit_examcourse_failed';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = s($body);
        $message->smallmessage = $subject;
        $message->notification = 1;
        message_send($message);
    }

    /**
     * Number of course modules of a course that are not being deleted.
     *
     * @param int $courseid
     * @return int
     */
    protected static function count_course_modules(int $courseid): int {
        global $DB;
        return $DB->count_records('course_modules', ['course' => $courseid, 'deletioninprogress' => 0]);
    }

    /**
     * Duplicate template course into target category (hidden, startdate = today).
     *
     * Runs as the task user; the signed-in web user is never switched.
     *
     * @param int $templateid
     * @param int $categoryid
     * @param stdClass $event
     * @return int New course id or 0
     */
    protected static function duplicate_template_into_category(int $templateid, int $categoryid, stdClass $event): int {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $template = $DB->get_record('course', ['id' => $templateid], '*', MUST_EXIST);
        $fullname = format_string($template->fullname);
        if (!empty($event->name)) {
            $fullname = format_string((string)$event->name);
        }
        $shortname = self::unique_shortname('exam' . (int)$event->id);

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $templateid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $USER->id
        );
        $settings = [
            'activities' => 1,
            'blocks' => 1,
            'filters' => 1,
            'users' => 0,
            'role_assignments' => 0,
            'comments' => 0,
            'userscompletion' => 0,
            'logs' => 0,
            'grade_histories' => 0,
        ];
        foreach ($settings as $name => $value) {
            if ($setting = $bc->get_plan()->get_setting($name)) {
                $setting->set_value($value);
            }
        }
        $backupid = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $bc->destroy();

        if (!file_exists($backupbasepath . '/moodle_backup.xml')) {
            $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $backupbasepath);
        }

        $newcourseid = restore_dbops::create_new_course($fullname, $shortname, $categoryid);
        $rc = null;
        try {
            $rc = new restore_controller(
                $backupid,
                $newcourseid,
                backup::INTERACTIVE_NO,
                backup::MODE_SAMESITE,
                $USER->id,
                backup::TARGET_NEW_COURSE
            );
            foreach ($settings as $name => $value) {
                $setting = $rc->get_plan()->get_setting($name);
                if ($setting && $setting->get_status() == backup_setting::NOT_LOCKED) {
                    $setting->set_value($value);
                }
            }
            if (!$rc->execute_precheck()) {
                $precheck = $rc->get_precheck_results();
                if (is_array($precheck) && !empty($precheck['errors'])) {
                    $rc->destroy();
                    if (empty($CFG->keeptempdirectoriesonbackup)) {
                        fulldelete($backupbasepath);
                    }
                    $file->delete();
                    // Remove the empty shell course so a later attempt does not leave a second one behind.
                    delete_course($newcourseid, false);
                    return 0;
                }
            }
            $rc->execute_plan();
            $rc->destroy();

            $course = $DB->get_record('course', ['id' => $newcourseid], '*', MUST_EXIST);
            $course->fullname = $fullname;
            $course->shortname = $shortname;
            $course->visible = 0;
            $course->startdate = self::start_of_today();
            $DB->update_record('course', $course);

            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }
            $file->delete();
        } catch (\Throwable $e) {
            // A failed restore must not leave the new course or the backup behind, else every retry adds
            // another empty course to the target category.
            self::discard_failed_copy($newcourseid, $rc, $backupbasepath, $file);
            throw $e;
        }

        return (int)$newcourseid;
    }

    /**
     * Remove what a failed template copy created: the new course, the restore controller and the backup.
     *
     * Every step runs on its own, so one failing clean-up step does not stop the others. Clean-up
     * problems are only reported for debugging; the original failure stays the reported cause.
     *
     * @param int $newcourseid Course created for the copy
     * @param restore_controller|null $rc Restore controller, if it was created
     * @param string $backupbasepath Temporary backup directory
     * @param \stored_file $file Backup file
     * @return void
     */
    protected static function discard_failed_copy(
        int $newcourseid,
        ?restore_controller $rc,
        string $backupbasepath,
        \stored_file $file
    ): void {
        global $CFG;

        $steps = [
            'restore controller' => static fn() => $rc?->destroy(),
            'temporary directory' => static function () use ($CFG, $backupbasepath): void {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($backupbasepath);
                }
            },
            'backup file' => static fn() => $file->delete(),
            'course' => static fn() => delete_course($newcourseid, false),
        ];
        foreach ($steps as $name => $step) {
            try {
                $step();
            } catch (\Throwable $cleanup) {
                debugging("examcourse_manager: removing the {$name} failed: " . $cleanup->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Enrol person in charge and other examiners as editingteacher.
     *
     * @param int $courseid
     * @param stdClass $event
     * @return void
     */
    protected static function enrol_exam_teachers(int $courseid, stdClass $event): void {
        global $DB;

        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', IGNORE_MISSING);
        if (!$role) {
            return;
        }
        $roleid = (int)$role->id;

        $userids = [];
        $pic = (int)($event->personinchargeid ?? 0);
        if ($pic > 0) {
            $userids[$pic] = $pic;
        }
        $raw = trim((string)($event->otherexaminers ?? ''));
        if ($raw !== '') {
            foreach (preg_split('/\s*,\s*/', $raw) as $part) {
                $uid = (int)$part;
                if ($uid > 0) {
                    $userids[$uid] = $uid;
                }
            }
        }

        $enrol = enrol_get_plugin('manual');
        if (!$enrol) {
            return;
        }
        $instances = enrol_get_instances($courseid, true);
        $manual = null;
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manual = $instance;
                break;
            }
        }
        if (!$manual) {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $enrol->add_default_instance($course);
            $instances = enrol_get_instances($courseid, true);
            foreach ($instances as $instance) {
                if ($instance->enrol === 'manual') {
                    $manual = $instance;
                    break;
                }
            }
        }
        if (!$manual) {
            return;
        }

        foreach ($userids as $userid) {
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id,suspended');
            if (!$user || (int)$user->suspended === 1) {
                continue;
            }
            $enrol->enrol_user($manual, $userid, $roleid);
        }
    }

    /**
     * Start of current day in server timezone.
     *
     * @return int
     */
    protected static function start_of_today(): int {
        $tz = \core_date::get_server_timezone_object();
        $dt = new \DateTime('today', $tz);
        return $dt->getTimestamp();
    }

    /**
     * Build a unique course shortname.
     *
     * @param string $base
     * @return string
     * @throws dml_exception
     */
    protected static function unique_shortname(string $base): string {
        global $DB;
        $base = clean_param(substr($base, 0, 80), PARAM_TEXT);
        if ($base === '') {
            $base = 'exam';
        }
        $candidate = $base;
        $i = 0;
        while ($DB->record_exists('course', ['shortname' => $candidate])) {
            $i++;
            $candidate = substr($base, 0, 70) . '-' . $i;
        }
        return $candidate;
    }

    /**
     * Course ids where the user is teacher or editingteacher.
     *
     * @param int $userid
     * @return int[]
     */
    protected static function get_teacher_course_ids(int $userid): array {
        global $DB;

        $roles = $DB->get_records_list('role', 'shortname', ['editingteacher', 'teacher'], '', 'id');
        if (empty($roles)) {
            return [];
        }
        $roleids = array_map('intval', array_keys($roles));
        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
        $sql = "SELECT DISTINCT ctx.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
                 WHERE ra.userid = :userid AND ra.roleid $rolesql";
        $params = array_merge($roleparams, [
            'userid' => $userid,
            'ctxlevel' => CONTEXT_COURSE,
        ]);
        $ids = $DB->get_fieldset_sql($sql, $params);
        return array_map('intval', $ids);
    }
}
