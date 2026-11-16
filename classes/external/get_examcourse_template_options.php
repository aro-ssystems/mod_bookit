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
 * External API that lists the exam course templates for a faculty and term.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\local\manager\examcourse_manager;

/**
 * Lists the exam course templates the booking form offers after the faculty or term changed.
 *
 * Read only. The list follows the same rule as the booking form and the check on save.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_examcourse_template_options extends external_api {
    /**
     * Parameters: activity, booking (0 for a new one), chosen faculty and term.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the booking activity'),
            'eventid' => new external_value(PARAM_INT, 'Booking id, 0 for a new booking', VALUE_DEFAULT, 0),
            'institutionid' => new external_value(PARAM_INT, 'Faculty chosen in the form, 0 for none'),
            'semester' => new external_value(PARAM_INT, 'Term chosen in the form, 0 for none'),
        ]);
    }

    /**
     * Return the templates offered for the chosen faculty and term.
     *
     * @param int $cmid
     * @param int $eventid
     * @param int $institutionid
     * @param int $semester
     * @return array{visible:bool,options:array<int,array{id:int,name:string}>}
     */
    public static function execute(int $cmid, int $eventid, int $institutionid, int $semester): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'eventid' => $eventid,
            'institutionid' => $institutionid,
            'semester' => $semester,
        ]);

        $context = context_module::instance($params['cmid']);
        self::validate_context($context);

        // Same rights as the editable template field in the booking form.
        require_capability('mod/bookit:selectexamcoursetemplate', $context);
        $event = null;
        if ($params['eventid'] > 0) {
            $event = $DB->get_record('bookit_event', ['id' => $params['eventid']]);
            if (!$event) {
                throw new invalid_parameter_exception('Unknown booking');
            }
        }
        event_access_manager::require_booking_fields_editable($event, $context, (int)$USER->id);

        if (!examcourse_manager::is_template_field_enabled()) {
            return ['visible' => false, 'options' => []];
        }

        $basis = examcourse_manager::get_template_basis(
            $event,
            (int)$USER->id,
            $params['institutionid'],
            $params['semester']
        );
        $options = [];
        foreach (examcourse_manager::get_template_options($basis) as $id => $name) {
            $options[] = ['id' => (int)$id, 'name' => $name];
        }

        return ['visible' => !empty($options), 'options' => $options];
    }

    /**
     * Whether the field is shown and the templates in display order.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'visible' => new external_value(PARAM_BOOL, 'Whether the template field is shown'),
            'options' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Template course id'),
                    'name' => new external_value(PARAM_TEXT, 'Template course name'),
                ])
            ),
        ]);
    }
}
