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
 * Resource checklist catalog output class.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\output;

use mod_bookit\local\manager\resource_checklist_manager;
use mod_bookit\local\manager\resource_manager;
use renderer_base;
use renderable;
use templatable;
use stdClass;

/**
 * Resource checklist catalog output class.
 *
 * Prepares data for the resource checklist template.
 */
class resource_checklist_catalog implements renderable, templatable {
    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->contextid = \context_system::instance()->id;
        $data->categories = [];

        // Get all categories.
        $categories = resource_manager::get_all_categories();

        // Get all checklist items with resource data joined.
        $checklistitems = resource_checklist_manager::get_all_checklist_items();

        // Group checklist items by category.
        $itemsbycategory = [];
        foreach ($checklistitems as $item) {
            if (!isset($itemsbycategory[$item->categoryid])) {
                $itemsbycategory[$item->categoryid] = [];
            }
            $itemsbycategory[$item->categoryid][] = $item;
        }

        // Build category structure with items.
        foreach ($categories as $category) {
            $categorydata = new stdClass();
            $categorydata->id = $category->get_id();
            $categorydata->name = format_string($category->get_name());
            $categorydata->description = format_text($category->get_description() ?? '', FORMAT_HTML);
            $categorydata->sortorder = $category->get_sortorder();
            $categorydata->items = [];

            // Add checklist items for this category.
            if (isset($itemsbycategory[$category->get_id()])) {
                foreach ($itemsbycategory[$category->get_id()] as $item) {
                    $itemdata = new stdClass();
                    $itemdata->id = $item->id;
                    $itemdata->resourceid = $item->resourceid;
                    $itemdata->name = format_string($item->name);
                    $itemdata->description = format_text($item->description ?? '', FORMAT_HTML);
                    $itemdata->categoryid = $item->categoryid;
                    $itemdata->amount = $item->amount;
                    $itemdata->amountirrelevant = (bool)$item->amountirrelevant;
                    $itemdata->sortorder = $item->sortorder;
                    $itemdata->active = (bool)$item->active;
                    $itemdata->duedate = $item->duedate ?? null;
                    $itemdata->duedatetype = $item->duedatetype ?? null;

                    $categorydata->items[] = $itemdata;
                }
            }

            $data->categories[] = $categorydata;
        }

        return $data;
    }
}
