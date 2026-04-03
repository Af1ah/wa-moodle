<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class profile_field_manager {
    public static function ensure_profile_field_exists(): void {
        global $DB;

        $shortname = config::PROFILE_FIELD_SHORTNAME;
        if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
            return;
        }

        $category = $DB->get_record('user_info_category', ['name' => get_string('pluginname', 'message_wamoodle')]);
        if (!$category) {
            $category = (object)[
                'name' => get_string('pluginname', 'message_wamoodle'),
                'sortorder' => 1,
            ];
            $category->id = $DB->insert_record('user_info_category', $category);
        }

        $field = (object)[
            'shortname' => $shortname,
            'name' => get_string('profilefieldname', 'message_wamoodle'),
            'datatype' => 'text',
            'description' => get_string('profilefielddesc', 'message_wamoodle'),
            'descriptionformat' => FORMAT_PLAIN,
            'categoryid' => $category->id,
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => 2,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => FORMAT_PLAIN,
            'param1' => 30,
            'param2' => 30,
            'param3' => '',
            'param4' => '',
            'param5' => '',
        ];

        $DB->insert_record('user_info_field', $field);
    }

    public static function get_value_for_user(int $userid): ?string {
        global $DB;

        $sql = "SELECT d.data
                  FROM {user_info_data} d
                  JOIN {user_info_field} f ON f.id = d.fieldid
                 WHERE f.shortname = :shortname
                   AND d.userid = :userid";

        $value = $DB->get_field_sql($sql, [
            'shortname' => config::PROFILE_FIELD_SHORTNAME,
            'userid' => $userid,
        ]);

        return $value !== false ? (string)$value : null;
    }

    public static function delete_value_for_user(int $userid): void {
        global $DB;

        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => config::PROFILE_FIELD_SHORTNAME]);
        if (!$fieldid) {
            return;
        }

        $DB->delete_records('user_info_data', ['fieldid' => $fieldid, 'userid' => $userid]);
    }
}
