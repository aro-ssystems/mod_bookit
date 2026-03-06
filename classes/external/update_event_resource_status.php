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
 * External API for updating event resource status.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_bookit\local\entity\resource\bookit_event_resource;
use mod_bookit\local\manager\event_resource_manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");

/**
 * External API for updating event resource status.
 */
class update_event_resource_status extends external_api {
    /**
     * Description of parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'eventid'    => new external_value(PARAM_INT, 'Event ID'),
            'resourceid' => new external_value(PARAM_INT, 'Resource ID'),
            'status'     => new external_value(PARAM_ALPHA, 'New status value'),
        ]);
    }

    /**
     * Update event resource status.
     *
     * @param int $cmid Course module ID
     * @param int $eventid Event ID
     * @param int $resourceid Resource ID
     * @param string $status New status
     * @return array
     */
    public static function execute(int $cmid, int $eventid, int $resourceid, string $status): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'       => $cmid,
            'eventid'    => $eventid,
            'resourceid' => $resourceid,
            'status'     => $status,
        ]);

        $cm = get_coursemodule_from_id('bookit', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/bookit:managebasics', $context);

        $validstatuses = [
            bookit_event_resource::STATUS_REQUESTED,
            bookit_event_resource::STATUS_CONFIRMED,
            bookit_event_resource::STATUS_INPROGRESS,
            bookit_event_resource::STATUS_REJECTED,
        ];
        if (!in_array($params['status'], $validstatuses)) {
            throw new \invalid_parameter_exception('Invalid status value: ' . $params['status']);
        }

        event_resource_manager::update_status(
            $params['eventid'],
            $params['resourceid'],
            $params['status']
        );

        return ['status' => $params['status']];
    }

    /**
     * Description of return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Updated status value'),
        ]);
    }
}
