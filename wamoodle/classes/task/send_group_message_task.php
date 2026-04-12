<?php
namespace message_wamoodle\task;

defined('MOODLE_INTERNAL') || die();

use message_wamoodle\local\config;
use message_wamoodle\local\sender_session_service;

final class send_group_message_task extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('whatsappconnect', 'message_wamoodle') . ' - Send Message';
    }

    public function execute(): void {
        $data = (object)$this->get_custom_data();
        if (empty($data->groupjid) || empty($data->text)) {
            return;
        }

        try {
            $sender = new sender_session_service();
            $sender->send_text($data->groupjid, $data->text, 'group-' . hash('sha256', json_encode($data)));
        } catch (\Throwable $exception) {
            // Task will fail, Moodle logs it and it can be retried. Admin gets notified of failed tasks usually.
            debugging('Failed to send group message: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            throw $exception;
        }
    }
}
