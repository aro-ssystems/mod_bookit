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
 * Tests for the room edit form.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\form;

use advanced_testcase;
use mod_bookit\local\install_helper;
use mod_bookit\local\persistent\room;

/**
 * Tests for the room edit form.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\form\edit_room_form
 */
final class edit_room_form_test extends advanced_testcase {
    /**
     * A user without room administration cannot open the room form.
     */
    public function test_user_without_managebasics_cannot_open_room_form(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);

        require($CFG->dirroot . '/mod/bookit/admin/edit_room.php');
    }

    /**
     * The form class must load.
     */
    public function test_form_class_loads(): void {
        $this->assertTrue(class_exists(edit_room_form::class));
    }

    /**
     * A shortname of seven characters is rejected. Six characters, including Unicode, are not.
     */
    public function test_shortname_length_in_extra_validation(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $toolong = $this->extra_validation_errors('TOOLONG');
        $this->assertArrayHasKey('shortname', $toolong);
        $this->assertStringContainsString('6', $toolong['shortname']);

        $this->assertArrayNotHasKey('shortname', $this->extra_validation_errors('ÄÖÜABC'));
        $this->assertArrayNotHasKey('shortname', $this->extra_validation_errors(''));
    }

    /**
     * Both language packs name the six-character limit.
     */
    public function test_shortname_error_string_in_english_and_german(): void {
        $manager = get_string_manager();
        $english = $manager->get_string('error_room_shortname_too_long', 'mod_bookit', 6, 'en');
        $german = $manager->get_string('error_room_shortname_too_long', 'mod_bookit', 6, 'de');
        $this->assertStringContainsString('6', $english);
        $this->assertStringContainsString('6', $german);
    }

    /**
     * The baseline shortname is DFLT. A later valid change is kept.
     */
    public function test_changed_default_shortname_is_kept(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        install_helper::ensure_fresh_install_baseline();
        $record = $DB->get_record('bookit_room', [], '*', MUST_EXIST);
        $this->assertSame(room::DEFAULT_SHORTNAME, (string) $record->shortname);

        $room = new room($record->id);
        $room->set('shortname', 'ROOM1');
        $room->update();

        $reloaded = new room($record->id);
        $this->assertSame('ROOM1', $reloaded->get('shortname'));
    }

    /**
     * Run the form's extra validation for one shortname.
     *
     * @param string $shortname Shortname to check.
     * @return array
     */
    private function extra_validation_errors(string $shortname): array {
        $form = new edit_room_form(null, ['persistent' => null]);
        $method = new \ReflectionMethod(edit_room_form::class, 'extra_validation');
        $errors = [];
        $data = (object) ['shortname' => $shortname];
        $files = [];
        $args = [$data, $files, &$errors];
        $extra = $method->invokeArgs($form, $args);
        return array_merge($errors, (array) $extra);
    }
}
