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

namespace mod_bookit\local\manager;

use advanced_testcase;

/**
 * Unit tests for color_manager contrast helpers.
 *
 * @covers \mod_bookit\local\manager\color_manager
 * @package mod_bookit
 * @copyright 2026 ssystems GmbH <oss@ssystems.de>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class color_manager_test extends advanced_testcase {
    /**
     * Empty and invalid colours must resolve to dark text for readable badges.
     *
     * @return void
     */
    public function test_empty_and_invalid_backgrounds_use_dark_text(): void {
        $this->assertSame('#000', color_manager::get_textcolor_for_background(''));
        $this->assertSame('#000', color_manager::get_textcolor_for_background('#'));
        $this->assertSame('#000', color_manager::get_textcolor_for_background('#fff'));
        $this->assertSame('#000', color_manager::get_textcolor_for_background('notahex'));
    }

    /**
     * Light backgrounds use dark text; dark backgrounds use light text.
     *
     * @return void
     */
    public function test_light_and_dark_backgrounds(): void {
        $this->assertSame('#000', color_manager::get_textcolor_for_background('#ffffff'));
        $this->assertSame('#000', color_manager::get_textcolor_for_background('#eeeeee'));
        $this->assertSame('#fff', color_manager::get_textcolor_for_background('#000000'));
        $this->assertSame('#fff', color_manager::get_textcolor_for_background('#1a1a1a'));
    }
}
