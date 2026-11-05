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
 * Notification manager for resource status changes.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use core_user;
use dml_exception;
use mod_bookit\local\entity\resource\bookit_resource_status;
use mod_bookit\local\install_helper;
use mod_bookit\local\manager\event_manager;
use moodle_url;

/**
 * Sends Moodle messages when a resource status changes.
 */
// phpcs:disable moodle.Commenting.ValidTags.Invalid,moodle.Commenting.DocblockDescription.Missing
/**
 * @SuppressWarnings(PHPMD)
 */
class resource_notification_manager {
// phpcs:enable moodle.Commenting.ValidTags.Invalid,moodle.Commenting.DocblockDescription.Missing
    /**
     * Notify all relevant users when a resource status changes for an event.
     *
     * Recipients honour site plugin settings (master enable + role checkboxes). Booking person
     * resolves from bookit_event.usercreated only.
     *
     * @param int $cmid Course-module ID (used to build the context URL)
     * @param int $eventid Bookit event ID
     * @param int $resourceid Resource ID whose status changed
     * @param bookit_resource_status $status New status value
     * @return int Number of messages sent
     * @throws dml_exception
     */
    public static function notify_status_changed(
        int $cmid,
        int $eventid,
        int $resourceid,
        bookit_resource_status $status
    ): int {
        global $DB;

        if (!self::is_notifications_enabled()) {
            return 0;
        }

        $event = $DB->get_record('bookit_event', ['id' => $eventid]);
        if (!$event) {
            return 0;
        }

        $resource = $DB->get_record('bookit_resource', ['id' => $resourceid]);
        if (!$resource) {
            return 0;
        }

        $recipientids = self::collect_recipient_ids($event);
        if (empty($recipientids)) {
            return 0;
        }

        $statuslabel = event_manager::get_resource_status_label($status->value);
        $contexturl  = new moodle_url(
            '/mod/bookit/view/event_resources.php',
            ['id' => $cmid, 'eventid' => $eventid]
        );

        // Use the sender as a Moodle "noreply" user so recipients see a system message.
        $userfrom = core_user::get_noreply_user();

        $sent = 0;
        foreach ($recipientids as $recipientid) {
            $userto = core_user::get_user($recipientid);
            if (!$userto || !$userto->id || $userto->deleted || $userto->suspended) {
                continue;
            }

            // Build subject and body in the recipient's language.
            $oldlang = force_current_language($userto->lang);

            $a = new \stdClass();
            $a->eventname    = $event->name;
            $a->resourcename = $resource->name;
            $a->statuslabel  = $statuslabel;

            $subject = get_string('resources:notification_status_changed_subject', 'mod_bookit', $a);
            $body    = get_string('resources:notification_status_changed_body', 'mod_bookit', $a);

            force_current_language($oldlang);

            $message = new \core\message\message();
            $message->component        = 'mod_bookit';
            $message->name             = 'bookit_resource_status_changed';
            $message->userfrom         = $userfrom;
            $message->userto           = $userto;
            $message->subject          = $subject;
            $message->fullmessage      = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml  = '';
            $message->smallmessage     = $subject;
            $message->notification     = 1;
            $message->contexturl       = $contexturl->out(false);
            $message->contexturlname   = $event->name;
            $message->courseid         = self::get_course_id($cmid);

            message_send($message);
            $sent++;
        }

        return $sent;
    }

    /**
     * Collect unique recipient user IDs from an event record using plugin config toggles.
     *
     * @param \stdClass $event Database record from bookit_event
     * @return int[] Unique user IDs
     */
    private static function collect_recipient_ids(\stdClass $event): array {
        $ids = [];

        if (
            self::is_config_enabled(install_helper::CONFIG_RESOURCESTATUS_NOTIFY_BOOKINGPERSON)
                && !empty($event->usercreated)
        ) {
            $ids[] = (int)$event->usercreated;
        }
        if (
            self::is_config_enabled(install_helper::CONFIG_RESOURCESTATUS_NOTIFY_PERSONINCHARGE)
                && !empty($event->personinchargeid)
        ) {
            $ids[] = (int)$event->personinchargeid;
        }
        if (
            self::is_config_enabled(
                install_helper::CONFIG_RESOURCESTATUS_NOTIFY_OTHEREXAMINERS,
                false
            )
                && !empty($event->otherexaminers)
        ) {
            foreach (explode(',', $event->otherexaminers) as $raw) {
                $id = (int)trim($raw);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether resource status-change notifications are master-enabled.
     *
     * Missing config is treated as enabled (fresh-install default).
     *
     * @return bool
     */
    private static function is_notifications_enabled(): bool {
        return self::is_config_enabled(install_helper::CONFIG_RESOURCESTATUS_NOTIFICATIONS_ENABLED);
    }

    /**
     * Whether a checkbox config is on (missing config ⇒ default on).
     *
     * @param string $configkey Plugin config key without component prefix
     * @param bool $defaultwhenmissing Value when the config key has never been set
     * @return bool
     */
    private static function is_config_enabled(string $configkey, bool $defaultwhenmissing = true): bool {
        $value = get_config('mod_bookit', $configkey);
        if ($value === false) {
            return $defaultwhenmissing;
        }
        return (int)$value !== 0;
    }

    /**
     * Retrieve the course ID for a given course-module ID.
     *
     * @param int $cmid Course-module ID
     * @return int Course ID, or 0 if not found
     */
    private static function get_course_id(int $cmid): int {
        global $DB;
        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'course', IGNORE_MISSING);
        return $cm ? (int)$cm->course : 0;
    }
}
