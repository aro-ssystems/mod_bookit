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
        // Run JavaScript inside the browser to find the resource row and inspect its class.
        $js = <<<JS
            (function(resourceName) {
                // Find all labels inside the booking modal that match the resource name.
                var labels = document.querySelectorAll('.modal-body .fgroup label, .mform .fgroup label');
                for (var i = 0; i < labels.length; i++) {
                    if (labels[i].textContent.trim() === resourceName) {
                        // Walk up to the fgroup container div.
                        var row = labels[i].closest('div[id^="fgroup_id_"]');
                        if (row) {
                            return row.classList.contains('bookit-resource-disabled') ? 'disabled' : 'enabled';
                        }
                    }
                }
                return 'not_found';
            })('$name')
        JS;

        $result = $this->getSession()->evaluateScript($js);

        if ($result === 'not_found') {
            throw new ExpectationException(
                "Resource \"$name\" was not found in the booking form.",
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
}
