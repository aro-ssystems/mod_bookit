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
 * Resource status view for event participants and service team.
 *
 * Service team (managebasics): interactive checklist with status dropdowns.
 * Bookers and examiners: read-only view matching the booking form layout.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use mod_bookit\form\view_event_resources_form;
use mod_bookit\local\manager\resource_manager;

$eventid = required_param('eventid', PARAM_INT);
$cmid    = required_param('id', PARAM_INT);

$cm     = get_coursemodule_from_id('bookit', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$event  = $DB->get_record('bookit_event', ['id' => $eventid], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/bookit:view', $context);

$canmanage = has_capability('mod/bookit:managebasics', $context);

$PAGE->set_url(new moodle_url('/mod/bookit/view/event_resources.php', ['id' => $cmid, 'eventid' => $eventid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading($course->fullname);

$titlestr = $canmanage ? get_string('event_checklist_title', 'mod_bookit') : get_string('event_resources_title', 'mod_bookit');
$PAGE->set_title($titlestr);

echo $OUTPUT->header();

if ($canmanage) {
    echo $OUTPUT->heading(get_string('event_checklist_heading', 'mod_bookit', format_string($event->name)));

    $catalog = new \mod_bookit\output\event_checklist_catalog($eventid, $cmid, $canmanage, $event);
    echo $OUTPUT->render($catalog);

    $PAGE->requires->js_call_amd(
        'mod_bookit/event_checklist/event_checklist_container',
        'init',
        ['#mod-bookit-event-checklist-container']
    );
} else {
    // Bookers and examiners: read-only form matching the booking form layout.
    echo $OUTPUT->heading(get_string('event_resources_heading', 'mod_bookit', format_string($event->name)));

    $bookedresources = [];
    foreach (resource_manager::get_resources_of_event($eventid) as $br) {
        $bookedresources[(int)$br->resourceid] = [
            'amount' => (int)$br->amount,
            'status' => (string)($br->status ?? 'requested'),
        ];
    }

    if (empty($bookedresources)) {
        echo $OUTPUT->notification(get_string('event_checklist_no_resources', 'mod_bookit'), 'info');
    } else {
        $resourcesdata = resource_manager::get_active_resources_grouped();
        $form = new view_event_resources_form(null, [
            'bookedresources' => $bookedresources,
            'resourcesdata'   => $resourcesdata,
        ]);
        $form->display();
    }
}

echo html_writer::start_tag('div', ['class' => 'mt-3']);
$backurl = new moodle_url('/mod/bookit/overview.php', ['id' => $cmid]);
echo html_writer::link($backurl, get_string('back_to_overview', 'mod_bookit'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
