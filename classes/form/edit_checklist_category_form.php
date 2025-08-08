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
use mod_bookit\local\entity\bookit_checklist_category;
use mod_bookit\local\manager\checklist_manager;

class edit_checklist_category_form extends dynamic_form {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'masterid');
        $mform->setType('masterid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        $mform->setDefault('action', 'put');

        $mform->addElement('hidden', 'checklistitems');
        $mform->setType('checklistitems', PARAM_TEXT);

        $mform->addElement('text', 'name', get_string('category_name', 'mod_bookit'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

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

        $ajaxdata = $this->_ajaxformdata;
        error_log("AJAX data: " . print_r($ajaxdata, true));

        if (!empty($data->action)) {
            switch ($data->action) {
                case 'delete':
                    return $this->process_delete_request($ajaxdata['id']);
                case 'put':
                    return $this->process_put_request($ajaxdata);
                default:
                    return ['success' => false, 'message' => 'Unknown action: ' . $data->action];
            }
        }

        // if (!empty($data->id)) {
        //     // Bestehende Kategorie bearbeiten
        //     try {
        //         $category = \mod_bookit\local\entity\bookit_checklist_category::from_database($data->id);
        //         $category->name = $data->name;
        //         $category->description = $data->description ?? '';
        //         $category->usermodified = $USER->id;
        //         $category->timemodified = time();

        //         $category->save();
        //         return ['success' => true, 'message' => get_string('category_updated', 'mod_bookit'), 'id' => $category->id];
        //     } catch (\Exception $e) {
        //         return ['success' => false, 'message' => $e->getMessage()];
        //     }
        // } else {
        //     // Neue Kategorie erstellen
        //     if (empty($data->masterid)) {
        //         return ['success' => false, 'message' => 'Missing masterid'];
        //     }

        //     try {

        //         $category = new \mod_bookit\local\entity\bookit_checklist_category(
        //             0,
        //             $data->masterid,
        //             $data->name,
        //             $data->description ?? '',
        //             [],
        //             0,
        //             $USER->id,
        //             time(),
        //             time()
        //         );

        //         $id = $category->save();

        //         // master needs update here
        //         return [
        //             [
        //                 'name' => 'checklistcategories',
        //                 'action' => 'put',
        //                 'fields' => [
        //                     'id' => $id,
        //                     'name' => $data->name,
        //                     'order' => 0,
        //                 ],
        //             ],
        //         ];


        //         // return ['success' => true, 'message' => get_string('category_created', 'mod_bookit'), 'id' => $id];
        //     } catch (\Exception $e) {
        //         return ['success' => false, 'message' => $e->getMessage()];
        //     }
        // }
    }

    /**
     * Set data for the form.
     */
    public function set_data_for_dynamic_submission(): void {
        $data = [];

        error_log("Setting data for dynamic submission in FORM CLASS: " . print_r($this->_ajaxformdata, true));

        if (!empty($this->_ajaxformdata['masterid'])) {
            $data['masterid'] = $this->_ajaxformdata['masterid'];
        }

        if (!empty($this->_ajaxformdata['id'])) {
            $id = $this->_ajaxformdata['id'];

            try {
                $category = bookit_checklist_category::from_database($id);
                $data['id'] = $category->id;
                $data['masterid'] = $category->masterid;
                $data['name'] = $category->name;
                $data['checklistitems'] = json_encode($this->_ajaxformdata['checklistitems']);

            } catch (\Exception $e) {
                error_log("Error loading checklist category with ID $id: " . $e->getMessage());
            }
        }

        $this->set_data($data);
        error_log("Data set for dynamic submission AFTER: " . print_r($data, true));
    }
    public function process_put_request($ajaxdata = []): array {
        global $USER;

        // error_log(json_encode($ajaxdata['checklistitems'] ?? []));

        // error_log(print_r(json_decode(json_encode($ajaxdata['checklistitems'] ?? [])), true));

        $lol = $ajaxdata['checklistitems'];
        // Log the type of the decoded JSON
        error_log("Type of lol: " . gettype($lol));
        error_log("Value of lol: " . print_r($lol, true));

        // Convert the checklistitems object to an array if needed
        if (is_object($lol) || is_array($lol)) {
            // If the conversion was successful, store it in a format that can be saved
            $checklistitems = json_encode($lol);
            error_log("Converted checklistitems: " . $checklistitems);
        } else {
            // Handle case where JSON decoding didn't result in expected type
            $checklistitems = '';
            error_log("Invalid checklistitems format. Using empty array.");
        }



        if (!empty($ajaxdata['id'])) {
            error_log("Processing PUT request for existing category with ID: " . $ajaxdata['id']);
            $category = bookit_checklist_category::from_database($ajaxdata['id']);
            $category->name = $ajaxdata['name'];
            $category->description = $ajaxdata['description'] ?? '';
            $category->checklistitems = $ajaxdata['checklistitems'] ?? '';
            $category->usermodified = $USER->id;
            $category->timemodified = time();

            $category->save();

            $id = $category->id;

        } else {
            error_log("Creating new checklist category with AJAX data in NEW FUNCTION: " . print_r($ajaxdata, true));
            $category = new bookit_checklist_category(
                0,
                $ajaxdata['masterid'],
                $ajaxdata['name'],
                $ajaxdata['description'] ?? '',
                '',
                0,
                $USER->id,
                time(),
                time()
            );

            $id = $category->save();

            // $id = $item->id;

        }

        return [
            [
                'name' => 'checklistcategories',
                'action' => 'put',
                'fields' => [
                    'id' => $id,
                    'name' => $ajaxdata['name'],
                    'order' => 0,
                    'items' => $ajaxdata['checklistitems'],
                ],
            ],
        ];
    }

    public function process_delete_request($id): array {
        $category = bookit_checklist_category::from_database($id);
        $category->delete();

        return [
            [
                'name' => 'checklistcategories',
                'action' => 'delete',
                'fields' => [
                    'id' => $id,
                ],
            ],
        ];
    }

}