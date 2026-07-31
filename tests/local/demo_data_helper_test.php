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
 * Unit tests for demo_data_helper.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local;

use advanced_testcase;

/**
 * Unit tests for demo_data_helper generate/reset invariants.
 *
 * @covers \mod_bookit\local\demo_data_helper
 */
final class demo_data_helper_test extends advanced_testcase {
    /**
     * Baseline-only is true after ensure_fresh_install_baseline on empty data.
     *
     * @return void
     */
    public function test_is_baseline_only_true_after_ensure_baseline(): void {
        $this->resetAfterTest(true);
        install_helper::ensure_fresh_install_baseline();
        $this->assertTrue(demo_data_helper::is_baseline_only());
    }

    /**
     * Extra room makes the instance non-baseline.
     *
     * @return void
     */
    public function test_is_baseline_only_false_with_extra_room(): void {
        global $DB;

        $this->resetAfterTest(true);
        install_helper::ensure_fresh_install_baseline();
        $DB->insert_record('bookit_room', (object)[
            'name' => 'Extra room',
            'shortname' => 'XTRA',
            'description' => '',
            'location' => '',
            'eventcolor' => '',
            'active' => 1,
            'roommode' => 0,
            'seats' => 0,
            'preventoverlap' => 0,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->assertFalse(demo_data_helper::is_baseline_only());
    }

    /**
     * Generate seeds the minimum demo dataset and enables modules; second generate refuses.
     *
     * @return void
     */
    public function test_generate_seeds_and_refuses_dirty(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        install_helper::ensure_fresh_install_baseline();

        $first = demo_data_helper::generate();
        $this->assertSame('success', $first['status']);
        $this->assertGreaterThanOrEqual(2, $DB->count_records('bookit_room'));
        $this->assertGreaterThanOrEqual(2, $DB->count_records('bookit_resource_category'));
        $this->assertGreaterThan(0, $DB->count_records('bookit_resource'));
        $this->assertGreaterThanOrEqual(2, $DB->count_records('bookit_checklist_category'));
        $this->assertGreaterThan(0, $DB->count_records('bookit_checklist_item'));
        $this->assertGreaterThanOrEqual(1, $DB->count_records('bookit_event'));
        $this->assertSame(1, (int)get_config('mod_bookit', install_helper::CONFIG_RESOURCES_ENABLED));
        $this->assertSame(1, (int)get_config('mod_bookit', install_helper::CONFIG_CHECKLIST_ENABLED));

        $roomsbefore = $DB->count_records('bookit_room');
        $second = demo_data_helper::generate();
        $this->assertSame('refused', $second['status']);
        $this->assertSame($roomsbefore, $DB->count_records('bookit_room'));
    }

    /**
     * Generate does not require prior role import.
     *
     * @return void
     */
    public function test_generate_without_role_install(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        install_helper::ensure_fresh_install_baseline();
        $report = demo_data_helper::generate();
        $this->assertSame('success', $report['status']);
    }

    /**
     * Reset returns to baseline and disables modules; idempotent on baseline.
     *
     * @return void
     */
    public function test_reset_to_baseline_and_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        install_helper::ensure_fresh_install_baseline();
        demo_data_helper::generate();

        $reset = demo_data_helper::reset();
        $this->assertSame('success', $reset['status']);
        $this->assertTrue(demo_data_helper::is_baseline_only());
        $this->assertSame(1, $DB->count_records('bookit_room'));
        $this->assertSame(install_helper::DEFAULT_ROOM_NAME, $DB->get_field('bookit_room', 'name', []));
        $this->assertSame(0, (int)get_config('mod_bookit', install_helper::CONFIG_RESOURCES_ENABLED));
        $this->assertSame(0, (int)get_config('mod_bookit', install_helper::CONFIG_CHECKLIST_ENABLED));

        $again = demo_data_helper::reset();
        $this->assertSame('success', $again['status']);
        $this->assertTrue(demo_data_helper::is_baseline_only());
    }
}
