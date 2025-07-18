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
 * Form for creating and editing an event.
 *
 * @package     mod_bookit
 * @copyright   2025 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\form;

use core_form\dynamic_form;

class edit_checklist_category_form extends dynamic_form {

    public function definition() {
        $mform = $this->_form;

        // Versteckte Felder für ID und Master-ID
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'masterid');
        $mform->setType('masterid', PARAM_INT);

        // Name der Kategorie
        $mform->addElement('text', 'name', get_string('category_name', 'mod_bookit'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // $this->add_action_buttons();
    }

    /**
     * Check if the current user has access to this form.
     */
    protected function check_access_for_dynamic_submission(): void {

    }

    /**
     * Get the context for this form.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        if (!empty($this->_ajaxformdata['cmid'])) {
            $cmid = $this->_ajaxformdata['cmid'];
            return \context_module::instance($cmid);
        }
        return \context_system::instance();
    }

    /**
     * Get the URL to return to after form submission.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        if (!empty($this->_ajaxformdata['cmid'])) {
            $cmid = $this->_ajaxformdata['cmid'];
            return new \moodle_url('/mod/bookit/master_checklist.php');
        }
        return new \moodle_url('/');
    }

    /**
     * Process the form submission.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();

        error_log("DATA: " . print_r($data, true));

        // Checklist category Objekt erstellen oder laden
        if (!empty($data->id)) {
            // Bestehende Kategorie bearbeiten
            try {
                $category = \mod_bookit\local\entity\bookit_checklist_category::from_database($data->id);
                $category->name = $data->name;
                $category->description = $data->description ?? '';
                $category->usermodified = $USER->id;
                $category->timemodified = time();

                $category->save();
                return ['success' => true, 'message' => get_string('category_updated', 'mod_bookit'), 'id' => $category->id];
            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        } else {
            // Neue Kategorie erstellen
            if (empty($data->masterid)) {
                return ['success' => false, 'message' => 'Missing masterid'];
            }

            try {

                $category = new \mod_bookit\local\entity\bookit_checklist_category(
                    0,
                    $data->masterid,
                    $data->name,
                    $data->description ?? '',
                    [],
                    0,
                    $USER->id,
                    time(),
                    time()
                );

                $id = $category->save();

                // master needs update here
                return [
                    [
                        'name' => 'checklistcategories',
                        'action' => 'put',
                        'fields' => [
                            'id' => $id,
                            'name' => $data->name,
                            'order' => 0,
                        ],
                    ],
                ];


                // return ['success' => true, 'message' => get_string('category_created', 'mod_bookit'), 'id' => $id];
            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * Set data for the form.
     */
    public function set_data_for_dynamic_submission(): void {
        $data = [];

        // Masterid von der Anfrage übernehmen
        if (!empty($this->_ajaxformdata['masterid'])) {
            $data['masterid'] = $this->_ajaxformdata['masterid'];
        }

        // Falls eine ID vorhanden ist, bestehende Daten laden
        if (!empty($this->_ajaxformdata['id'])) {
            $id = $this->_ajaxformdata['id'];

            try {
                $category = \mod_bookit\local\entity\bookit_checklist_category::from_database($id);
                $data['id'] = $category->id;
                $data['masterid'] = $category->masterid;
                $data['name'] = $category->name;
            } catch (\Exception $e) {
                // Fehler beim Laden - mit leeren Daten fortfahren
            }
        }

        $this->set_data($data);
    }
}