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
 * Unit tests for booking_status_cell output class.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\output;

use advanced_testcase;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\output\booking_status_cell;
use stdClass;

/**
 * Tests for booking_status_cell output.
 *
 * @covers \mod_bookit\output\booking_status_cell
 */
final class booking_status_cell_test extends advanced_testcase {
    /**
     * Service-team open-request rows export an editable booking cell without cancel option.
     */
    public function test_open_request_row_exports_editable_cell_without_cancel(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id]);
        $context = \context_module::instance($bookit->cmid);
        $serviceuser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($serviceuser->id, $course->id);

        \update_capabilities('mod_bookit');
        $roleid = \create_role('Bookit service status', 'bookitservicestatus', 'manager');
        \assign_capability('mod/bookit:managebasics', CAP_ALLOW, $roleid, $context->id, true);
        \role_assign($roleid, $serviceuser->id, $context->id);

        $this->setUser($serviceuser);

        $event = (object)[
            'id' => 42,
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_NEW,
        ];

        $cell = booking_status_cell::for_booking_overview_row(
            $event,
            $context,
            $serviceuser->id,
            $bookit->cmid,
            true,
            true,
            'openrequests'
        );

        global $PAGE;
        $output = $PAGE->get_renderer('mod_bookit');
        $data = $cell->export_for_template($output);

        $this->assertTrue($data->canedit);
        $this->assertTrue($data->showoptions);
        $this->assertSame(get_string('event_bookingstatus_0', 'mod_bookit'), $data->statustext);
        $optionvalues = array_map(static fn(array $option): int => (int)$option['value'], $data->options);
        $this->assertNotContains(event_access_manager::BOOKINGSTATUS_CANCELED, $optionvalues);
    }

    /**
     * Resource rows export readonly chips for users without manage capability.
     */
    public function test_resource_row_exports_readonly_chip(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $itemdata = (object)[
            'id' => 7,
            'eventid' => 3,
            'cmid' => 12,
            'status' => 'requested',
        ];

        $cell = booking_status_cell::for_resource_row($itemdata, false);
        global $PAGE;
        $output = $PAGE->get_renderer('mod_bookit');
        $data = $cell->export_for_template($output);

        $this->assertFalse($data->canedit);
        $this->assertFalse($data->showoptions);
        $this->assertSame('resource', $data->mode);
        $this->assertSame('update-status', $data->updateaction);
        $this->assertSame('open', $data->statusgroupkey);
        $this->assertSame(get_string('event_bookingstatus_0', 'mod_bookit'), $data->statustext);
    }

    /**
     * Resource rows export editable dropdowns for service team.
     */
    public function test_resource_row_exports_editable_dropdown_for_manage(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $itemdata = (object)[
            'id' => 8,
            'eventid' => 3,
            'cmid' => 12,
            'resourceid' => 5,
            'status' => 'confirmed',
        ];

        $cell = booking_status_cell::for_resource_row($itemdata, true);
        global $PAGE;
        $output = $PAGE->get_renderer('mod_bookit');
        $data = $cell->export_for_template($output);

        $this->assertTrue($data->canedit);
        $this->assertTrue($data->showoptions);
        $this->assertSame('update-status', $data->updateaction);
        $this->assertSame('confirmed', $data->statusgroupkey);
        $this->assertSame(get_string('event_bookingstatus_2', 'mod_bookit'), $data->statustext);
        $this->assertCount(4, $data->options);
    }

    /**
     * Resource status maps to open / confirmed / closed groups.
     */
    public function test_resource_row_status_group_keys(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        global $PAGE;
        $output = $PAGE->get_renderer('mod_bookit');

        $cases = [
            'requested' => 'open',
            'inprogress' => 'open',
            'confirmed' => 'confirmed',
            'rejected' => 'closed',
        ];

        foreach ($cases as $status => $expectedgroup) {
            $itemdata = (object)['id' => 1, 'status' => $status];
            $cell = booking_status_cell::for_resource_row($itemdata, false);
            $data = $cell->export_for_template($output);
            $this->assertSame($expectedgroup, $data->statusgroupkey, "Status {$status} group key");
        }
    }

    /**
     * Resource status colours match verified #30 / booking-status palette and stay distinct.
     */
    public function test_resource_row_status_colors_match_booking_palette(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        global $PAGE;
        $output = $PAGE->get_renderer('mod_bookit');

        $booking = \mod_bookit\local\manager\event_manager::get_booking_status_colors();
        $expected = [
            'requested' => $booking[event_access_manager::BOOKINGSTATUS_NEW],
            'inprogress' => $booking[event_access_manager::BOOKINGSTATUS_IN_PROGRESS],
            'confirmed' => $booking[event_access_manager::BOOKINGSTATUS_CONFIRMED],
            'rejected' => $booking[event_access_manager::BOOKINGSTATUS_REJECTED],
        ];

        $seen = [];
        foreach ($expected as $status => $colors) {
            $itemdata = (object)['id' => 1, 'status' => $status];
            $cell = booking_status_cell::for_resource_row($itemdata, false);
            $data = $cell->export_for_template($output);
            $this->assertStringContainsString('background-color:' . $colors['bg'], $data->statusstyle);
            $this->assertStringContainsString('color:' . $colors['fg'], $data->statusstyle);
            $seen[] = $colors['bg'];
        }
        $this->assertCount(4, array_unique($seen), 'Four resource statuses must use distinct background colours');
    }

    /**
     * Unknown resource status renders a neutral readable fallback (not blank).
     */
    public function test_resource_row_unknown_status_uses_neutral_fallback(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        global $PAGE;
        $output = $PAGE->get_renderer('mod_bookit');

        $itemdata = (object)['id' => 1, 'status' => 'not-a-real-status'];
        $cell = booking_status_cell::for_resource_row($itemdata, false);
        $data = $cell->export_for_template($output);

        $this->assertNotSame('', $data->statusstyle);
        $this->assertStringContainsString('background-color:#e2e3e5', $data->statusstyle);
        $this->assertStringContainsString('color:#212529', $data->statusstyle);
        $this->assertSame('bookit-resource-status-requested', $data->statusclass);
    }
}
