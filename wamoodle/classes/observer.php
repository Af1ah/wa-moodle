<?php

namespace message_wamoodle;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function course_module_created(\core\event\course_module_created $event) {
        (new \message_wamoodle\local\group_notification_service())->handle_course_module_created($event);
    }

    public static function course_module_updated(\core\event\course_module_updated $event) {
        (new \message_wamoodle\local\group_notification_service())->handle_course_module_updated($event);
    }
}
