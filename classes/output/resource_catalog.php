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
use mod_bookit\local\formelement\roomfilter;
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

        // Render room filter using custom form element.
        $data->roomfilter = $this->render_room_filter($output);

        return $data;
    }

    /**
     * Render room filter using roomfilter form element
     *
     * @param renderer_base $output
     * @return string HTML for room filter
     */
    private function render_room_filter(renderer_base $output): string {
        // Create roomfilter element in filter mode.
        $roomfilter = new roomfilter(
            'roomfilter',
            get_string('filters:room_label', 'mod_bookit'),
            [], // Auto-loads rooms from database.
            ['mode' => 'filter', 'id' => 'id_roomfilter']
        );

        // Export and render template.
        $context = $roomfilter->export_for_template($output);
        return $context->html ?? '';
    }
}
