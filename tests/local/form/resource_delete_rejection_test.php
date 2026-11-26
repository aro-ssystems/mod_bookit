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
 * Tests for rejected deletes in the resource catalog forms.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\form;

use advanced_testcase;
use mod_bookit\local\entity\resource\bookit_resource;
use mod_bookit\local\entity\resource\bookit_resource_category;
use mod_bookit\local\form\resource\edit_resource_category_form;
use mod_bookit\local\form\resource\edit_resource_form;
use mod_bookit\local\manager\resource_manager;

/**
 * Rejected deletes come back as a notice and a redirect instead of an exception.
 *
 * @covers \mod_bookit\local\form\resource\edit_resource_form
 * @covers \mod_bookit\local\form\resource\edit_resource_category_form
 */
final class resource_delete_rejection_test extends advanced_testcase {
    /**
     * Create a category with one resource.
     *
     * @param bool $active Whether the resource is active
     * @return int[] [categoryid, resourceid]
     */
    private function create_category_with_resource(bool $active = true): array {
        $category = new bookit_resource_category(null, 'Cat', null, 0, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);
        $resource = new bookit_resource(null, 'Res', null, $categoryid, 1, false, 0, $active, null, 0, 0, 2);
        $resourceid = resource_manager::save_resource($resource, 2);
        return [$categoryid, $resourceid];
    }

    /**
     * Book a resource on a new event.
     *
     * @param int $resourceid Resource to book
     * @return void
     */
    private function book_resource(int $resourceid): void {
        global $DB;

        $eventid = (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'Event',
            'starttime' => time() + DAYSECS,
            'endtime' => time() + DAYSECS + HOURSECS,
            'bookingstatus' => 2,
            'usercreated' => 2,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('bookit_event_resource', (object)[
            'eventid' => $eventid,
            'resourceid' => $resourceid,
            'amount' => 1,
            'status' => 'requested',
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Add the session key the dynamic form expects.
     *
     * @param array $formdata Submitted values
     * @return array Values with sesskey
     */
    private function with_sesskey(array $formdata): array {
        $formdata['sesskey'] = sesskey();
        $_POST['sesskey'] = $formdata['sesskey'];
        return $formdata;
    }

    /**
     * Submit a delete request through the resource form.
     *
     * @param int $resourceid Resource id
     * @param int $categoryid Category id
     * @return array Form result
     */
    private function delete_resource_via_form(int $resourceid, int $categoryid): array {
        $form = new edit_resource_form(null, null, 'post', '', [], true, $this->with_sesskey([
            'id' => $resourceid,
            'action' => 'delete',
            'name' => 'Res',
            'categoryid' => $categoryid,
            'amount' => 1,
            'amountirrelevant' => 0,
            'active' => 1,
            '_qf__mod_bookit_local_form_resource_edit_resource_form' => 1,
        ]), true);
        $form->set_data_for_dynamic_submission();
        return $form->process_dynamic_submission();
    }

    /**
     * Submit a delete request through the category form.
     *
     * @param int $categoryid Category id
     * @return array Form result
     */
    private function delete_category_via_form(int $categoryid): array {
        $form = new edit_resource_category_form(null, null, 'post', '', [], true, $this->with_sesskey([
            'id' => $categoryid,
            'action' => 'delete',
            'name' => 'Cat',
            '_qf__mod_bookit_local_form_resource_edit_resource_category_form' => 1,
        ]), true);
        $form->set_data_for_dynamic_submission();
        return $form->process_dynamic_submission();
    }

    /**
     * Assert the rejection shape and the queued notice.
     *
     * @param array $result Form result
     * @param string $stringkey Expected message string key
     * @return void
     */
    private function assert_rejected(array $result, string $stringkey): void {
        $expected = get_string($stringkey, 'mod_bookit');
        $this->assertFalse($result['success']);
        $this->assertSame($expected, $result['message']);
        $this->assertStringEndsWith('/mod/bookit/admin/resources.php?id=resources', $result['redirecturl']);
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertContains($expected, $messages);
    }

    /**
     * A booked resource is not deleted; the form returns a notice and a redirect.
     *
     * @return void
     */
    public function test_booked_resource_delete_is_rejected_with_notice(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$categoryid, $resourceid] = $this->create_category_with_resource();
        $this->book_resource($resourceid);

        $result = $this->delete_resource_via_form($resourceid, $categoryid);

        $this->assert_rejected($result, 'resources:resource_has_bookings');
        $this->assertTrue($DB->record_exists('bookit_resource', ['id' => $resourceid]));
    }

    /**
     * A category with a resource is not deleted; the form returns a notice and a redirect.
     *
     * @return void
     */
    public function test_category_with_resource_delete_is_rejected_with_notice(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$categoryid] = $this->create_category_with_resource();

        $result = $this->delete_category_via_form($categoryid);

        $this->assert_rejected($result, 'resources:category_has_resources');
        $this->assertTrue($DB->record_exists('bookit_resource_category', ['id' => $categoryid]));
    }

    /**
     * A category whose only resource is inactive still counts as not empty.
     *
     * @return void
     */
    public function test_category_with_inactive_resource_delete_is_rejected_with_notice(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$categoryid] = $this->create_category_with_resource(false);

        $result = $this->delete_category_via_form($categoryid);

        $this->assert_rejected($result, 'resources:category_has_resources');
        $this->assertTrue($DB->record_exists('bookit_resource_category', ['id' => $categoryid]));
    }

    /**
     * An unbooked resource is deleted as before.
     *
     * @return void
     */
    public function test_unbooked_resource_delete_succeeds(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$categoryid, $resourceid] = $this->create_category_with_resource();

        $result = $this->delete_resource_via_form($resourceid, $categoryid);

        $this->assertSame('items', $result[0]['name']);
        $this->assertSame('delete', $result[0]['action']);
        $this->assertFalse($DB->record_exists('bookit_resource', ['id' => $resourceid]));
        $this->assertEmpty(\core\notification::fetch());
    }
}
