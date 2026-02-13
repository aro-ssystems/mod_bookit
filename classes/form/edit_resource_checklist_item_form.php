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
use mod_bookit\local\manager\resource_manager;
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

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        $mform->setDefault('action', 'put');

        // Resource display (read-only).
        $mform->addElement(
            'static',
            'resourcename',
            get_string('resource', 'mod_bookit'),
            ''
        );

        $mform->addElement(
            'static',
            'categoryname',
            get_string('resources:category', 'mod_bookit'),
            ''
        );

        // Sort order.
        $mform->addElement(
            'text',
            'sortorder',
            get_string('checklist_sortorder', 'mod_bookit'),
            ['size' => 10]
        );
        $mform->setType('sortorder', PARAM_INT);
        $mform->addRule('sortorder', null, 'numeric', null, 'client');
        $mform->addHelpButton('sortorder', 'checklist_sortorder', 'mod_bookit');

        // Active checkbox.
        $mform->addElement(
            'advcheckbox',
            'active',
            get_string('active', 'mod_bookit')
        );
        $mform->setDefault('active', 1);
        $mform->addHelpButton('active', 'checklist_active', 'mod_bookit');

        // Due date type selector.
        $duedatetypes = [
            '' => get_string('none'),
            'before_event' => get_string('duedate_before_event', 'mod_bookit'),
            'after_event' => get_string('duedate_after_event', 'mod_bookit'),
            'fixed_date' => get_string('duedate_fixed_date', 'mod_bookit'),
        ];

        $mform->addElement(
            'select',
            'duedatetype',
            get_string('duedatetype', 'mod_bookit'),
            $duedatetypes
        );
        $mform->addHelpButton('duedatetype', 'duedatetype', 'mod_bookit');

        // Due date value - days offset (for before_event / after_event).
        $mform->addElement(
            'text',
            'duedate_days',
            get_string('duedate_days', 'mod_bookit'),
            ['size' => 10]
        );
        $mform->setType('duedate_days', PARAM_INT);
        $mform->hideIf('duedate_days', 'duedatetype', 'eq', '');
        $mform->hideIf('duedate_days', 'duedatetype', 'eq', 'fixed_date');

        // Due date value - fixed date (for fixed_date).
        $mform->addElement(
            'date_time_selector',
            'duedate_fixed',
            get_string('duedate_fixed', 'mod_bookit'),
            ['optional' => false]
        );
        $mform->hideIf('duedate_fixed', 'duedatetype', 'neq', 'fixed_date');
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
     * Validate the form data.
     *
     * @param array $data Form data
     * @param array $files Uploaded files
     * @return array Validation errors
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Validate sortorder is positive.
        if (isset($data['sortorder']) && $data['sortorder'] < 0) {
            $errors['sortorder'] = get_string('error_sortorder_negative', 'mod_bookit');
        }

        // Validate due date configuration.
        if (!empty($data['duedatetype'])) {
            if ($data['duedatetype'] === 'fixed_date') {
                // Fixed date must be in the future (optional validation).
                if (empty($data['duedate_fixed'])) {
                    $errors['duedate_fixed'] = get_string('error_duedate_required', 'mod_bookit');
                }
            } else {
                // Before_event / after_event require days value.
                if (empty($data['duedate_days']) && $data['duedate_days'] !== '0') {
                    $errors['duedate_days'] = get_string('error_duedate_days_required', 'mod_bookit');
                }
            }
        }

        return $errors;
    }

    /**
     * Process the form submission.
     *
     * @return array Result data
     */
    public function process_dynamic_submission(): array {
        global $USER;

        $data = $this->get_data();

        // Handle delete action.
        if ($data->action === 'delete') {
            resource_checklist_manager::delete_checklist_item($data->id);
            return [
                'success' => true,
                'action' => 'deleted',
            ];
        }

        // Load existing item.
        $item = resource_checklist_manager::get_checklist_item_by_id($data->id);
        if (!$item) {
            throw new \moodle_exception('checklistitemnotfound', 'mod_bookit');
        }

        // Update basic fields.
        $item->set_sortorder($data->sortorder ?? 0);
        $item->set_active($data->active ?? false);
        $item->set_duedatetype($data->duedatetype ?? null);

        // Convert duedate based on type.
        if ($data->duedatetype === 'fixed_date' && !empty($data->duedate_fixed)) {
            $item->set_duedate($data->duedate_fixed);
        } else if ($data->duedatetype && !empty($data->duedate_days)) {
            // Convert days to seconds.
            $item->set_duedate($data->duedate_days * 86400);
        } else {
            $item->set_duedate(null);
        }

        // Save item.
        resource_checklist_manager::update_checklist_item($item, $USER->id);

        return [
            'success' => true,
            'action' => 'updated',
        ];
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

        parent::set_data($data);
    }

    /**
     * Set data for dynamic submission.
     *
     * This method is called when the form is loaded dynamically.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $id = $this->optional_param('id', null, PARAM_INT);

        if (empty($id)) {
            throw new \moodle_exception('invalidchecklistitemid', 'mod_bookit');
        }

        // Load checklist item.
        $item = resource_checklist_manager::get_checklist_item_by_id($id);
        if (!$item) {
            throw new \moodle_exception('checklistitemnotfound', 'mod_bookit');
        }

        // Load associated resource for display.
        $resource = resource_manager::get_resource_by_id($item->get_resourceid());
        if (!$resource) {
            throw new \moodle_exception('resourcenotfound', 'mod_bookit');
        }

        // Load category for display.
        $category = resource_manager::get_category($resource->get_categoryid());

        $data = (object) [
            'id' => $item->get_id(),
            'resourcename' => $resource->get_name(),
            'categoryname' => $category ? $category->get_name() : get_string('none'),
            'sortorder' => $item->get_sortorder(),
            'active' => $item->is_active() ? 1 : 0,
            'duedatetype' => $item->get_duedatetype() ?? '',
            'action' => 'put',
        ];

        // Convert duedate based on type.
        if ($item->get_duedatetype() === 'fixed_date' && $item->get_duedate()) {
            $data->duedate_fixed = $item->get_duedate();
        } else if ($item->get_duedatetype() && $item->get_duedate()) {
            // Convert seconds to days.
            $data->duedate_days = intval($item->get_duedate() / 86400);
        }

        $this->set_data($data);
    }
}
