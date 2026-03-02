<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for Poodll LTI
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    enrol_poodlllti
 * @category   upgrade
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_poodlllti_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2023102503.01) {

        // Define table enrol_poodlllti_clients to be created.
        $table = new xmldb_table('enrol_poodlllti_clients');

        // Adding fields to table enrol_poodlllti_clients.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('schoolname', XMLDB_TYPE_CHAR, '1333', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ltiappregid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ltideploymentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table enrol_poodlllti_clients.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fkuserid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('fkcategoryid', XMLDB_KEY_FOREIGN, ['categoryid'], 'course_categories', ['id']);
        $table->add_key('fkltiappregid', XMLDB_KEY_FOREIGN, ['ltiappregid'], 'enrol_lti_app_registration', ['id']);
        $table->add_key('fkltideploymentid', XMLDB_KEY_FOREIGN, ['ltideploymentid'], 'enrol_lti_deployment', ['id']);

        // Conditionally launch create table for enrol_poodlllti_clients.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Poodlllti savepoint reached.
        upgrade_plugin_savepoint(true, 2023102503.01, 'enrol', 'poodlllti');
    }

    if ($oldversion < 2023102503.02) {

        // Define field platformtype to be added to enrol_poodlllti_clients.
        $table = new xmldb_table('enrol_poodlllti_clients');
        $field = new xmldb_field('platformtype', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'ltideploymentid');

        // Conditionally launch add field platformtype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('platformurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'platformtype');

        // Conditionally launch add field platformurl.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Poodlllti savepoint reached.
        upgrade_plugin_savepoint(true, 2023102503.02, 'enrol', 'poodlllti');
    }

    if ($oldversion < 2023102503.04) {

        // Define field deleted to be added to enrol_poodlllti_clients.
        $table = new xmldb_table('enrol_poodlllti_clients');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'platformurl');

        // Conditionally launch add field deleted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Poodlllti savepoint reached.
        upgrade_plugin_savepoint(true, 2023102503.04, 'enrol', 'poodlllti');
    }

    return true;
}
