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
 * Admin-Backend for checklist settings.
 *
 * @package    mod_bookit
 * @copyright  2025 Melanie Treitinger, Ruhr-Universität Bochum <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookit\form\settings_checklist_form;
use mod_bookit\local\install_helper;
use mod_bookit\local\tabs;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/bookit/admin/checklist.php'));
$PAGE->set_primary_active_tab('bookit_settings');
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('checklist', 'mod_bookit'));
$PAGE->set_heading(get_string('settings_overview', 'mod_bookit'));

$returnurl = new moodle_url('/mod/bookit/admin/checklist.php');

require_capability('mod/bookit:managemasterchecklist', $context); // XXX TODO: use other capability.
if (!install_helper::is_checklist_enabled()) {
    $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingbookit']);
    redirect($settingsurl, get_string('optional_part_disabled', 'mod_bookit'), null, \core\output\notification::NOTIFY_WARNING);
}

$fileoptions = [
    'subdirs' => 0,
    'maxfiles' => 1,
    'accepted_types' => ['.png', '.jpg', '.jpeg'],
];

$mform = new settings_checklist_form(null, ['fileoptions' => $fileoptions]);

// Standard form processing if statement.
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $draftid = (int)($data->pdf_logo_custom ?? 0);
    if ($draftid > 0) {
        file_save_draft_area_files(
            $draftid,
            $context->id,
            'mod_bookit',
            'pdf_logo_custom',
            0,
            $fileoptions
        );
    }

    $configkeys = ['pdf_logo_enable', 'pdf_logo_source'];
    foreach ($configkeys as $key) {
        if (property_exists($data, $key)) {
            set_config($key, $data->$key, 'mod_bookit');
        }
    }
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$draftid = file_get_submitted_draft_itemid('pdf_logo_custom');
file_prepare_draft_area($draftid, $context->id, 'mod_bookit', 'pdf_logo_custom', 0, $fileoptions);

$config = get_config('mod_bookit');
$config->pdf_logo_custom = $draftid;
$mform->set_data($config);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('checklist', 'mod_bookit'));

// Show tabs.
$renderer = $PAGE->get_renderer('mod_bookit');
$tabrow = tabs::get_tabrow($context);
$id = optional_param('id', 'settings', PARAM_TEXT);
echo $renderer->tabs($tabrow, $id);

$mform->display();

echo $OUTPUT->footer();
