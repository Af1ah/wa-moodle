<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_message_wamoodle_upgrade(int $oldversion): bool {
    global $DB;
    require_once(__DIR__ . '/../classes/local/default_preferences_manager.php');
    $dbman = $DB->get_manager();

    if ($oldversion < 2026033001) {
        if (!$DB->record_exists('message_processors', ['name' => 'wamoodle'])) {
            $provider = new stdClass();
            $provider->name = 'wamoodle';
            $DB->insert_record('message_processors', $provider);
        }

        $field = $DB->get_record('user_info_field', ['shortname' => 'wamoodlewhatsapp']);
        if ($field && (int)$field->param2 < 1) {
            $field->param2 = 30;
            $DB->update_record('user_info_field', $field);
        }

        upgrade_plugin_savepoint(true, 2026033001, 'message', 'wamoodle');
    }

    if ($oldversion < 2026033002) {
        $table = new xmldb_table('message_wamoodle_session');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('instancename', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
            $table->add_field('connectionstatus', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'missing');
            $table->add_field('ownerjid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('profilename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('phonenumber', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('qrcode', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('qrbase64', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('pairingcode', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('instancename_uix', XMLDB_INDEX_UNIQUE, ['instancename']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026033002, 'message', 'wamoodle');
    }

    if ($oldversion < 2026033004) {
        $table = new xmldb_table('message_wamoodle_session');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('instancename', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
            $table->add_field('connectionstatus', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'missing');
            $table->add_field('ownerjid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('profilename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('phonenumber', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('qrcode', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('qrbase64', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('pairingcode', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('instancename_uix', XMLDB_INDEX_UNIQUE, ['instancename']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026033004, 'message', 'wamoodle');
    }

    if ($oldversion < 2026033005) {
        \message_wamoodle\local\default_preferences_manager::sync_site_defaults();
        upgrade_plugin_savepoint(true, 2026033005, 'message', 'wamoodle');
    }

    return true;
}
