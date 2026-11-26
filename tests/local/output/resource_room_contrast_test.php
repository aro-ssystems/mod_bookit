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
 * Contrast of room shortnames on a resource row.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\output;

use advanced_testcase;
use mod_bookit\local\entity\resource\bookit_resource;
use mod_bookit\local\entity\resource\bookit_resource_category;
use mod_bookit\local\manager\resource_manager;
use mod_bookit\local\persistent\room;
use mod_bookit\output\resource_item_card;

/**
 * Contrast of room shortnames on a resource row.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\output\resource_item_card
 */
final class resource_room_contrast_test extends advanced_testcase {
    /**
     * An empty room colour uses dark text. A black room colour uses light text.
     */
    public function test_room_badge_textclass_follows_background(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $lightid = $this->create_room('Light room', 'DFLT', '');
        $darkid = $this->create_room('Dark room', 'DARK1', '#000000');

        $light = $this->export_room_badge($lightid);
        $dark = $this->export_room_badge($darkid);

        $this->assertSame('text-dark', $light['textclass']);
        $this->assertSame('DFLT', $light['shortname']);
        $this->assertSame('text-light', $dark['textclass']);
    }

    /**
     * Create a room and return its id.
     *
     * @param string $name Room name.
     * @param string $shortname Room shortname.
     * @param string $eventcolor Room colour.
     * @return int
     */
    private function create_room(string $name, string $shortname, string $eventcolor): int {
        $room = new room(0, (object) [
            'name' => $name,
            'shortname' => $shortname,
            'description' => '',
            'location' => '',
            'eventcolor' => $eventcolor,
            'active' => 1,
            'roommode' => room::MODE_FREE,
            'seats' => 10,
            'preventoverlap' => room::OVERLAPPING_ALLOW_NONE,
        ]);
        $room->create();
        return (int) $room->get('id');
    }

    /**
     * Export the first room badge for a resource assigned to one room.
     *
     * @param int $roomid Room id.
     * @return array
     */
    private function export_room_badge(int $roomid): array {
        global $PAGE;

        $category = new bookit_resource_category(null, 'Contrast ' . $roomid, null, 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);
        $resource = new bookit_resource(
            null,
            'Contrast resource ' . $roomid,
            null,
            $categoryid,
            1,
            false,
            0,
            true,
            [$roomid],
            0,
            0,
            2
        );
        resource_manager::save_resource($resource, 2);
        $saved = resource_manager::get_all_resources($categoryid, true)[0];
        $card = new resource_item_card($saved, 2);
        $data = $card->export_for_template($PAGE->get_renderer('core'));
        $this->assertNotEmpty($data->roomnames);
        return $data->roomnames[0];
    }
}
