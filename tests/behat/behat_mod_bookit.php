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
 * Custom Behat step definitions for mod_bookit.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Mink\Exception\ExpectationException;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Custom Behat step definitions for mod_bookit.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_bookit extends behat_base {
    /**
     * Checks that the given resource row has the bookit-resource-disabled class (is greyed out).
     *
     * This is the primary regression check for the room filter: after selecting a room in the
     * booking form, resources not assigned to that room must receive the .bookit-resource-disabled
     * CSS class which sets opacity:0.4 and pointer-events:none.
     *
     * @Then the resource :name should be disabled in the booking form
     * @param string $name Visible resource name shown in the booking form
     * @throws ExpectationException
     */
    public function the_resource_should_be_disabled_in_the_booking_form(string $name): void {
        $this->assert_resource_state($name, true);
    }

    /**
     * Checks that the given resource row does NOT have the bookit-resource-disabled class (is enabled).
     *
     * @Then the resource :name should be enabled in the booking form
     * @param string $name Visible resource name shown in the booking form
     * @throws ExpectationException
     */
    public function the_resource_should_be_enabled_in_the_booking_form(string $name): void {
        $this->assert_resource_state($name, false);
    }

    /**
     * Assert whether a resource row in the booking form is disabled or enabled.
     *
     * Uses JavaScript to find the resource group row by its label text, then checks
     * whether the ancestor .fgroup container has the .bookit-resource-disabled class.
     *
     * @param string $name Resource label text.
     * @param bool $expectdisabled True to assert disabled, false to assert enabled.
     * @throws ExpectationException
     */
    private function assert_resource_state(string $name, bool $expectdisabled): void {
        // In Moodle 4.5/Boost, addGroup() renders the group label as a <p id="fgroup_id_..._label">.
        // The bookit-resource-disabled class is applied to the outer [id^="fgroup_id_resourcegroup_"] div.
        $js = <<<JS
            (function(resourceName) {
                var groups = document.querySelectorAll('[id^="fgroup_id_resourcegroup_"]');
                for (var i = 0; i < groups.length; i++) {
                    var labelEl = groups[i].querySelector('[id$="_label"]');
                    if (labelEl && labelEl.textContent.trim() === resourceName) {
                        return groups[i].classList.contains('bookit-resource-disabled') ? 'disabled' : 'enabled';
                    }
                }
                var found = Array.from(groups).map(function(g) {
                    var l = g.querySelector('[id$="_label"]');
                    return l ? l.textContent.trim() : '(no label)';
                });
                return 'not_found:labels=' + JSON.stringify(found);
            })('$name')
        JS;

        $result = $this->getSession()->evaluateScript($js);

        if (strpos($result, 'not_found') === 0) {
            throw new ExpectationException(
                "Resource \"$name\" was not found in the booking form. JS info: $result",
                $this->getSession()
            );
        }

        $isdisabled = ($result === 'disabled');
        if ($expectdisabled && !$isdisabled) {
            throw new ExpectationException(
                "Resource \"$name\" was expected to be disabled (greyed out) but it is enabled.",
                $this->getSession()
            );
        }
        if (!$expectdisabled && $isdisabled) {
            throw new ExpectationException(
                "Resource \"$name\" was expected to be enabled but it is disabled (greyed out).",
                $this->getSession()
            );
        }
    }

    /**
     * Selects an option from a named select field in the booking form.
     *
     * This step selects a room (or other option) from a Moodle select element identified
     * by its visible label. It is equivalent to the built-in "I select ... from the ... field"
     * but targets the Moodle form element by label text.
     *
     * @When I select :value from the :field field
     * @param string $value The option text to select.
     * @param string $field The visible label of the select field.
     */
    public function i_select_from_the_field(string $value, string $field): void {
        $selectnode = $this->find_field($field);
        $selectnode->selectOption($value);
    }
}
