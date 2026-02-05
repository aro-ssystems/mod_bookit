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
 * Unit tests for resource_manager class.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use advanced_testcase;
use mod_bookit\local\entity\bookit_resource;
use mod_bookit\local\entity\bookit_resource_categories;

/**
 * Unit tests for resource_manager class.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\manager\resource_manager
 */
final class resource_manager_test extends advanced_testcase {
    /**
     * Test creating and retrieving a category.
     */
    public function test_create_and_get_category(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $name = 'Test Category';
        $description = 'Test category description';
        $sortorder = 1;
        $active = true;

        // Create category entity.
        $category = new bookit_resource_categories(
            null,
            $name,
            $description,
            $sortorder,
            $active,
            0,
            0,
            2
        );

        // Save via manager.
        $categoryid = resource_manager::save_category($category, 2);

        // Verify ID is returned.
        $this->assertNotEmpty($categoryid);
        $this->assertIsInt($categoryid);

        // Retrieve via manager.
        $retrieved = resource_manager::get_category($categoryid);

        // Verify retrieved data.
        $this->assertNotNull($retrieved);
        $this->assertEquals($categoryid, $retrieved->get_id());
        $this->assertEquals($name, $retrieved->get_name());
        $this->assertEquals($description, $retrieved->get_description());
        $this->assertEquals($sortorder, $retrieved->get_sortorder());
        $this->assertTrue($retrieved->is_active());

        // Verify database record.
        $record = $DB->get_record('bookit_resource_categories', ['id' => $categoryid]);
        $this->assertNotEmpty($record);
        $this->assertEquals($name, $record->name);
        $this->assertEquals(1, $record->active); // Boolean stored as int.
    }

    /**
     * Test get_all_categories with active filter.
     */
    public function test_get_all_categories_with_filter(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create active category.
        $activecat = new bookit_resource_categories(null, 'Active Cat', null, 0, true, 0, 0, 2);
        $activeid = resource_manager::save_category($activecat, 2);

        // Create inactive category.
        $inactivecat = new bookit_resource_categories(null, 'Inactive Cat', null, 1, false, 0, 0, 2);
        $inactiveid = resource_manager::save_category($inactivecat, 2);

        // Get all categories (including inactive).
        $allcategories = resource_manager::get_all_categories(false);
        $this->assertCount(2, $allcategories);

        // Get only active categories.
        $activecategories = resource_manager::get_all_categories(true);
        $this->assertCount(1, $activecategories);
        $this->assertEquals($activeid, $activecategories[0]->get_id());
    }

    /**
     * Test updating a category.
     */
    public function test_update_category(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create initial category.
        $category = new bookit_resource_categories(null, 'Original Name', 'Original Desc', 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);

        // Retrieve and modify.
        $retrieved = resource_manager::get_category($categoryid);
        $retrieved->set_name('Updated Name');
        $retrieved->set_description('Updated Description');
        $retrieved->set_active(false);

        // Save update.
        $updatedid = resource_manager::save_category($retrieved, 2);

        // Verify same ID.
        $this->assertEquals($categoryid, $updatedid);

        // Verify updates in database.
        $record = $DB->get_record('bookit_resource_categories', ['id' => $categoryid]);
        $this->assertEquals('Updated Name', $record->name);
        $this->assertEquals('Updated Description', $record->description);
        $this->assertEquals(0, $record->active);
    }

    /**
     * Test delete_category validates no resources exist.
     */
    public function test_delete_category_with_resources_throws_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create category.
        $category = new bookit_resource_categories(null, 'Test Cat', null, 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);

        // Create resource in category.
        $resource = new bookit_resource(null, 'Test Resource', null, $categoryid, 5, false, 0, true, 0, 0, 2);
        $resourceid = resource_manager::save_resource($resource, 2);

        // Try to delete category - should throw exception.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('category_has_resources');
        resource_manager::delete_category($categoryid);
    }

    /**
     * Test delete_category succeeds when no resources exist.
     */
    public function test_delete_category_without_resources_succeeds(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create category.
        $category = new bookit_resource_categories(null, 'Test Cat', null, 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);

        // Delete category.
        resource_manager::delete_category($categoryid);

        // Verify deleted from database.
        $exists = $DB->record_exists('bookit_resource_categories', ['id' => $categoryid]);
        $this->assertFalse($exists);
    }

    /**
     * Test creating and retrieving a resource.
     */
    public function test_create_and_get_resource(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create category first.
        $category = new bookit_resource_categories(null, 'Test Cat', null, 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);

        // Create resource.
        $name = 'Test Resource';
        $description = 'Test resource description';
        $amount = 10;
        $sortorder = 1;
        $active = true;

        $resource = new bookit_resource(
            null,
            $name,
            $description,
            $categoryid,
            $amount,
            false, // Amountirrelevant (not in DB, only in Entity).
            $sortorder,
            $active,
            0,
            0,
            2
        );

        // Save via manager.
        $resourceid = resource_manager::save_resource($resource, 2);

        // Verify ID is returned.
        $this->assertNotEmpty($resourceid);
        $this->assertIsInt($resourceid);

        // Retrieve via manager.
        $retrieved = resource_manager::get_resource_by_id($resourceid);

        // Verify retrieved data.
        $this->assertNotNull($retrieved);
        $this->assertEquals($resourceid, $retrieved->get_id());
        $this->assertEquals($name, $retrieved->get_name());
        $this->assertEquals($description, $retrieved->get_description());
        $this->assertEquals($categoryid, $retrieved->get_categoryid());
        $this->assertEquals($amount, $retrieved->get_amount());
        // Skip amountirrelevant check - not in database schema.
        $this->assertEquals($sortorder, $retrieved->get_sortorder());
        $this->assertTrue($retrieved->is_active());

        // Verify database record.
        $record = $DB->get_record('bookit_resource', ['id' => $resourceid]);
        $this->assertNotEmpty($record);
        $this->assertEquals($name, $record->name);
        $this->assertEquals($categoryid, $record->categoryid);
        $this->assertEquals($amount, $record->amount);
        // Skip amountirrelevant - column doesn't exist in schema.
        $this->assertEquals(1, $record->active);
    }

    /**
     * Test get_all_resources with category filter.
     */
    public function test_get_all_resources_with_category_filter(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create two categories.
        $cat1 = new bookit_resource_categories(null, 'Cat 1', null, 0, true, 0, 0, 2);
        $cat1id = resource_manager::save_category($cat1, 2);

        $cat2 = new bookit_resource_categories(null, 'Cat 2', null, 1, true, 0, 0, 2);
        $cat2id = resource_manager::save_category($cat2, 2);

        // Create resources in cat1.
        $res1 = new bookit_resource(null, 'Resource 1', null, $cat1id, 5, false, 0, true, 0, 0, 2);
        resource_manager::save_resource($res1, 2);

        $res2 = new bookit_resource(null, 'Resource 2', null, $cat1id, 3, false, 1, true, 0, 0, 2);
        resource_manager::save_resource($res2, 2);

        // Create resource in cat2.
        $res3 = new bookit_resource(null, 'Resource 3', null, $cat2id, 7, false, 0, true, 0, 0, 2);
        resource_manager::save_resource($res3, 2);

        // Get all resources (no filter).
        $allresources = resource_manager::get_all_resources(null, false);
        $this->assertCount(3, $allresources);

        // Get resources for cat1 only.
        $cat1resources = resource_manager::get_all_resources($cat1id, false);
        $this->assertCount(2, $cat1resources);

        // Get resources for cat2 only.
        $cat2resources = resource_manager::get_all_resources($cat2id, false);
        $this->assertCount(1, $cat2resources);
    }

    /**
     * Test get_all_resources with active filter.
     */
    public function test_get_all_resources_with_active_filter(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create category.
        $category = new bookit_resource_categories(null, 'Test Cat', null, 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);

        // Create active resource.
        $activeres = new bookit_resource(null, 'Active', null, $categoryid, 5, false, 0, true, 0, 0, 2);
        resource_manager::save_resource($activeres, 2);

        // Create inactive resource.
        $inactiveres = new bookit_resource(null, 'Inactive', null, $categoryid, 3, false, 1, false, 0, 0, 2);
        resource_manager::save_resource($inactiveres, 2);

        // Get all resources (including inactive).
        $allresources = resource_manager::get_all_resources($categoryid, false);
        $this->assertCount(2, $allresources);

        // Get only active resources.
        $activeresources = resource_manager::get_all_resources($categoryid, true);
        $this->assertCount(1, $activeresources);
        $this->assertEquals('Active', $activeresources[0]->get_name());
    }

    /**
     * Test delete_resource succeeds.
     */
    public function test_delete_resource_succeeds(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create category.
        $category = new bookit_resource_categories(null, 'Test Cat', null, 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);

        // Create resource.
        $resource = new bookit_resource(null, 'Test Resource', null, $categoryid, 5, false, 0, true, 0, 0, 2);
        $resourceid = resource_manager::save_resource($resource, 2);

        // Delete resource.
        resource_manager::delete_resource($resourceid);

        // Verify deleted from database.
        $exists = $DB->record_exists('bookit_resource', ['id' => $resourceid]);
        $this->assertFalse($exists);
    }

    /**
     * Test update_category_sortorder.
     */
    public function test_update_category_sortorder(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create categories.
        $cat1 = new bookit_resource_categories(null, 'Cat 1', null, 0, true, 0, 0, 2);
        $cat1id = resource_manager::save_category($cat1, 2);

        $cat2 = new bookit_resource_categories(null, 'Cat 2', null, 1, true, 0, 0, 2);
        $cat2id = resource_manager::save_category($cat2, 2);

        $cat3 = new bookit_resource_categories(null, 'Cat 3', null, 2, true, 0, 0, 2);
        $cat3id = resource_manager::save_category($cat3, 2);

        // Reorder: cat3 first, cat1 second, cat2 third.
        $neworder = [
            0 => $cat3id,
            1 => $cat1id,
            2 => $cat2id,
        ];

        resource_manager::update_category_sortorder($neworder);

        // Verify new sortorder in database.
        $cat1record = $DB->get_record('bookit_resource_categories', ['id' => $cat1id]);
        $this->assertEquals(1, $cat1record->sortorder);

        $cat2record = $DB->get_record('bookit_resource_categories', ['id' => $cat2id]);
        $this->assertEquals(2, $cat2record->sortorder);

        $cat3record = $DB->get_record('bookit_resource_categories', ['id' => $cat3id]);
        $this->assertEquals(0, $cat3record->sortorder);
    }

    /**
     * Test validation: category name required.
     */
    public function test_validate_category_name_required(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create category with empty name.
        $category = new bookit_resource_categories(null, '', null, 0, true, 0, 0, 2);

        // Expect exception when saving.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('category_name_required');
        resource_manager::save_category($category, 2);
    }

    /**
     * Test validation: resource name required.
     */
    public function test_validate_resource_name_required(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create category.
        $category = new bookit_resource_categories(null, 'Test Cat', null, 0, true, 0, 0, 2);
        $categoryid = resource_manager::save_category($category, 2);

        // Create resource with empty name.
        $resource = new bookit_resource(null, '', null, $categoryid, 5, false, 0, true, 0, 0, 2);

        // Expect exception when saving.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('resource_name_required');
        resource_manager::save_resource($resource, 2);
    }

    /**
     * Test validation: resource category must exist.
     */
    public function test_validate_resource_category_exists(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create resource with non-existent category.
        $resource = new bookit_resource(null, 'Test Resource', null, 99999, 5, false, 0, true, 0, 0, 2);

        // Expect exception when saving.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('resource_category_not_found');
        resource_manager::save_resource($resource, 2);
    }
}
