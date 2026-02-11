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
 * Resource catalog output class
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\output;

use mod_bookit\local\manager\resource_manager;
use mod_bookit\local\persistent\room;
use renderer_base;
use renderable;
use templatable;
use stdClass;

/**
 * Resource catalog output class
 *
 * Prepares data for the resource catalog template
 */
class resource_catalog implements renderable, templatable {
    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->contextid = \context_system::instance()->id;
        $data->categories = [];

        $categories = resource_manager::get_all_categories();

        foreach ($categories as $category) {
            $categorycard = new resource_category_card($category);
            $data->categories[] = $categorycard->export_for_template($output);
        }

        // Get rooms and separate into selected/unselected for two-row layout.
        $roomsdata = $this->get_rooms_for_filter();
        $data->rooms_selected = []; // All start as unselected.
        $data->rooms_unselected = $roomsdata;

        return $data;
    }

    /**
     * Get all active rooms for the filter
     *
     * @return array Array of room objects with id, name, and colors
     */
    private function get_rooms_for_filter(): array {
        $rooms = room::get_records(['active' => 1], 'name', 'ASC');
        $roomsdata = [];

        $colors = [
            ['selected' => '#5BC0BE', 'unselected' => '#A8E6CF'],
            ['selected' => '#FF6B6B', 'unselected' => '#FFE5E5'],
            ['selected' => '#4ECDC4', 'unselected' => '#B4F8C8'],
            ['selected' => '#FF8B94', 'unselected' => '#FFD4A3'],
        ];

        $index = 0;
        foreach ($rooms as $room) {
            $colorset = $colors[$index % count($colors)];
            $roomsdata[] = [
                'id' => $room->get('id'),
                'name' => $room->get('name'),
                'colorselected' => $colorset['selected'],
                'colorunselected' => $colorset['unselected'],
            ];
            $index++;
        }

        return $roomsdata;
    }
}
