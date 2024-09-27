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
 * Upgrade hook.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_tool_enrolprofile_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024091701) {

        // Define table tool_enrolprofile_presets to be created.
        $table = new xmldb_table('tool_enrolprofile_presets');

        // Adding fields to table tool_enrolprofile_presets.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categories', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('courses', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('tags', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_enrolprofile_presets.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for tool_enrolprofile_presets.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Enrolprofile savepoint reached.
        upgrade_plugin_savepoint(true, 2024091701, 'tool', 'enrolprofile');
    }

    if ($oldversion < 2024091706) {

        $table = new xmldb_table('tool_enrolprofile_presets');

        $field = new xmldb_field('categories', XMLDB_TYPE_TEXT);
        $dbman->rename_field($table, $field, 'category');

        $field = new xmldb_field('courses', XMLDB_TYPE_TEXT);
        $dbman->rename_field($table, $field, 'course');

        $field = new xmldb_field('tags', XMLDB_TYPE_TEXT);
        $dbman->rename_field($table, $field, 'tag');

        upgrade_plugin_savepoint(true, 2024091706, 'tool', 'enrolprofile');
    }

    return true;
}
