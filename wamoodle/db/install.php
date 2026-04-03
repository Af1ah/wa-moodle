<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_message_wamoodle_install(): bool {
    global $DB;
    require_once(__DIR__ . '/../classes/local/default_preferences_manager.php');

    if (!$DB->record_exists('message_processors', ['name' => 'wamoodle'])) {
        $provider = new stdClass();
        $provider->name = 'wamoodle';
        $DB->insert_record('message_processors', $provider);
    }

    \message_wamoodle\local\profile_field_manager::ensure_profile_field_exists();
    \message_wamoodle\local\default_preferences_manager::sync_site_defaults();
    return true;
}
