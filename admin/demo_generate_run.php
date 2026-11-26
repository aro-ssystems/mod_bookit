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
 * Demo data generate runner.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookit\local\demo_data_helper;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_admin();
require_sesskey();

$report = demo_data_helper::generate();
$params = [
    'section' => 'modsettingbookit',
    'demogeneratestatus' => $report['status'],
];
if ($report['status'] === 'success') {
    $params['demorooms'] = $report['counts']['rooms'] ?? 0;
    $params['demoresourcecategories'] = $report['counts']['resource_categories'] ?? 0;
    $params['demochecklistcategories'] = $report['counts']['checklist_categories'] ?? 0;
    $params['demoevents'] = $report['counts']['events'] ?? 0;
}

redirect(new moodle_url('/admin/settings.php', $params));
