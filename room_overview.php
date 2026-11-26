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
 * Booking-facing room overview (equipment + resources).
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookit\local\manager\resource_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('bookit', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$moduleinstance = $DB->get_record('bookit', ['id' => $cm->instance], '*', MUST_EXIST);
$modulecontext = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/bookit:view', $modulecontext);

$PAGE->set_url('/mod/bookit/room_overview.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('room_overview', 'mod_bookit'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->requires->css(new moodle_url('/mod/bookit/styles.css'));

$rooms = resource_manager::get_active_rooms_with_resources();
$filteroptions = [];
$tablerows = [];
foreach ($rooms as $room) {
    $filteroptions[] = [
        'id' => (int) $room['id'],
        'label' => format_string($room['shortname'] . ' — ' . $room['name']),
    ];
    $tablerows[] = [
        'id' => (int) $room['id'],
        'shortname' => format_string($room['shortname']),
        'name' => format_string($room['name']),
        'description' => format_string($room['description']),
        'seats' => (int) $room['seats'],
        'resourceslabel' => format_string($room['resourceslabel']),
    ];
}

$templatecontext = [
    'calendarurl' => (new moodle_url('/mod/bookit/view.php', ['id' => $cm->id]))->out(false),
    'hasrooms' => $tablerows !== [],
    'rooms' => $tablerows,
    'filteroptions' => $filteroptions,
];

$PAGE->requires->js_call_amd('mod_bookit/room_overview_filter', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('room_overview', 'mod_bookit'));
echo $OUTPUT->render_from_template('mod_bookit/view/room_overview', $templatecontext);
echo $OUTPUT->footer();
