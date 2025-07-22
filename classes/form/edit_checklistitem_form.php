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

class edit_checklistitem_form extends dynamic_form {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('pluginname', 'mod_bookit'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // TODO get current master categories

        $ajaxdata = $this->_ajaxformdata;
        // TODO we have masterid and can get cats from database? => using js saves db calls
        $categories = [];

        if (!empty($ajaxdata['categories'])) {
            error_log(print_r($ajaxdata['categories'], true));
            foreach ($ajaxdata['categories'] as $cat) {
                // Check for expected structure
                if (isset($cat['id']) && isset($cat['name'])) {
                    $categories[$cat['id']] = $cat['name'];
                }
            }
        }
        error_log("HERE");
        error_log(print_r($categories, true));
        $mform->addElement('select', 'categoryid', get_string('checklistcategory', 'mod_bookit'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required', null, 'client');

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
            return new \moodle_url('/mod/bookit/master_checklist.php', ['id' => $cmid]);
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

        if (empty($data->categoryid)) {
            $data->categoryid = $ajaxdata['categoryid'] ?? null;
        }

        error_log("Creating new checklist item with data: " . print_r($data, true));

        if (!empty($data->id)) {

            try {
                $item = \mod_bookit\local\entity\bookit_checklist_item::from_database($data->id);
                $item->title = $data->name;
                $item->description = $data->description ?? '';
                $item->usermodified = $USER->id;
                $item->timemodified = time();

                $item->save();
                return ['success' => true, 'message' => get_string('item_updated', 'mod_bookit'), 'id' => $item->id];
            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        } else {

            // error_log("Creating new checklist item with data: " . print_r($data, true));

            if (empty($data->categoryid)) {
                return ['success' => false, 'message' => 'Missing masterid or categoryid'];
            }
            try {
                $item = new \mod_bookit\local\entity\bookit_checklist_item(
                    0,
                    1,
                    $data->categoryid,
                    null, // parentid
                    $data->name,
                    $data->description ?? '',
                    1, // itemtype
                    null, // options
                    0, // sortorder
                    0, // isrequired
                    null, // defaultvalue
                    0, // due_days_offset
                    $USER->id,
                    time(),
                    time()
                );

                $id = $item->save();
                return [
                    [
                        'name' => 'checklistitems',
                        'action' => 'put',
                        'fields' => [
                            'id' => $id,
                            'name' => $data->name,
                            'order' => 0,
                            'category' => $data->categoryid,
                        ],
                    ],
                ];

            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }

        }

        // return $data;
    }

    /**
     * Set data for the form.
     */
    public function set_data_for_dynamic_submission(): void {

        error_log("Setting data for dynamic submission in edit_checklistitem_form");

        // $data = [];

        // TODO check why no categoryid is send

        // if (!empty($this->_ajaxformdata['masterid'])) {
        //     $data['masterid'] = $this->_ajaxformdata['masterid'];
        // }

        // // if (!empty($this->_ajaxformdata['categoryid'])) {
        // //     $data['categoryid'] = $this->_ajaxformdata['categoryid'];
        // // }

        // $categories = isset($this->_ajaxformdata['categories']) ? $this->_ajaxformdata['categories'] : [];


        // if (!empty($this->_ajaxformdata['id'])) {
        //     $id = $this->_ajaxformdata['id'];

        //     try {
        //         $item = \mod_bookit\local\entity\bookit_checklist_item::from_database($id);
        //         $data['id'] = $item->id;
        //         $data['masterid'] = $item->masterid;
        //         $data['categoryid'] = $item->categoryid;
        //         $data['title'] = $item->title;
        //     } catch (\Exception $e) {
        //         // Fehler beim Laden - mit leeren Daten fortfahren
        //     }
        // }

        // $this->set_data($data);
        return;
    }
}