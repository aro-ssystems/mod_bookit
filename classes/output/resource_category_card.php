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
 * Resource category card output class
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\output;

use mod_bookit\local\entity\bookit_resource_categories;
use mod_bookit\local\manager\resource_manager;
use renderer_base;
use renderable;
use templatable;
use stdClass;

/**
 * Resource category card output class
 *
 * Prepares data for a single resource category card
 */
class resource_category_card implements renderable, templatable {
    /** @var bookit_resource_categories */
    private $category;

    /**
     * Constructor
     *
     * @param bookit_resource_categories $category
     */
    public function __construct(bookit_resource_categories $category) {
        $this->category = $category;
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->id = $this->category->get_id();
        $data->name = format_string($this->category->get_name());
        $data->description = format_text($this->category->get_description() ?? '');
        $data->description_raw = $this->category->get_description() ?? '';
        $data->sortorder = $this->category->get_sortorder();
        $data->active = $this->category->is_active();
        $data->resources = [];

        $resources = resource_manager::get_all_resources($this->category->get_id());

        foreach ($resources as $resource) {
            $itemcard = new resource_item_card($resource);
            $data->resources[] = $itemcard->export_for_template($output);
        }

        return $data;
    }
}
