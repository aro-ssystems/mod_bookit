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
 * Event master checklist catalog output class.
 *
 * Renders the per-event master checklist with check-off checkboxes and a
 * reactive progress bar. Used by view/event_checklist_view.php.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\output;

use mod_bookit\local\manager\checklist_manager;
use mod_bookit\local\manager\event_checklist_state_manager;
use renderer_base;
use renderable;
use templatable;
use stdClass;

/**
 * Event master checklist catalog output class.
 */
class event_master_checklist_catalog implements renderable, templatable {
    /** @var int Event ID */
    private int $eventid;

    /** @var int Course module ID */
    private int $cmid;

    /** @var int Context ID */
    private int $contextid;

    /**
     * Constructor.
     *
     * @param int $eventid Event ID
     * @param int $cmid Course module ID
     * @param int $contextid Context ID
     */
    public function __construct(int $eventid, int $cmid, int $contextid) {
        $this->eventid   = $eventid;
        $this->cmid      = $cmid;
        $this->contextid = $contextid;
    }

    /**
     * Export template data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB, $USER;

        $data = new stdClass();
        $data->eventid   = $this->eventid;
        $data->cmid      = $this->cmid;
        $data->contextid = $this->contextid;

        // Load event details for header.
        $event = $DB->get_record('bookit_event', ['id' => $this->eventid]);
        $data->eventname = $event ? format_string($event->name) : '';

        $data->starttime = '';
        if ($event && !empty($event->starttime)) {
            $data->starttime = userdate($event->starttime, get_string('strftimedatetimeshort', 'langconfig'));
        }

        // Default master checklist.
        $master = checklist_manager::get_default_master();
        if (!$master) {
            $data->categories   = [];
            $data->hasitems     = false;
            $data->progresstotal     = 0;
            $data->progressdone      = 0;
            $data->progresspercent   = 0;
            $data->progresscomplete  = false;
            return $data;
        }

        $masterid = $master->id;

        // Load per-user state for this event.
        $statebyitem = event_checklist_state_manager::get_state_for_event($this->eventid, $USER->id);

        $categories = checklist_manager::get_categories_by_master_id($masterid);

        $totalcount = 0;
        $donecount  = 0;
        $categoriesdata = [];

        foreach ($categories as $category) {
            $categoryid   = $category->id;
            $categoryname = format_string($category->name);

            $items = checklist_manager::get_items_by_category_id($categoryid);
            if (empty($items)) {
                continue;
            }

            $itemsdata = [];
            foreach ($items as $item) {
                $itemid = $item->id;
                $done   = $statebyitem[$itemid] ?? false;

                $itemdata = new stdClass();
                $itemdata->id    = $itemid;
                $itemdata->title = format_string($item->title);
                $itemdata->done  = $done;

                $totalcount++;
                if ($done) {
                    $donecount++;
                }

                $itemsdata[] = $itemdata;
            }

            if (!empty($itemsdata)) {
                $catdata = new stdClass();
                $catdata->id    = $categoryid;
                $catdata->name  = $categoryname;
                $catdata->items = $itemsdata;
                $categoriesdata[] = $catdata;
            }
        }

        $data->categories  = $categoriesdata;
        $data->hasitems    = !empty($categoriesdata);

        // Progress bar data.
        $data->progresstotal    = $totalcount;
        $data->progressdone     = $donecount;
        $data->progresspercent  = $totalcount > 0 ? (int)round(($donecount / $totalcount) * 100) : 0;
        $data->progresscomplete = ($totalcount > 0 && $donecount === $totalcount);

        return $data;
    }
}
