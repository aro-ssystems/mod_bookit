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
 * Institution/semester overrides for exam-course categories.
 *
 * @package    mod_bookit
 * @copyright  2026 ssystems GmbH <oss@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookit\form\examcourse_mapping_form;
use mod_bookit\local\manager\examcourse_manager;
use mod_bookit\local\persistent\institution;
use mod_bookit\local\tabs;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();

require_login();
require_capability('mod/bookit:managebasics', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/bookit/admin/examcourse_mapping.php'));
$PAGE->set_primary_active_tab('bookit_settings');
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('examcourse_mapping', 'mod_bookit'));
$PAGE->set_heading(get_string('settings_overview', 'mod_bookit'));

$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
if ($deleteid && $confirm && confirm_sesskey()) {
    examcourse_manager::delete_mapping($deleteid);
    redirect(
        new moodle_url('/mod/bookit/admin/examcourse_mapping.php', ['id' => 'examcourse_mapping']),
        get_string('examcourse_mapping_deleted', 'mod_bookit'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$mform = new examcourse_mapping_form();
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/bookit/admin/examcourse_mapping.php', ['id' => 'examcourse_mapping']));
} else if ($data = $mform->get_data()) {
    examcourse_manager::upsert_mapping(
        (string)$data->purpose,
        (int)$data->institutionid,
        (int)($data->semester ?? 0),
        (int)$data->categoryid
    );
    redirect(
        new moodle_url('/mod/bookit/admin/examcourse_mapping.php', ['id' => 'examcourse_mapping']),
        get_string('examcourse_mapping_saved', 'mod_bookit'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('examcourse_mapping', 'mod_bookit'));

$renderer = $PAGE->get_renderer('mod_bookit');
$tabrow = tabs::get_tabrow($context);
$id = optional_param('id', 'examcourse_mapping', PARAM_TEXT);
echo $renderer->tabs($tabrow, $id);

echo html_writer::div(get_string('examcourse_viewexamcourse_admin_hint', 'mod_bookit'), 'alert alert-info mb-3');

$mform->display();

$rows = examcourse_manager::list_mappings();
$table = new html_table();
$table->head = [
    get_string('examcourse_map_purpose', 'mod_bookit'),
    get_string('institutions', 'mod_bookit'),
    get_string('select_semester', 'mod_bookit'),
    get_string('examcourse_map_category', 'mod_bookit'),
    get_string('actions'),
];
$institutions = institution::get_records();
$instnames = [];
foreach ($institutions as $inst) {
    $instnames[(int)$inst->get('id')] = format_string($inst->get('name'));
}
foreach ($rows as $row) {
    $purpose = (string)$row->purpose;
    $purposelabel = $purpose === examcourse_manager::PURPOSE_TEMPLATE
        ? get_string('examcourse_purpose_template', 'mod_bookit')
        : get_string('examcourse_purpose_target', 'mod_bookit');
    $semesterlabel = ((int)$row->semester === 0)
        ? get_string('examcourse_semester_institution_wide', 'mod_bookit')
        : (string)$row->semester;
    $cat = core_course_category::get((int)$row->categoryid, IGNORE_MISSING);
    $catname = $cat ? $cat->get_formatted_name() : (string)$row->categoryid;
    $deleteurl = new moodle_url('/mod/bookit/admin/examcourse_mapping.php', [
        'delete' => $row->id,
        'confirm' => 1,
        'sesskey' => sesskey(),
        'id' => 'examcourse_mapping',
    ]);
    $table->data[] = [
        $purposelabel,
        $instnames[(int)$row->institutionid] ?? (string)$row->institutionid,
        $semesterlabel,
        $catname,
        html_writer::link($deleteurl, get_string('delete'), ['class' => 'text-danger']),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
