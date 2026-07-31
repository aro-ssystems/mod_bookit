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
 * Demo data reset runner with confirm step.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookit\local\demo_data_helper;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_admin();

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$returnurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingbookit']);
$pageurl = new moodle_url('/mod/bookit/admin/demo_reset_run.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('demoreset', 'mod_bookit'));
$PAGE->set_heading(get_string('demoreset', 'mod_bookit'));

if (!$confirm) {
    $confirmurl = new moodle_url('/mod/bookit/admin/demo_reset_run.php', [
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('demoreset_confirm', 'mod_bookit'),
        $confirmurl,
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

$report = demo_data_helper::reset();
redirect(new moodle_url('/admin/settings.php', [
    'section' => 'modsettingbookit',
    'demoresetstatus' => $report['status'],
]));
