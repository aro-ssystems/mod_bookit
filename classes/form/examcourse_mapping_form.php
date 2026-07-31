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
 * Form to add exam-course category mapping overrides.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\form;

use moodleform;
use mod_bookit\local\manager\examcourse_manager;
use mod_bookit\local\manager\event_manager;
use mod_bookit\local\persistent\institution;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Add mapping override form.
 */
class examcourse_mapping_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'addmapping', get_string('examcourse_mapping_add', 'mod_bookit'));

        $mform->addElement('select', 'purpose', get_string('examcourse_map_purpose', 'mod_bookit'), [
            examcourse_manager::PURPOSE_TEMPLATE => get_string('examcourse_purpose_template', 'mod_bookit'),
            examcourse_manager::PURPOSE_TARGET => get_string('examcourse_purpose_target', 'mod_bookit'),
        ]);
        $mform->setType('purpose', PARAM_ALPHA);

        $institutions = [0 => get_string('choosedots')];
        foreach (institution::get_records() as $inst) {
            $institutions[(int)$inst->get('id')] = format_string($inst->get('name'));
        }
        $mform->addElement('select', 'institutionid', get_string('institutions', 'mod_bookit'), $institutions);
        $mform->setType('institutionid', PARAM_INT);
        $mform->addRule('institutionid', null, 'required', null, 'client');

        $semesters = [0 => get_string('examcourse_semester_institution_wide', 'mod_bookit')];
        foreach (event_manager::get_semester_filter_options() as $id => $label) {
            $semesters[(int)$id] = $label;
        }
        $mform->addElement('select', 'semester', get_string('select_semester', 'mod_bookit'), $semesters);
        $mform->setType('semester', PARAM_INT);
        $mform->addHelpButton('semester', 'examcourse_map_semester', 'mod_bookit');

        $categories = \core_course_category::make_categories_list();
        $mform->addElement('select', 'categoryid', get_string('examcourse_map_category', 'mod_bookit'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate institution selection.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['institutionid'])) {
            $errors['institutionid'] = get_string('required');
        }
        if (empty($data['categoryid'])) {
            $errors['categoryid'] = get_string('required');
        }
        return $errors;
    }
}
