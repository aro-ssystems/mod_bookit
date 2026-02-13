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
 * Admin page for managing resource checklist.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookit\local\tabs;
use mod_bookit\local\manager\resource_checklist_manager;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();

require_login();
require_capability('mod/bookit:managebasics', $context);

// Auto-generate checklist entries if they don't exist.
$autogenerate = optional_param('autogenerate', false, PARAM_BOOL);
if ($autogenerate && confirm_sesskey()) {
    $count = resource_checklist_manager::auto_generate_checklist($USER->id);
    if ($count > 0) {
        redirect(
            new moodle_url('/mod/bookit/admin/resource_checklist.php'),
            get_string('resources:checklist_generated', 'mod_bookit', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/bookit/admin/resource_checklist.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('resources:checklist', 'mod_bookit'));
$PAGE->set_heading(get_string('resources:checklist', 'mod_bookit'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('resources:checklist', 'mod_bookit'));

// Show tabs.
$renderer = $PAGE->get_renderer('mod_bookit');
$tabrow = tabs::get_tabrow($context);
$id = 'resource_checklist';
echo $renderer->tabs($tabrow, $id);

// Check if checklist is empty and offer to auto-generate.
$items = resource_checklist_manager::get_all_checklist_items();
if (empty($items)) {
    echo $OUTPUT->notification(
        get_string('resources:checklist_empty', 'mod_bookit'),
        \core\output\notification::NOTIFY_INFO
    );
    $generateurl = new moodle_url('/mod/bookit/admin/resource_checklist.php', [
        'autogenerate' => 1,
        'sesskey' => sesskey(),
    ]);
    echo html_writer::link(
        $generateurl,
        get_string('resources:generate_checklist', 'mod_bookit'),
        ['class' => 'btn btn-primary mb-3']
    );
} else {
    // Render via Output Class.
    $catalog = new \mod_bookit\output\resource_checklist_catalog();
    echo $OUTPUT->render($catalog);

    // Init Reactive JS.
    $PAGE->requires->js_call_amd(
        'mod_bookit/resource_checklist/resource_checklist_container',
        'init',
        ['#mod-bookit-resource-checklist-container']
    );
}

echo $OUTPUT->footer();
