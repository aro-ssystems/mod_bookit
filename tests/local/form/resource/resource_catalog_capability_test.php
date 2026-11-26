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
 * Capability checks for resource catalog dynamic forms.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\form\resource;

use advanced_testcase;
use ReflectionMethod;
use required_capability_exception;

/**
 * Tests that catalog delete/deactivate forms require managebasics.
 *
 * @covers \mod_bookit\local\form\resource\edit_resource_form
 * @covers \mod_bookit\local\form\resource\edit_resource_category_form
 */
final class resource_catalog_capability_test extends advanced_testcase {
    /**
     * Users without managebasics cannot access the resource edit/delete form.
     */
    public function test_edit_resource_form_requires_managebasics(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $form = new edit_resource_form();
        $method = new ReflectionMethod(edit_resource_form::class, 'check_access_for_dynamic_submission');
        $method->setAccessible(true);

        $this->expectException(required_capability_exception::class);
        $method->invoke($form);
    }

    /**
     * Users without managebasics cannot access the category edit/delete form.
     */
    public function test_edit_resource_category_form_requires_managebasics(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $form = new edit_resource_category_form();
        $method = new ReflectionMethod(edit_resource_category_form::class, 'check_access_for_dynamic_submission');
        $method->setAccessible(true);

        $this->expectException(required_capability_exception::class);
        $method->invoke($form);
    }
}
