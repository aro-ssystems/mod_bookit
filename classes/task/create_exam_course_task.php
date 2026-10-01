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
 * Adhoc task that copies the exam course template for a confirmed booking.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\task;

use mod_bookit\local\manager\examcourse_manager;

/**
 * Creates the exam course outside the web request of the confirming user.
 *
 * The web request only queues this task, so the signed-in user is never switched. The task runs
 * as the user set when queueing (the site administrator) because backup and restore check that
 * user's capabilities. Custom data carries the booking and the confirming user, who is notified
 * when the copy fails or is incomplete.
 */
class create_exam_course_task extends \core\task\adhoc_task {
    /**
     * Human readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_create_exam_course', 'mod_bookit');
    }

    /**
     * Run the exam course creation for the booking in the custom data.
     *
     * @return void
     */
    public function execute(): void {
        $data = (object)$this->get_custom_data();
        examcourse_manager::run_creation((int)($data->eventid ?? 0), (int)($data->confirmeruserid ?? 0));
    }
}
