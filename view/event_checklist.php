<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event-specific resource checklist view.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookit\local\manager\event_resource_manager;
use mod_bookit\local\manager\resource_checklist_manager;

require_once(__DIR__ . '/../../../config.php');

$eventid = required_param('eventid', PARAM_INT);
$cmid = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('bookit', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$bookit = $DB->get_record('bookit', ['id' => $cm->instance], '*', MUST_EXIST);
$event = $DB->get_record('bookit_event', ['id' => $eventid], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$canview = has_capability('mod/bookit:view', $context);
if (!$canview) {
    require_capability('mod/bookit:view', $context);
}

$PAGE->set_url(new moodle_url('/mod/bookit/view/event_checklist.php', ['id' => $cmid, 'eventid' => $eventid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('event_checklist_title', 'mod_bookit'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('event_checklist_heading', 'mod_bookit', format_string($event->name)));

$eventresources = event_resource_manager::get_resources_for_event($eventid);

if (empty($eventresources)) {
    echo $OUTPUT->notification(get_string('event_checklist_no_resources', 'mod_bookit'), \core\output\notification::NOTIFY_INFO);
} else {
    echo html_writer::start_tag('div', ['class' => 'event-checklist-container']);

    echo html_writer::start_tag('div', ['class' => 'event-details mb-4']);
    echo html_writer::tag('h4', get_string('event_details', 'mod_bookit'));
    $eventnamefield = html_writer::tag('strong', get_string('event_name', 'mod_bookit') . ': ');
    echo html_writer::tag('p', $eventnamefield . format_string($event->name));
    if (!empty($event->starttime)) {
        $starttimefield = html_writer::tag('strong', get_string('starttime', 'mod_bookit') . ': ');
        echo html_writer::tag('p', $starttimefield . userdate($event->starttime));
    }
    if (!empty($event->endtime)) {
        $endtimefield = html_writer::tag('strong', get_string('endtime', 'mod_bookit') . ': ');
        echo html_writer::tag('p', $endtimefield . userdate($event->endtime));
    }
    echo html_writer::end_tag('div');

    echo html_writer::tag('h4', get_string('resources_checklist', 'mod_bookit'));

    $table = new html_table();
    $table->head = [
        get_string('resource', 'mod_bookit'),
        get_string('quantity', 'mod_bookit'),
        get_string('duedate', 'mod_bookit'),
        get_string('status', 'core'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($eventresources as $eventresource) {
        $resourceid = $eventresource->get_resourceid();
        $quantity = $eventresource->get_quantity();

        $resource = $DB->get_record('bookit_resource', ['id' => $resourceid]);
        if (!$resource) {
            continue;
        }

        $checklistitem = resource_checklist_manager::get_checklist_item_by_resource($resourceid);

        // Status badge.
        if ($checklistitem && $checklistitem->is_active()) {
            $statusbadge = html_writer::tag('span', get_string('active', 'core'), [
                'class' => 'badge badge-success',
            ]);
        } else {
            $statusbadge = html_writer::tag('span', get_string('inactive', 'core'), [
                'class' => 'badge badge-secondary',
            ]);
        }

        $row = new html_table_row();
        $row->cells = [
            format_string($resource->name),
            $quantity,
            $checklistitem && $checklistitem->get_duedate() ? userdate($checklistitem->get_duedate()) : '-',
            $statusbadge,
        ];

        $table->data[] = $row;
    }

    echo html_writer::table($table);
    echo html_writer::end_tag('div');
}

echo html_writer::start_tag('div', ['class' => 'mt-3']);
$backlink = new moodle_url('/mod/bookit/view.php', ['id' => $cmid]);
echo html_writer::link($backlink, get_string('back_to_event', 'mod_bookit'), [
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
