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
 * Checklist manager class.
 *
 * @package     mod_bookit
 * @copyright   2024 Melanie Treitinger, Ruhr-Universität Bochum <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_bookit\local\manager;

use dml_exception;
use mod_bookit\local\entity\bookit_checklist_master;
use mod_bookit\local\entity\bookit_checklist_category;
use mod_bookit\local\entity\bookit_checklist_item;

/**
 * Checklist manager class.
 *
 * @package     mod_bookit
 * @copyright   2025 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist_manager {

    /**
     * Get the default checklist master.
     *
     * @return bookit_checklist_master|null
     * @throws dml_exception
     */
    public static function get_default_master(): ?bookit_checklist_master {
        global $DB;
        $record = $DB->get_record("bookit_checklist_master", ["isdefault" => 1]);
        if (!$record) {
            return null;
        }
        return bookit_checklist_master::from_database($record->id);
    }

    /**
     * Get checklist categories from database.
     *
     * @param array $categories Array of category IDs to fetch.
     * @return array Array of bookit_checklist_category objects.
     * @throws dml_exception
     */
    public static function get_categories_by_ids(array $categories = []): array {
        global $DB;

        list($in_sql, $params) = $DB->get_in_or_equal($categories);
        $sql = "SELECT * FROM {bookit_checklist_category} WHERE id $in_sql";
        $records = $DB->get_records_sql($sql, $params);

        return array_map(fn($record) => bookit_checklist_category::from_record($record), $records);
    }


    public static function get_categories_by_master_id(int $masterid): array {
        global $DB;

        $sql = "SELECT * FROM {bookit_checklist_category} WHERE masterid = :masterid";
        $params = ['masterid' => $masterid];
        $records = $DB->get_records_sql($sql, $params);

        return array_map(fn($record) => bookit_checklist_category::from_record($record), $records);
    }

    /**
     * Get checklist items by category ID.
     *
     * @param int $categoryid ID of the checklist category.
     * @return array Array of bookit_checklist_item objects.
     * @throws dml_exception
     */
    public static function get_items_by_category_id(int $categoryid): array {
        global $DB;

        $sql = "SELECT * FROM {bookit_checklist_item} WHERE categoryid = :categoryid";
        $params = ['categoryid' => $categoryid];
        $records = $DB->get_records_sql($sql, $params);

        error_log('Debugging get_items_by_category_id: ' . json_encode($records));
        return array_map(fn($record) => bookit_checklist_item::from_record($record), $records);
    }

}
