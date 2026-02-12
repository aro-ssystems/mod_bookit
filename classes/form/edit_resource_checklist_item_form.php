<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing a resource checklist item.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\form;

use core_form\dynamic_form;
use context;
use context_system;
use mod_bookit\local\manager\resource_checklist_manager;
use moodle_url;

/**
 * Form for editing a resource checklist item.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_resource_checklist_item_form extends dynamic_form {
    /**
     * Define the form.
     */
    public function definition(): void {
        $mform =& $this->_form;

        // Hidden field: id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Hidden field: resourceid (read-only, set from checklist entry).
        $mform->addElement('hidden', 'resourceid');
        $mform->setType('resourceid', PARAM_INT);

        // Hidden field: action (put or delete).
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        $mform->setDefault('action', 'put');

        // Field: sortorder.
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'core'), ['size' => 10]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        // Field: active (checkbox).
        $mform->addElement('advcheckbox', 'active', get_string('resources:active', 'mod_bookit'));
        $mform->setType('active', PARAM_BOOL);
        $mform->setDefault('active', 1);
        $mform->addHelpButton('active', 'resources:active', 'mod_bookit');

        // Field: duedate (date selector, optional).
        $mform->addElement('date_selector', 'duedate', get_string('resources:duedate', 'mod_bookit'), [
            'optional' => true,
        ]);

        // Field: duedatetype (select - relative/absolute).
        $duedatetypes = [
            '' => get_string('none'),
            'relative' => get_string('resources:duedate_relative', 'mod_bookit'),
            'absolute' => get_string('resources:duedate_absolute', 'mod_bookit'),
        ];
        $mform->addElement('select', 'duedatetype', get_string('resources:duedate_type', 'mod_bookit'), $duedatetypes);

        // Notification slots (optional, for future enhancement).
        $mform->addElement('header', 'notifications_header', get_string('notifications', 'core'));

        // For now, keep notification slots simple - just IDs (can be enhanced later).
        $mform->addElement('text', 'beforedueid', get_string('resources:notification_before', 'mod_bookit'), ['size' => 10]);
        $mform->setType('beforedueid', PARAM_INT);
        $mform->setDefault('beforedueid', 0);

        $mform->addElement('text', 'whendueid', get_string('resources:notification_when', 'mod_bookit'), ['size' => 10]);
        $mform->setType('whendueid', PARAM_INT);
        $mform->setDefault('whendueid', 0);

        $mform->addElement('text', 'overdueid', get_string('resources:notification_overdue', 'mod_bookit'), ['size' => 10]);
        $mform->setType('overdueid', PARAM_INT);
        $mform->setDefault('overdueid', 0);

        $mform->addElement('text', 'whendoneid', get_string('resources:notification_done', 'mod_bookit'), ['size' => 10]);
        $mform->setType('whendoneid', PARAM_INT);
        $mform->setDefault('whendoneid', 0);
    }

    /**
     * Check access for dynamic form submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/bookit:managebasics', context_system::instance());
    }

    /**
     * Process the form submission.
     *
     * @return array Result data
     */
    public function process_dynamic_submission(): array {
        global $USER;

        $data = $this->get_data();

        if ($data->action === 'delete') {
            resource_checklist_manager::delete_checklist_item($data->id);
            return ['success' => true, 'action' => 'deleted'];
        }

        // Save checklist item.
        $item = resource_checklist_manager::get_checklist_item_by_id($data->id);
        $item->set_sortorder($data->sortorder);
        $item->set_active($data->active ?? false);
        $item->set_duedate($data->duedate ?? null);
        $item->set_duedatetype($data->duedatetype ?? null);
        $item->set_beforedueid($data->beforedueid ?? null);
        $item->set_whendueid($data->whendueid ?? null);
        $item->set_overdueid($data->overdueid ?? null);
        $item->set_whendoneid($data->whendoneid ?? null);

        resource_checklist_manager::update_checklist_item($item, $USER->id);

        return ['success' => true, 'action' => 'updated'];
    }

    /**
     * Load initial data for form.
     *
     * @return object Data
     */
    protected function get_default_data(): object {
        $data = parent::get_default_data();

        if (!empty($data->id)) {
            $item = resource_checklist_manager::get_checklist_item_by_id($data->id);
            $data->resourceid = $item->get_resourceid();
            $data->sortorder = $item->get_sortorder();
            $data->active = $item->get_active();
            $data->duedate = $item->get_duedate();
            $data->duedatetype = $item->get_duedatetype();
            $data->beforedueid = $item->get_beforedueid();
            $data->whendueid = $item->get_whendueid();
            $data->overdueid = $item->get_overdueid();
            $data->whendoneid = $item->get_whendoneid();
        }

        return $data;
    }

    /**
     * Get page URL for form.
     *
     * @return moodle_url Page URL
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/bookit/admin/resource_checklist.php');
    }

    /**
     * Get context for dynamic form.
     *
     * @return context Form context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Set initial data.
     *
     * @param array $data Initial data
     */
    public function set_data($data): void {
        if (is_array($data)) {
            $data = (object)$data;
        }

        // Convert duedate from timestamp to date array if it exists.
        if (!empty($data->duedate)) {
            $data->duedate = $data->duedate;
        }

        parent::set_data($data);
    }
}
