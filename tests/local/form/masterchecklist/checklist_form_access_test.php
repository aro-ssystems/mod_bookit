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
 * Capability checks for master checklist dynamic forms.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\form\masterchecklist;

use advanced_testcase;
use ReflectionMethod;
use required_capability_exception;

/**
 * Tests that master checklist forms require the manage capability.
 *
 * @covers \mod_bookit\local\form\masterchecklist\edit_checklist_master_form
 * @covers \mod_bookit\local\form\masterchecklist\edit_checklist_category_form
 * @covers \mod_bookit\local\form\masterchecklist\edit_checklist_item_form
 * @covers \mod_bookit\local\form\masterchecklist\import_checklist_form
 * @covers \mod_bookit\local\form\masterchecklist\export_checklist_form
 */
final class checklist_form_access_test extends advanced_testcase {
    /**
     * Form classes that edit or export the master checklist.
     *
     * @return string[]
     */
    private function form_classes(): array {
        return [
            edit_checklist_master_form::class,
            edit_checklist_category_form::class,
            edit_checklist_item_form::class,
            import_checklist_form::class,
            export_checklist_form::class,
        ];
    }

    /**
     * A user without the capability cannot submit any of the five forms.
     */
    public function test_forms_reject_user_without_capability(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        foreach ($this->form_classes() as $class) {
            $form = new $class();
            $method = new ReflectionMethod($class, 'check_access_for_dynamic_submission');
            $method->setAccessible(true);
            try {
                $method->invoke($form);
                $this->fail($class . ' allowed a user without the capability');
            } catch (required_capability_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    /**
     * The site administrator passes the same access check.
     */
    public function test_forms_allow_user_with_capability(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        foreach ($this->form_classes() as $class) {
            $form = new $class();
            $method = new ReflectionMethod($class, 'check_access_for_dynamic_submission');
            $method->setAccessible(true);
            $method->invoke($form);
        }
        $this->assertTrue(true);
    }
}
