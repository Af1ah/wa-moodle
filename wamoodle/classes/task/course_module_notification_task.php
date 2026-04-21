<?php
namespace message_wamoodle\task;

defined('MOODLE_INTERNAL') || die();

use message_wamoodle\local\group_message_formatter;
use message_wamoodle\local\group_notification_service;
use message_wamoodle\local\sender_session_service;

class course_module_notification_task extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('task:coursegroupnotification', 'message_wamoodle');
    }

    public function execute(): void {
        $data = (object)$this->get_custom_data();
        if (empty($data->cmid) || empty($data->courseid) || empty($data->modname) || empty($data->type)) {
            return;
        }

        $service = new group_notification_service();
        $group = $service->get_connected_group((int)$data->courseid);
        if (!$group) {
            return;
        }

        $snapshot = $service->get_module_snapshot((int)$data->cmid, (string)$data->modname);
        if (!$snapshot || (int)$snapshot->courseid !== (int)$data->courseid) {
            return;
        }

        $text = '';
        if ((string)$data->type === 'created') {
            if (isset($data->trackedtime)) {
                $snapshot->trackedtime = (int)$data->trackedtime;
            }
            $text = group_message_formatter::format_created($snapshot);
        } else if ((string)$data->type === 'schedule_updated') {
            if (isset($data->newtrackedtime)) {
                $snapshot->trackedtime = (int)$data->newtrackedtime;
            }
            $text = group_message_formatter::format_schedule_update(
                $snapshot,
                (int)($data->oldtrackedtime ?? 0)
            );
        } else if ((string)$data->type === 'reminder') {
            if (!isset($data->trackedtime) || (int)$data->trackedtime !== (int)$snapshot->trackedtime) {
                return;
            }
            $text = group_message_formatter::format_reminder(
                $snapshot,
                (string)($data->remindercode ?? '')
            );
        }

        if ($text === '') {
            return;
        }

        $idempotency = 'group-course-' . hash('sha256', json_encode([
            'courseid' => (int)$data->courseid,
            'cmid' => (int)$data->cmid,
            'modname' => (string)$data->modname,
            'type' => (string)$data->type,
            'trackedtime' => (int)($data->trackedtime ?? $snapshot->trackedtime),
            'oldtrackedtime' => (int)($data->oldtrackedtime ?? 0),
            'newtrackedtime' => (int)($data->newtrackedtime ?? 0),
            'remindercode' => (string)($data->remindercode ?? ''),
        ]));

        (new sender_session_service())->send_text((string)$group->groupjid, $text, $idempotency);

        if ((string)$data->type === 'created') {
            $service->suppress_active_reminders((int)$data->cmid, (string)$data->modname, (int)$snapshot->trackedtime);
        }
    }
}
