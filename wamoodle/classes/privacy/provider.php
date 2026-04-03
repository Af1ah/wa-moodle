<?php
namespace message_wamoodle\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use message_wamoodle\local\config;
use message_wamoodle\local\profile_field_manager;

final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('message_wamoodle_queue', [
            'userid' => 'privacy:metadata:queue:userid',
            'messageid' => 'privacy:metadata:queue:messageid',
            'notificationtype' => 'privacy:metadata:queue:notificationtype',
            'payloadjson' => 'privacy:metadata:queue:payloadjson',
            'recipientnumber' => 'privacy:metadata:queue:recipientnumber',
            'status' => 'privacy:metadata:queue:status',
        ], 'privacy:metadata:queue');
        $collection->add_database_table('message_wamoodle_log', [
            'queueid' => 'privacy:metadata:log:queueid',
            'userid' => 'privacy:metadata:log:userid',
            'requestbody' => 'privacy:metadata:log:requestbody',
            'responsebody' => 'privacy:metadata:log:responsebody',
            'errorcode' => 'privacy:metadata:log:errorcode',
            'errormessage' => 'privacy:metadata:log:errormessage',
        ], 'privacy:metadata:log');
        $collection->add_external_location_link('wa_moodle_backend', [], 'privacy:metadata:external');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $hasdata = $DB->record_exists('message_wamoodle_queue', ['userid' => $userid])
            || $DB->record_exists('message_wamoodle_log', ['userid' => $userid])
            || profile_field_manager::get_value_for_user($userid) !== null;

        if ($hasdata) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }

            $queued = array_values($DB->get_records('message_wamoodle_queue', ['userid' => $userid]));
            $logs = array_values($DB->get_records('message_wamoodle_log', ['userid' => $userid]));
            $profilefield = profile_field_manager::get_value_for_user($userid);

            $data = (object)[
                'profilefieldshortname' => config::PROFILE_FIELD_SHORTNAME,
                'profilefieldvalue' => $profilefield,
                'queue' => $queued,
                'logs' => $logs,
            ];

            writer::with_context($context)->export_data([get_string('pluginname', 'message_wamoodle')], $data);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $DB->delete_records('message_wamoodle_queue', ['userid' => $userid]);
        $DB->delete_records('message_wamoodle_log', ['userid' => $userid]);
        profile_field_manager::delete_value_for_user($userid);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('message_wamoodle_queue', []);
        $DB->delete_records('message_wamoodle_log', []);
    }
}
