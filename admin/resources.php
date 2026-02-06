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
 * Admin page for managing resources and resource categories.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_bookit\local\tabs;

require_login();
$context = context_system::instance();
require_capability('mod/bookit:managebasics', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/bookit/admin/resources.php', ['id' => 'resources']));
$PAGE->set_title(get_string('resources:overview', 'mod_bookit'));
$PAGE->set_heading(get_string('resources:overview', 'mod_bookit'));

echo $OUTPUT->header();

// Render tab navigation.
$tabrow = tabs::get_tabrow($context);
$id = optional_param('id', 'resources', PARAM_TEXT);
print_tabs([$tabrow], $id);

// Render via Output Class - get data.
$catalog = new \mod_bookit\output\resource_catalog();
$catalogdata = $catalog->export_for_template($OUTPUT);

// Render template (only container with spinner) FIRST.
echo $OUTPUT->render_from_template('mod_bookit/resource_catalog_container', [
    'contextid' => $catalogdata->contextid,
]);

// Init Reactive JS with data AFTER DOM is rendered.
$PAGE->requires->js_call_amd(
    'mod_bookit/resource_catalog',
    'init',
    [
        $catalogdata->contextid,
        json_encode($catalogdata->categories), // JSON String for AMD.
    ]
);

echo $OUTPUT->footer();
