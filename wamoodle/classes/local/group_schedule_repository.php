<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class group_schedule_repository {
    private const REMINDER_COLUMN_MAP = [
        group_notification_policy::REMINDER_ASSIGN_7D => 'assign7dsent',
        group_notification_policy::REMINDER_ASSIGN_24H => 'assign24hsent',
        group_notification_policy::REMINDER_QUIZ_3H => 'quiz3hsent',
    ];

    public function get_by_cmid(int $cmid): ?\stdClass {
        global $DB;

        return $DB->get_record('message_wamoodle_group_schedule', ['cmid' => $cmid]) ?: null;
    }

    public function sync_state(int $courseid, int $cmid, string $modname, int $trackedtime): \stdClass {
        global $DB;

        $existing = $this->get_by_cmid($cmid);
        $now = time();

        if (!$existing) {
            $record = (object)[
                'courseid' => $courseid,
                'cmid' => $cmid,
                'modname' => $modname,
                'trackedtime' => $trackedtime,
                'assign7dsent' => 0,
                'assign24hsent' => 0,
                'quiz3hsent' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = (int)$DB->insert_record('message_wamoodle_group_schedule', $record);
            return $record;
        }

        $record = (object)[
            'id' => $existing->id,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'modname' => $modname,
            'trackedtime' => $trackedtime,
            'timemodified' => $now,
        ];

        if ((int)$existing->trackedtime !== $trackedtime || (string)$existing->modname !== $modname) {
            $record->assign7dsent = 0;
            $record->assign24hsent = 0;
            $record->quiz3hsent = 0;
        }

        $DB->update_record('message_wamoodle_group_schedule', $record);

        return $DB->get_record('message_wamoodle_group_schedule', ['id' => $existing->id], '*', MUST_EXIST);
    }

    public function is_reminder_sent(\stdClass $state, string $code, int $trackedtime): bool {
        $column = self::REMINDER_COLUMN_MAP[$code] ?? null;
        if ($column === null) {
            return false;
        }

        return (int)($state->{$column} ?? 0) === $trackedtime && $trackedtime > 0;
    }

    public function mark_reminder_sent(int $cmid, string $code, int $trackedtime): void {
        global $DB;

        $column = self::REMINDER_COLUMN_MAP[$code] ?? null;
        if ($column === null) {
            return;
        }

        $state = $this->get_by_cmid($cmid);
        if (!$state) {
            return;
        }

        $record = (object)[
            'id' => $state->id,
            $column => $trackedtime,
            'timemodified' => time(),
        ];
        $DB->update_record('message_wamoodle_group_schedule', $record);
    }
}
