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
 * Event checklist catalog output class.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\output;

use mod_bookit\local\manager\event_resource_manager;
use mod_bookit\local\manager\resource_checklist_manager;
use renderer_base;
use renderable;
use templatable;
use stdClass;

/**
 * Event checklist catalog output class.
 *
 * Prepares data for the event_checklist_catalog template.
 */
class event_checklist_catalog implements renderable, templatable {
    /** @var int Event ID */
    private int $eventid;

    /** @var int Course module ID */
    private int $cmid;

    /** @var bool Whether current user can manage */
    private bool $canmanage;

    /** @var stdClass Event record */
    private stdClass $event;

    /**
     * Constructor.
     *
     * @param int $eventid Event ID
     * @param int $cmid Course module ID
     * @param bool $canmanage Whether current user can manage checklist
     * @param stdClass $event Event database record
     */
    public function __construct(int $eventid, int $cmid, bool $canmanage, stdClass $event) {
        $this->eventid = $eventid;
        $this->cmid = $cmid;
        $this->canmanage = $canmanage;
        $this->event = $event;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;

        $data = new stdClass();
        $data->eventid = $this->eventid;
        $data->cmid = $this->cmid;
        $data->canmanage = (int)$this->canmanage;
        $data->contextid = \context_system::instance()->id;
        $data->eventname = format_string($this->event->name);
        $data->starttime = !empty($this->event->starttime) ? userdate($this->event->starttime) : '';
        $data->endtime = !empty($this->event->endtime) ? userdate($this->event->endtime) : '';

        $eventresources = event_resource_manager::get_resources_for_event($this->eventid);

        // Group items by category.
        $categoriesmap = [];

        foreach ($eventresources as $er) {
            $resource = $DB->get_record('bookit_resource', ['id' => $er->get_resourceid()]);
            if (!$resource) {
                continue;
            }

            $categoryid = (int)($resource->categoryid ?? 0);
            if ($categoryid > 0) {
                $category = $DB->get_record('bookit_resource_categories', ['id' => $categoryid]);
                $categoryname = $category ? format_string($category->name) : '';
            } else {
                $categoryname = '';
            }

            if (!isset($categoriesmap[$categoryid])) {
                $categoriesmap[$categoryid] = [
                    'id'    => $categoryid,
                    'name'  => $categoryname,
                    'items' => [],
                ];
            }

            $checklistitem = resource_checklist_manager::get_checklist_item_by_resource($er->get_resourceid());
            $duedate = '';
            if ($checklistitem && $checklistitem->get_duedate()) {
                $duedate = userdate($checklistitem->get_duedate());
            }

            $status = $er->get_status();

            $itemdata = new stdClass();
            $itemdata->id         = $er->get_id();
            $itemdata->resourceid = $er->get_resourceid();
            $itemdata->resourcename = format_string($resource->name);
            $itemdata->categoryid   = $categoryid;
            $itemdata->amount       = $er->get_amount();
            $itemdata->status       = $status;
            $itemdata->duedate      = $duedate;
            $itemdata->canmanage    = (int)$this->canmanage;
            $itemdata->isrequested  = ($status === 'requested');
            $itemdata->isconfirmed  = ($status === 'confirmed');
            $itemdata->isinprogress = ($status === 'inprogress');
            $itemdata->isrejected   = ($status === 'rejected');

            $categoriesmap[$categoryid]['items'][] = $itemdata;
        }

        $data->categories = array_values($categoriesmap);
        $data->hasresources = !empty($data->categories);

        return $data;
    }
}
