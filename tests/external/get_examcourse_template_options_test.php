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
 * Tests for the web service that lists exam course templates for a faculty and term.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\external;

use advanced_testcase;
use context_module;
use core_external\external_api;
use invalid_parameter_exception;
use mod_bookit\local\install_helper;
use mod_bookit\local\manager\event_access_manager;
use mod_bookit\local\manager\examcourse_manager;
use required_capability_exception;

/**
 * Tests for the web service that lists exam course templates for a faculty and term.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\external\get_examcourse_template_options
 * @covers      \mod_bookit\local\manager\event_access_manager::require_booking_fields_editable
 * @covers      \mod_bookit\local\manager\examcourse_manager::get_template_basis
 */
final class get_examcourse_template_options_test extends advanced_testcase {
    /** @var context_module Booking activity context. */
    private context_module $context;

    /** @var int Course module id. */
    private int $cmid;

    /** @var \stdClass Template in the site default category. */
    private \stdClass $defaulttemplate;

    /** @var int Faculty with its own template category. */
    private int $facultyid;

    /** @var \stdClass Template of that faculty. */
    private \stdClass $facultytemplate;

    /** @var \stdClass Booking person with the template selection right. */
    private \stdClass $booker;

    /** @var \stdClass Second booking person, not involved in the booker's bookings. */
    private \stdClass $otherbooker;

    /** @var \stdClass Service team user. */
    private \stdClass $serviceteam;

    /** @var int Booking person role id. */
    private int $bookerroleid;

    /**
     * Prepare template categories, a faculty mapping, the activity and the users.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);
        install_helper::ensure_examcourse_defaults();

        $defaultcat = $this->getDataGenerator()->create_category();
        $facultycat = $this->getDataGenerator()->create_category();
        $this->defaulttemplate = $this->getDataGenerator()->create_course(
            ['category' => $defaultcat->id, 'fullname' => 'Default template']
        );
        $this->facultytemplate = $this->getDataGenerator()->create_course(
            ['category' => $facultycat->id, 'fullname' => 'Faculty template']
        );
        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 1, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID, $defaultcat->id, 'mod_bookit');

        $this->facultyid = (int)$DB->insert_record('bookit_institution', (object)[
            'name' => 'Faculty with templates',
            'active' => 1,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        examcourse_manager::upsert_mapping(
            examcourse_manager::PURPOSE_TEMPLATE,
            $this->facultyid,
            examcourse_manager::SEMESTER_INSTITUTION_WIDE,
            (int)$facultycat->id
        );

        $course = $this->getDataGenerator()->create_course();
        $bookit = $this->getDataGenerator()->create_module('bookit', ['course' => $course->id]);
        $this->cmid = (int)$bookit->cmid;
        $this->context = context_module::instance($bookit->cmid);

        \update_capabilities('mod_bookit');
        $this->bookerroleid = \create_role('Template list booker', 'templatelistbooker', 'student');
        foreach (['view', 'addevent', 'viewownoverview', 'viewalldetailsofownevent', 'selectexamcoursetemplate'] as $cap) {
            \assign_capability('mod/bookit:' . $cap, CAP_ALLOW, $this->bookerroleid, $this->context->id, true);
        }
        $stroleid = \create_role('Template list service team', 'templatelistserviceteam', 'student');
        foreach (['view', 'addevent', 'editevent', 'viewalldetailsofevent', 'selectexamcoursetemplate'] as $cap) {
            \assign_capability('mod/bookit:' . $cap, CAP_ALLOW, $stroleid, $this->context->id, true);
        }

        $this->booker = $this->enrol_with_role($course->id, $this->bookerroleid);
        $this->otherbooker = $this->enrol_with_role($course->id, $this->bookerroleid);
        $this->serviceteam = $this->enrol_with_role($course->id, $stroleid);
        \accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Create a user, enrol them and give them a role in the activity.
     *
     * @param int $courseid
     * @param int $roleid
     * @return \stdClass
     */
    private function enrol_with_role(int $courseid, int $roleid): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid);
        \role_assign($roleid, $user->id, $this->context->id);
        return $user;
    }

    /**
     * Store a booking of the booking person.
     *
     * @param int $institutionid
     * @param int $status
     * @return int Booking id
     */
    private function create_booking(int $institutionid, int $status = event_access_manager::BOOKINGSTATUS_NEW): int {
        global $DB;
        $start = strtotime('+14 days 09:00');
        return (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'Template list booking',
            'semester' => 20262,
            'institutionid' => $institutionid,
            'starttime' => $start,
            'endtime' => $start + 7200,
            'duration' => 120,
            'roomid' => 0,
            'participantsamount' => 10,
            'compensationfordisadvantages' => '',
            'bookingstatus' => $status,
            'personinchargeid' => 0,
            'otherexaminers' => '',
            'coursetemplate' => 0,
            'notes' => '',
            'internalnotes' => '',
            'supportpersons' => '',
            'extratimebefore' => 0,
            'extratimeafter' => 0,
            'refcourseid' => 0,
            'usercreated' => (int)$this->booker->id,
            'usermodified' => (int)$this->booker->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Call the service as the given user and return the cleaned result.
     *
     * @param \stdClass $user
     * @param int $eventid
     * @param int $institutionid
     * @param int $semester
     * @return array
     */
    private function call(\stdClass $user, int $eventid, int $institutionid, int $semester = 0): array {
        $this->setUser($user);
        $result = get_examcourse_template_options::execute($this->cmid, $eventid, $institutionid, $semester);
        return external_api::clean_returnvalue(get_examcourse_template_options::execute_returns(), $result);
    }

    /**
     * Names of the offered templates.
     *
     * @param array $result
     * @return string[]
     */
    private function names(array $result): array {
        return array_column($result['options'], 'name');
    }

    /**
     * Without a mapping the templates of the site default category are offered.
     */
    public function test_default_category_without_mapping(): void {
        $result = $this->call($this->booker, 0, 1);
        $this->assertTrue($result['visible']);
        $this->assertSame(['Default template'], $this->names($result));
        $this->assertSame((int)$this->defaulttemplate->id, $result['options'][0]['id']);
    }

    /**
     * A faculty with its own category offers only its templates.
     */
    public function test_faculty_mapping_replaces_default(): void {
        $result = $this->call($this->booker, 0, $this->facultyid);
        $this->assertTrue($result['visible']);
        $this->assertSame(['Faculty template'], $this->names($result));
    }

    /**
     * A mapping for faculty and term wins over the faculty-wide mapping.
     */
    public function test_term_mapping_wins_over_faculty_mapping(): void {
        $termcat = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $termcat->id, 'fullname' => 'Term template']);
        examcourse_manager::upsert_mapping(examcourse_manager::PURPOSE_TEMPLATE, $this->facultyid, 20262, (int)$termcat->id);

        $this->assertSame(['Term template'], $this->names($this->call($this->booker, 0, $this->facultyid, 20262)));
        $this->assertSame(['Faculty template'], $this->names($this->call($this->booker, 0, $this->facultyid, 20271)));
    }

    /**
     * For an existing booking the faculty chosen in the form counts, not the stored one.
     */
    public function test_existing_booking_uses_chosen_faculty(): void {
        $eventid = $this->create_booking(1);
        $this->assertSame(['Faculty template'], $this->names($this->call($this->serviceteam, $eventid, $this->facultyid)));
        $this->assertSame(['Default template'], $this->names($this->call($this->booker, $eventid, 1)));
    }

    /**
     * The list equals the templates accepted on save for the same faculty and term.
     */
    public function test_list_matches_accepted_templates(): void {
        $basis = examcourse_manager::get_template_basis(null, (int)$this->booker->id, $this->facultyid, 0);
        $expected = array_keys(examcourse_manager::get_template_options($basis));
        $result = $this->call($this->booker, 0, $this->facultyid);
        $this->assertSame($expected, array_column($result['options'], 'id'));
    }

    /**
     * When exam course creation is switched off the field stays hidden and nothing is listed.
     */
    public function test_switched_off_feature_hides_field(): void {
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 0, 'mod_bookit');
        $result = $this->call($this->booker, 0, $this->facultyid);
        $this->assertFalse($result['visible']);
        $this->assertSame([], $result['options']);
    }

    /**
     * A category without visible courses hides the field without an error.
     */
    public function test_category_without_visible_courses_hides_field(): void {
        $hiddencat = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $hiddencat->id, 'fullname' => 'Hidden', 'visible' => 0]);
        examcourse_manager::upsert_mapping(
            examcourse_manager::PURPOSE_TEMPLATE,
            $this->facultyid,
            examcourse_manager::SEMESTER_INSTITUTION_WIDE,
            (int)$hiddencat->id
        );
        $result = $this->call($this->booker, 0, $this->facultyid);
        $this->assertFalse($result['visible']);
        $this->assertSame([], $result['options']);
    }

    /**
     * A mapping to a deleted category hides the field without an error.
     */
    public function test_deleted_category_hides_field(): void {
        $gonecat = $this->getDataGenerator()->create_category();
        examcourse_manager::upsert_mapping(
            examcourse_manager::PURPOSE_TEMPLATE,
            $this->facultyid,
            examcourse_manager::SEMESTER_INSTITUTION_WIDE,
            (int)$gonecat->id
        );
        \core_course_category::get($gonecat->id)->delete_full(false);

        $result = $this->call($this->booker, 0, $this->facultyid);
        $this->assertFalse($result['visible']);
        $this->assertSame([], $result['options']);
    }

    /**
     * Without the template selection right the call is refused.
     */
    public function test_refused_without_selection_right(): void {
        \assign_capability('mod/bookit:selectexamcoursetemplate', CAP_PROHIBIT, $this->bookerroleid, $this->context->id, true);
        \accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(required_capability_exception::class);
        $this->call($this->booker, 0, $this->facultyid);
    }

    /**
     * A new booking needs the right to add bookings.
     */
    public function test_new_booking_refused_without_add_right(): void {
        \assign_capability('mod/bookit:addevent', CAP_PROHIBIT, $this->bookerroleid, $this->context->id, true);
        \accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(required_capability_exception::class);
        $this->call($this->booker, 0, $this->facultyid);
    }

    /**
     * A booking the user may not edit is refused, even with the selection right.
     */
    public function test_foreign_booking_refused(): void {
        $eventid = $this->create_booking(1);
        $this->expectException(required_capability_exception::class);
        $this->call($this->otherbooker, $eventid, $this->facultyid);
    }

    /**
     * The booking person may no longer refresh once the booking is confirmed.
     */
    public function test_confirmed_booking_refused_for_booking_person(): void {
        $eventid = $this->create_booking(1, event_access_manager::BOOKINGSTATUS_CONFIRMED);
        $this->expectException(required_capability_exception::class);
        $this->call($this->booker, $eventid, $this->facultyid);
    }

    /**
     * An unknown booking id is refused.
     */
    public function test_unknown_booking_refused(): void {
        $this->expectException(invalid_parameter_exception::class);
        $this->call($this->serviceteam, 999999, $this->facultyid);
    }
}
