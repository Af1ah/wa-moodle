<?php
namespace message_wamoodle\task;

defined('MOODLE_INTERNAL') || die();

final class scan_group_reminders_task extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task:scangroupreminders', 'message_wamoodle');
    }

    public function execute(): void {
        (new \message_wamoodle\local\group_notification_service())->queue_due_reminders();
    }
}
