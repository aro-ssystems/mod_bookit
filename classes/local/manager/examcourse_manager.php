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
use context_course;
use dml_exception;
use mod_bookit\local\install_helper;
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
     * @param int $userid Selecting user
     * @return bool
     */
    public static function should_show_template_field(stdClass $event, int $userid): bool {
        if (!install_helper::is_resources_enabled() || !self::is_autocreate_enabled()) {
            return false;
        }
        $options = self::get_template_options($event, $userid);
        return count($options) > 0;
    }

    /**
     * Build template course options for the form (id => name).
     *
     * @param stdClass $event
     * @param int $userid Selecting user
     * @return array<int,string>
     */
    public static function get_template_options(stdClass $event, int $userid): array {
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
                if (!self::user_can_see_course((int)$course->id, $userid)) {
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
                    if (!self::user_can_see_course($courseid, $userid)) {
                        continue;
                    }
                    $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname');
                    if ($course) {
                        $options[$courseid] = format_string($course->fullname);
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Ensure an exam course exists for a confirmed event (idempotent, fail-soft).
     *
     * @param stdClass $event
     * @return array{created:bool,courseid:int,skipped:string|null}
     */
    public static function ensure_created_for_event(stdClass $event): array {
        global $DB;

        $result = ['created' => false, 'courseid' => 0, 'skipped' => null];

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
                $result['skipped'] = 'target_unresolved';
                return $result;
            }

            if (!$DB->record_exists('course', ['id' => $templateid])) {
                $result['skipped'] = 'template_missing';
                return $result;
            }

            $newcourseid = self::duplicate_template_into_category($templateid, $targetcategory, $event);
            if ($newcourseid <= 0) {
                $result['skipped'] = 'duplicate_failed';
                return $result;
            }

            self::enrol_exam_teachers($newcourseid, $event);

            $event->refcourseid = $newcourseid;
            $event->timemodified = time();
            $DB->update_record('bookit_event', $event);

            $result['created'] = true;
            $result['courseid'] = $newcourseid;
            return $result;
        } catch (\Throwable $e) {
            debugging('examcourse_manager::ensure_created_for_event failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $result['skipped'] = 'exception';
            return $result;
        }
    }

    /**
     * Duplicate template course into target category (hidden, startdate = today).
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

        $admin = get_admin();
        $previoususer = $USER;
        if ($admin) {
            \core\session\manager::set_user($admin);
        }

        try {
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

            return (int)$newcourseid;
        } finally {
            if ($admin && $previoususer) {
                \core\session\manager::set_user($previoususer);
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

    /**
     * Whether the user can see the course for template selection.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    protected static function user_can_see_course(int $courseid, int $userid): bool {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id,visible', IGNORE_MISSING);
        if (!$course) {
            return false;
        }
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        // Hidden courses still selectable for users who can view them (e.g. teachers/admins).
        return is_enrolled($context, $userid, '', true)
            || has_capability('moodle/course:viewhiddencourses', $context, $userid)
            || has_capability('moodle/course:update', $context, $userid)
            || ((int)$course->visible === 1 && has_capability('moodle/course:view', $context, $userid));
    }
}
