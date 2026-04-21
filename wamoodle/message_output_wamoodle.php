<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/message/output/lib.php');

use message_wamoodle\local\config;
use message_wamoodle\local\notification_policy;
use message_wamoodle\local\queue_repository;
use message_wamoodle\local\task_scheduler;

class message_output_wamoodle extends message_output {
    public function is_system_configured(): bool {
        return config::is_configured() && config::is_personal_notifications_enabled();
    }

    public function send_message($eventdata): bool {
        if (!config::is_enabled() || !config::is_personal_notifications_enabled()) {
            return true;
        }

        if (empty($eventdata->userto) || empty($eventdata->userto->id) || empty($eventdata->notification)) {
            return true;
        }

        $component = (string)($eventdata->component ?? '');
        $name = (string)($eventdata->name ?? '');

        $queue = new queue_repository();
        $queueid = $queue->enqueue($eventdata);
        task_scheduler::queue_processing_task($queueid);

        return true;
    }

    public function get_default_messaging_settings() {
        if (!config::is_personal_notifications_enabled()) {
            return MESSAGE_DISALLOWED;
        }

        return MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED;
    }

    public function config_form($preferences) {
        return null;
    }

    public function process_form($form, &$preferences): bool {
        return true;
    }

    public function load_data(&$preferences, $userid): void {
    }
}
