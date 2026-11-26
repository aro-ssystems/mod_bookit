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
 * External API for toggling a room active flag.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_bookit\local\persistent\room;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API for toggling room active state from the rooms admin table.
 *
 * @package mod_bookit
 * @copyright 2026 ssystems GmbH <oss@ssystems.de>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_room_active extends external_api {
    /**
     * Describe input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roomid' => new external_value(PARAM_INT, 'Room id'),
            'active' => new external_value(PARAM_BOOL, 'Target active state'),
        ]);
    }

    /**
     * Toggle the active flag of a room.
     *
     * @param int $roomid
     * @param bool $active
     * @return array
     */
    public static function execute(int $roomid, bool $active): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'roomid' => $roomid,
            'active' => $active,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('mod/bookit:managebasics', $context);

        $room = new room($params['roomid']);
        $room->set('active', (int) $params['active']);
        $room->update();

        return [
            'roomid' => (int) $room->get('id'),
            'active' => $room->get('active') ? 1 : 0,
        ];
    }

    /**
     * Describe return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'roomid' => new external_value(PARAM_INT, 'Room id'),
            'active' => new external_value(PARAM_INT, 'Persisted active flag 0/1'),
        ]);
    }
}
