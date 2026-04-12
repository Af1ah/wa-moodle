<?php
namespace message_wamoodle\task;

defined('MOODLE_INTERNAL') || die();

use message_wamoodle\local\config;
use message_wamoodle\local\message_formatter;
use message_wamoodle\local\notification_policy;
use message_wamoodle\local\phone_resolver;
use message_wamoodle\local\queue_repository;
use message_wamoodle\local\sender_session_service;
use message_wamoodle\local\task_scheduler;

final class process_notification_task extends \core\task\adhoc_task {
    private const MAX_ATTEMPTS = 5;

    public function get_name(): string {
        return get_string('taskname', 'message_wamoodle');
    }

    public function execute(): void {
        $data = (object)$this->get_custom_data();
        if (empty($data->queueid)) {
            return;
        }

        $queue = new queue_repository();
        $item = $queue->get_queue_item((int)$data->queueid);

        if (!$item || $item->status !== 'pending' || $item->nextruntime > time()) {
            return;
        }

        $payload = json_decode($item->payloadjson, true) ?? [];
        $userid = (int)$item->userid;

        try {
            $recipientnumber = phone_resolver::resolve_for_user($userid);
            if ($recipientnumber === null) {
                $queue->mark_skipped((int)$item->id, null, get_string('error:missingrecipient', 'message_wamoodle'));
                $queue->add_log((int)$item->id, $userid, 'skipped', null, null, 'missing_recipient', get_string('error:missingrecipient', 'message_wamoodle'));
                return;
            }

            $message = message_formatter::from_event((object)$payload);
            $text = message_formatter::as_whatsapp_text($message);
            $response = (new sender_session_service())->send_text($recipientnumber, $text, 'queue-' . (int)$item->id);
            $queue->mark_sent((int)$item->id, $recipientnumber, $response);
            $queue->add_log((int)$item->id, $userid, 'sent', json_encode([
                'recipient' => $recipientnumber,
                'text' => $text,
                'notificationtype' => (string)$item->notificationtype,
                'sender' => config::get_sender_session_id(),
            ]), json_encode($response));
        } catch (\Throwable $exception) {
            $attempts = (int)$item->attempts + 1;
            $retryable = $attempts < self::MAX_ATTEMPTS;
            $message = $exception->getMessage();

            if ($retryable) {
                $nextruntime = time() + ($attempts * 300);
                $queue->mark_retry((int)$item->id, $message, $nextruntime);
                $queue->add_log((int)$item->id, $userid, 'pending', null, null, 'retry_scheduled', $message);
                task_scheduler::queue_processing_task((int)$item->id, $nextruntime);
                return;
            }

            $queue->mark_failed((int)$item->id, $message);
            $queue->add_log((int)$item->id, $userid, 'failed', null, null, 'delivery_failed', $message);
        }
    }
}
