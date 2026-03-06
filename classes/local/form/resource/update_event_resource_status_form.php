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
 * Dynamic form for updating event resource status inline.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\form\resource;

use context;
use context_module;
use core_form\dynamic_form;
use mod_bookit\local\manager\event_resource_manager;
use moodle_url;

/**
 * Dynamic form for updating event resource status.
 *
 * Submitted silently via ModalForm.submitFormAjax() — no modal is shown.
 * Using Moodle's built-in core_form_dynamic_form web service instead of
 * a custom external API endpoint.
 */
class update_event_resource_status_form extends dynamic_form {
    /**
     * Allowed status values for event resource.
     *
     * @var string[]
     */
    const VALID_STATUSES = ['requested', 'confirmed', 'inprogress', 'rejected'];

    /**
     * Form definition — all fields are hidden.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'eventid');
        $mform->setType('eventid', PARAM_INT);

        $mform->addElement('hidden', 'resourceid');
        $mform->setType('resourceid', PARAM_INT);

        $mform->addElement('hidden', 'status');
        $mform->setType('status', PARAM_ALPHA);
    }

    /**
     * Validate submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array Errors keyed by field name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!in_array($data['status'] ?? '', self::VALID_STATUSES, true)) {
            $errors['status'] = 'Invalid status value.';
        }

        return $errors;
    }

    /**
     * Check capability before processing submission.
     */
    protected function check_access_for_dynamic_submission(): void {
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);
        $context = context_module::instance($cmid);
        require_capability('mod/bookit:managebasics', $context);
    }

    /**
     * Process the form submission.
     *
     * @return array Updated status value.
     */
    public function process_dynamic_submission(): array {
        $data = $this->get_data();

        event_resource_manager::update_status(
            (int)$data->eventid,
            (int)$data->resourceid,
            $data->status
        );

        return ['status' => $data->status];
    }

    /**
     * Load current data into the form (pre-fill from args).
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data([
            'cmid'       => $this->optional_param('cmid', 0, PARAM_INT),
            'eventid'    => $this->optional_param('eventid', 0, PARAM_INT),
            'resourceid' => $this->optional_param('resourceid', 0, PARAM_INT),
            'status'     => $this->optional_param('status', '', PARAM_ALPHA),
        ]);
    }

    /**
     * Return context for the dynamic form.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);
        return context_module::instance($cmid);
    }

    /**
     * URL used when form is used outside AJAX context.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/bookit/view.php', [
            'id' => $this->optional_param('cmid', 0, PARAM_INT),
        ]);
    }
}
