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
 * Form for creating and editing a resource.
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
use mod_bookit\local\manager\resource_manager;
use moodle_url;

/**
 * Form for creating and editing a resource.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_resource_form extends dynamic_form {
    /**
     * Define the form.
     */
    public function definition(): void {
        $mform =& $this->_form;

        // Hidden field: id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Field: name.
        $mform->addElement('text', 'name', get_string('resources:name', 'mod_bookit'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', null, 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'resources:name', 'mod_bookit');

        // Field: categoryid (select).
        $categories = resource_manager::get_all_categories(false); // Include inactive for edit.
        $categoryoptions = [];
        foreach ($categories as $category) {
            $categoryoptions[$category->get_id()] = $category->get_name();
        }

        if (empty($categoryoptions)) {
            $categoryoptions = [0 => get_string('none')];
            $mform->addElement('static', 'categoryid_notice', '', get_string('no_categories_available', 'mod_bookit'));
        }

        $mform->addElement('select', 'categoryid', get_string('resources:category', 'mod_bookit'), $categoryoptions);
        $mform->addRule('categoryid', null, 'required', null, 'client');
        $mform->addHelpButton('categoryid', 'resources:category', 'mod_bookit');

        // Field: description.
        $mform->addElement('textarea', 'description', get_string('resources:description', 'mod_bookit'), [
            'rows' => 4,
            'cols' => 50,
        ]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'resources:description', 'mod_bookit');

        // Field: amountirrelevant (checkbox).
        $mform->addElement('advcheckbox', 'amountirrelevant', get_string('resources:amountirrelevant', 'mod_bookit'));
        $mform->setDefault('amountirrelevant', 0);
        $mform->addHelpButton('amountirrelevant', 'resources:amountirrelevant', 'mod_bookit');

        // Field: amount (text, conditional).
        $mform->addElement('text', 'amount', get_string('resources:amount', 'mod_bookit'), ['size' => 10]);
        $mform->setType('amount', PARAM_INT);
        $mform->setDefault('amount', 1);
        $mform->addHelpButton('amount', 'resources:amount', 'mod_bookit');

        // Hide amount field if amountirrelevant is checked.
        $mform->hideIf('amount', 'amountirrelevant', 'checked');

        // Validation rule: amount required if not amountirrelevant.
        $mform->addRule('amount', null, 'required', null, 'client');
        $mform->disabledIf('amount', 'amountirrelevant', 'checked');

        // Field: active.
        $mform->addElement('advcheckbox', 'active', get_string('resources:active', 'mod_bookit'));
        $mform->setDefault('active', 1);
        $mform->addHelpButton('active', 'resources:active', 'mod_bookit');

        // Hidden field: sortorder.
        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
    }

    /**
     * Custom validation.
     *
     * @param array $data Array of ("fieldname"=>value) of submitted data
     * @param array $files Array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors, or an empty array if everything is OK
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Validate: amount required if not amountirrelevant.
        if (empty($data['amountirrelevant'])) {
            if (empty($data['amount']) || $data['amount'] < 1) {
                $errors['amount'] = get_string('error_amount_required', 'mod_bookit');
            }
        }

        // Validate: category exists.
        if (!empty($data['categoryid'])) {
            try {
                resource_manager::get_category($data['categoryid']);
            } catch (\Exception $e) {
                $errors['categoryid'] = get_string('error_category_not_found', 'mod_bookit');
            }
        }

        return $errors;
    }

    /**
     * Load in existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        $id = $this->optional_param('id', null, PARAM_INT);
        $categoryid = $this->optional_param('categoryid', null, PARAM_INT);

        if (!empty($id)) {
            // Edit mode: Load existing resource.
            $resource = resource_manager::get_resource_by_id($id);
            $data = (object) [
                'id' => $resource->get_id(),
                'name' => $resource->get_name(),
                'categoryid' => $resource->get_categoryid(),
                'description' => $resource->get_description(),
                'amount' => $resource->get_amount(),
                'amountirrelevant' => $resource->is_amountirrelevant() ? 1 : 0,
                'active' => $resource->is_active() ? 1 : 0,
                'sortorder' => $resource->get_sortorder(),
            ];
        } else {
            // Create mode: Set defaults.
            $data = (object) [
                'id' => 0,
                'categoryid' => $categoryid ?? 0,
                'amount' => 1,
                'amountirrelevant' => 0,
                'active' => 1,
                'sortorder' => 0,
            ];
        }

        $this->set_data($data);
    }

    /**
     * Check if current user has access to this form.
     *
     * @throws \moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/bookit:managebasics', context_system::instance());
    }

    /**
     * Process the form submission.
     *
     * @return array Empty array (required by interface)
     */
    public function process_dynamic_submission(): array {
        $formdata = $this->get_data();

        // If amountirrelevant is checked, set amount to 0.
        $amount = $formdata->amountirrelevant ? 0 : ($formdata->amount ?? 1);

        // Create entity from form data.
        $resource = new \mod_bookit\local\entity\bookit_resource(
            $formdata->id ?: null,
            $formdata->name,
            $formdata->categoryid,
            $formdata->description ?? '',
            $amount,
            (bool) $formdata->amountirrelevant,
            $formdata->sortorder ?? 0,
            (bool) $formdata->active,
            time(), // Timecreated.
            time()   // Timemodified.
        );

        // Save via manager.
        global $USER;
        resource_manager::save_resource($resource, $USER->id);

        return [];
    }

    /**
     * Returns context where this form is used.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url().
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/bookit/admin/resources.php');
    }
}
