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
 * Unit tests for examcourse_manager.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\manager;

use advanced_testcase;
use mod_bookit\local\install_helper;

/**
 * Unit tests for examcourse_manager.
 *
 * @package     mod_bookit
 * @category    test
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_bookit\local\manager\examcourse_manager
 */
final class examcourse_manager_test extends advanced_testcase {
    /**
     * Ensure mapping table exists for Fresh-Install-Only schema on older test DBs.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('bookit_examcourse_map')) {
            $table = new \xmldb_table('bookit_examcourse_map');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('purpose', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('institutionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('semester', XMLDB_TYPE_INTEGER, '8', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('purpose_inst_sem_uk', XMLDB_INDEX_UNIQUE, ['purpose', 'institutionid', 'semester']);
            $dbman->create_table($table);
        }
        install_helper::ensure_examcourse_defaults();
        set_config(install_helper::CONFIG_RESOURCES_ENABLED, 1, 'mod_bookit');
    }

    /**
     * Resolve precedence: semester+institution → institution-wide → site default.
     */
    public function test_resolve_category_precedence(): void {
        $sitecat = $this->getDataGenerator()->create_category(['name' => 'Site default']);
        $instcat = $this->getDataGenerator()->create_category(['name' => 'Institution override']);
        $semcat = $this->getDataGenerator()->create_category(['name' => 'Semester override']);

        set_config(
            install_helper::CONFIG_EXAMCOURSE_TEMPLATE_CATEGORYID,
            $sitecat->id,
            'mod_bookit'
        );

        $institutionid = 7;
        $semester = 20261;

        $this->assertSame(
            (int)$sitecat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, $institutionid, $semester)
        );

        examcourse_manager::upsert_mapping(
            examcourse_manager::PURPOSE_TEMPLATE,
            $institutionid,
            examcourse_manager::SEMESTER_INSTITUTION_WIDE,
            (int)$instcat->id
        );
        $this->assertSame(
            (int)$instcat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, $institutionid, $semester)
        );

        examcourse_manager::upsert_mapping(
            examcourse_manager::PURPOSE_TEMPLATE,
            $institutionid,
            $semester,
            (int)$semcat->id
        );
        $this->assertSame(
            (int)$semcat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, $institutionid, $semester)
        );

        // Other institution falls back to site default.
        $this->assertSame(
            (int)$sitecat->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TEMPLATE, 99, $semester)
        );
    }

    /**
     * Target purpose uses its own site default config key.
     */
    public function test_resolve_target_site_default(): void {
        $target = $this->getDataGenerator()->create_category();
        set_config(install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID, $target->id, 'mod_bookit');
        $this->assertSame(
            (int)$target->id,
            examcourse_manager::resolve_category(examcourse_manager::PURPOSE_TARGET, 1, 0)
        );
    }

    /**
     * Autocreate skips when disabled or no template; creates once when enabled.
     */
    public function test_ensure_created_idempotent_and_gates(): void {
        global $DB;

        $this->setAdminUser();
        $templatecat = $this->getDataGenerator()->create_category();
        $targetcat = $this->getDataGenerator()->create_category();
        $template = $this->getDataGenerator()->create_course(['category' => $templatecat->id, 'fullname' => 'Template']);
        set_config(install_helper::CONFIG_EXAMCOURSE_TARGET_CATEGORYID, $targetcat->id, 'mod_bookit');
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 0, 'mod_bookit');

        $pic = $this->getDataGenerator()->create_user();
        $event = (object)[
            'id' => 0,
            'name' => 'Exam Event',
            'coursetemplate' => (int)$template->id,
            'refcourseid' => 0,
            'institutionid' => 1,
            'semester' => 20261,
            'personinchargeid' => (int)$pic->id,
            'otherexaminers' => '',
            'usercreated' => (int)$pic->id,
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
            'timemodified' => time(),
        ];
        // Persist a minimal event row for update_record in ensure_created.
        $event->id = (int)$DB->insert_record('bookit_event', (object)[
            'name' => $event->name,
            'semester' => $event->semester,
            'institutionid' => $event->institutionid,
            'starttime' => time() + DAYSECS,
            'endtime' => time() + DAYSECS + HOURSECS,
            'duration' => 60,
            'bookingstatus' => $event->bookingstatus,
            'personinchargeid' => $event->personinchargeid,
            'otherexaminers' => '',
            'coursetemplate' => $event->coursetemplate,
            'refcourseid' => 0,
            'usercreated' => $event->usercreated,
            'usermodified' => $event->usercreated,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $r1 = examcourse_manager::ensure_created_for_event($event);
        $this->assertFalse($r1['created']);
        $this->assertSame('autocreate_disabled', $r1['skipped']);

        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        $event = $DB->get_record('bookit_event', ['id' => $event->id], '*', MUST_EXIST);
        $r2 = examcourse_manager::ensure_created_for_event($event);
        $this->assertTrue($r2['created']);
        $this->assertGreaterThan(0, $r2['courseid']);
        $course = $DB->get_record('course', ['id' => $r2['courseid']], '*', MUST_EXIST);
        $this->assertSame(0, (int)$course->visible);
        $this->assertSame((int)$targetcat->id, (int)$course->category);

        $event = $DB->get_record('bookit_event', ['id' => $event->id], '*', MUST_EXIST);
        $this->assertSame($r2['courseid'], (int)$event->refcourseid);

        $r3 = examcourse_manager::ensure_created_for_event($event);
        $this->assertFalse($r3['created']);
        $this->assertSame('already_created', $r3['skipped']);
        $this->assertSame($r2['courseid'], $r3['courseid']);
    }

    /**
     * No template → skip create.
     */
    public function test_ensure_created_skips_without_template(): void {
        global $DB;
        set_config(install_helper::CONFIG_EXAMCOURSE_AUTOCREATE_ENABLED, 1, 'mod_bookit');
        $eventid = (int)$DB->insert_record('bookit_event', (object)[
            'name' => 'No template',
            'semester' => 20261,
            'institutionid' => 1,
            'starttime' => time() + DAYSECS,
            'endtime' => time() + DAYSECS + HOURSECS,
            'duration' => 60,
            'bookingstatus' => event_access_manager::BOOKINGSTATUS_CONFIRMED,
            'coursetemplate' => 0,
            'refcourseid' => 0,
            'usercreated' => 2,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $event = $DB->get_record('bookit_event', ['id' => $eventid], '*', MUST_EXIST);
        $r = examcourse_manager::ensure_created_for_event($event);
        $this->assertFalse($r['created']);
        $this->assertSame('no_template', $r['skipped']);
    }
}
