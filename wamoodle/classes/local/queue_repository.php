<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class queue_repository {
    public function enqueue(\stdClass $eventdata): int {
        global $DB;

        $now = time();
        $payload = [
            'subject' => (string)($eventdata->subject ?? ''),
            'fullmessage' => (string)($eventdata->fullmessage ?? ''),
            'smallmessage' => (string)($eventdata->smallmessage ?? ''),
            'contexturl' => (string)($eventdata->contexturl ?? ''),
            'component' => (string)($eventdata->component ?? ''),
            'name' => (string)($eventdata->name ?? ''),
            'userid' => (int)$eventdata->userto->id,
        ];

        $record = (object)[
            'userid' => (int)$eventdata->userto->id,
            'messageid' => !empty($eventdata->id) ? (string)$eventdata->id : null,
            'notificationtype' => trim(((string)($eventdata->component ?? 'core')) . ':' . ((string)($eventdata->name ?? 'notification')), ':'),
            'recipientnumber' => null,
            'payloadjson' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'lastresponse' => null,
            'nextruntime' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        return (int)$DB->insert_record('message_wamoodle_queue', $record);
    }

    public function get_queue_item(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('message_wamoodle_queue', ['id' => $id]);
    }

    public function mark_sent(int $id, string $recipientnumber, array $response): void {
        global $DB;

        $record = (object)[
            'id' => $id,
            'recipientnumber' => $recipientnumber,
            'status' => 'sent',
            'attempts' => $this->get_attempts($id) + 1,
            'lastresponse' => json_encode($response),
            'timemodified' => time(),
        ];

        $DB->update_record('message_wamoodle_queue', $record);
    }

    public function mark_skipped(int $id, ?string $recipientnumber, string $message): void {
        global $DB;

        $record = (object)[
            'id' => $id,
            'recipientnumber' => $recipientnumber,
            'status' => 'skipped',
            'attempts' => $this->get_attempts($id) + 1,
            'lastresponse' => $message,
            'timemodified' => time(),
        ];

        $DB->update_record('message_wamoodle_queue', $record);
    }

    public function mark_failed(int $id, string $message): void {
        global $DB;

        $record = (object)[
            'id' => $id,
            'status' => 'failed',
            'attempts' => $this->get_attempts($id) + 1,
            'lastresponse' => $message,
            'timemodified' => time(),
        ];

        $DB->update_record('message_wamoodle_queue', $record);
    }

    public function mark_retry(int $id, string $message, int $nextruntime): void {
        global $DB;

        $record = (object)[
            'id' => $id,
            'status' => 'pending',
            'attempts' => $this->get_attempts($id) + 1,
            'lastresponse' => $message,
            'nextruntime' => $nextruntime,
            'timemodified' => time(),
        ];

        $DB->update_record('message_wamoodle_queue', $record);
    }

    public function add_log(int $queueid, int $userid, string $status, ?string $requestbody, ?string $responsebody, ?string $errorcode = null, ?string $errormessage = null): void {
        global $DB;

        $DB->insert_record('message_wamoodle_log', (object)[
            'queueid' => $queueid,
            'userid' => $userid,
            'status' => $status,
            'requestbody' => $requestbody,
            'responsebody' => $responsebody,
            'errorcode' => $errorcode,
            'errormessage' => $errormessage,
            'timecreated' => time(),
        ]);
    }

    public function get_recent_problem_items(int $limit = 20): array {
        global $DB;

        $sql = "SELECT *
                  FROM {message_wamoodle_queue}
                 WHERE status IN ('failed', 'skipped')
              ORDER BY timemodified DESC";

        return $DB->get_records_sql($sql, [], 0, $limit);
    }

    private function get_attempts(int $id): int {
        global $DB;
        return (int)$DB->get_field('message_wamoodle_queue', 'attempts', ['id' => $id], MUST_EXIST);
    }
}
