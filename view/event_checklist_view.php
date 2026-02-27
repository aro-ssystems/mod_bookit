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
 * Event-specific master checklist view with check-off and progress bar.
 *
 * Service team (managebasics): can check off items and see progress.
 * Bookers and examiners: can check off their own items and see progress.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use mod_bookit\local\manager\checklist_manager;
use mod_bookit\local\manager\event_checklist_state_manager;
use mod_bookit\output\event_master_checklist_catalog;

$eventid = required_param('eventid', PARAM_INT);
$cmid    = required_param('id', PARAM_INT);

$cm     = get_coursemodule_from_id('bookit', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$event  = $DB->get_record('bookit_event', ['id' => $eventid], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/bookit:view', $context);

$PAGE->set_url(new moodle_url('/mod/bookit/view/event_checklist_view.php', ['id' => $cmid, 'eventid' => $eventid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('event_checklist_view_title', 'mod_bookit'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('event_checklist_view_heading', 'mod_bookit', format_string($event->name)));

$output = new event_master_checklist_catalog($eventid, $cmid, $context->id);
echo $OUTPUT->render($output);

$PAGE->requires->js_call_amd(
    'mod_bookit/event_master_checklist/event_master_checklist_container',
    'init',
    ['[data-region="event-master-checklist-container"]']
);

echo $OUTPUT->footer();
