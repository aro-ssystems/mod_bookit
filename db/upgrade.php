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
 * Upgrade script.
 *
 * @package     mod_bookit
 * @copyright   2024 Melanie Treitinger, Ruhr-Universität Bochum <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script.
 *
 * @param int $oldversion
 * @return bool
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 */
function xmldb_bookit_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Set this to the SAME value you set in mod/bookit/version.php ($plugin->version).
    $newversion = 2025411305;

    if ($oldversion < $newversion) {
        $table = new xmldb_table('bookit_event');

        $old = new xmldb_field('department', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'semester');

        $new = new xmldb_field('institutionid', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'semester');

        if ($dbman->field_exists($table, $old) && !$dbman->field_exists($table, $new)) {
            $dbman->rename_field($table, $old, 'institutionid');
        }
        upgrade_mod_savepoint(true, $newversion, 'bookit');
    }

    // Add sortorder and active fields to resource tables.
    $newversion = 2025511306;

    if ($oldversion < $newversion) {
        // Add sortorder and active to bookit_resource_categories.
        $table = new xmldb_table('bookit_resource_categories');

        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add sortorder and active to bookit_resource.
        $table = new xmldb_table('bookit_resource');

        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'categoryid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, $newversion, 'bookit');
    }

    $newversion = 2025511307;

    if ($oldversion < $newversion) {
        $table = new xmldb_table('bookit_event_resource');

        // Rename quantity to amount if it still exists under old name.
        $oldfield = new xmldb_field('quantity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        if ($dbman->field_exists($table, $oldfield)) {
            $dbman->rename_field($table, $oldfield, 'amount');
        }

        // Migrate data from old table if it exists.
        $oldtable = new xmldb_table('bookit_event_resources');
        if ($dbman->table_exists($oldtable)) {
            $DB->execute("
                INSERT INTO {bookit_event_resource} (eventid, resourceid, amount, status, usermodified, timecreated, timemodified)
                SELECT eventid, resourceid, amount, 'requested', usermodified, timecreated, timemodified
                FROM {bookit_event_resources}
                ON CONFLICT (eventid, resourceid) DO NOTHING
            ");
            $dbman->drop_table($oldtable);
        }

        upgrade_mod_savepoint(true, $newversion, 'bookit');
    }

    $newversion = 2025511309;

    if ($oldversion < $newversion) {
        $table = new xmldb_table('bookit_notification_slots');

        // Drop the FK constraint on checklistitemid first (required before changing nullability).
        $key = new xmldb_key('checklistitemid', XMLDB_KEY_FOREIGN, ['checklistitemid'], 'bookit_checklist_item', ['id']);
        if ($dbman->find_key_name($table, $key)) {
            $dbman->drop_key($table, $key);
        }

        // Make checklistitemid nullable (resource checklist items set it to NULL).
        $field = new xmldb_field('checklistitemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        upgrade_mod_savepoint(true, $newversion, 'bookit');
    }

    $newversion = 2025511310;
    if ($oldversion < $newversion) {
        // Re-assign sortorders in bookit_resource_checklist alphabetically by resource name.
        global $DB;
        $sql = "SELECT rc.id, r.name
                FROM {bookit_resource_checklist} rc
                JOIN {bookit_resource} r ON r.id = rc.resourceid
                ORDER BY r.name ASC";
        $items = $DB->get_records_sql($sql);
        $sortorder = 0;
        foreach ($items as $item) {
            $DB->set_field('bookit_resource_checklist', 'sortorder', $sortorder, ['id' => $item->id]);
            $sortorder++;
        }

        upgrade_mod_savepoint(true, $newversion, 'bookit');
    }

    return true;
}
