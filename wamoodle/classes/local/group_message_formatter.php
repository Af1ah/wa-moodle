<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class group_message_formatter {
    public static function format_created(\stdClass $snapshot): string {
        $heading = $snapshot->modname === 'quiz'
            ? get_string('groupmessage:newquiz', 'message_wamoodle')
            : get_string('groupmessage:newassignment', 'message_wamoodle');

        return self::build_message(
            $heading,
            $snapshot,
            [[group_notification_policy::get_schedule_label($snapshot->modname), self::format_time($snapshot->trackedtime)]]
        );
    }

    public static function format_reminder(\stdClass $snapshot, string $code): string {
        $heading = get_string('groupmessage:update', 'message_wamoodle');
        if ($code === group_notification_policy::REMINDER_ASSIGN_7D) {
            $heading = get_string('groupmessage:assign7d', 'message_wamoodle');
        } else if ($code === group_notification_policy::REMINDER_ASSIGN_24H) {
            $heading = get_string('groupmessage:assign24h', 'message_wamoodle');
        } else if ($code === group_notification_policy::REMINDER_QUIZ_3H) {
            $heading = get_string('groupmessage:quiz3h', 'message_wamoodle');
        }

        return self::build_message(
            $heading,
            $snapshot,
            [[group_notification_policy::get_schedule_label($snapshot->modname), self::format_time($snapshot->trackedtime)]]
        );
    }

    public static function format_schedule_update(\stdClass $snapshot, int $oldtrackedtime): string {
        $heading = $snapshot->modname === 'quiz'
            ? get_string('groupmessage:quizupdated', 'message_wamoodle')
            : get_string('groupmessage:assignmentupdated', 'message_wamoodle');

        return self::build_message(
            $heading,
            $snapshot,
            [
                [get_string('groupfield:was', 'message_wamoodle'), self::format_time($oldtrackedtime)],
                [get_string('groupfield:now', 'message_wamoodle'), self::format_time($snapshot->trackedtime)],
            ]
        );
    }

    /**
     * @param array<int, array{0: string, 1: string}> $extralines
     */
    private static function build_message(string $heading, \stdClass $snapshot, array $extralines): string {
        $lines = [
            '*' . $heading . '*',
            get_string('groupfield:course', 'message_wamoodle') . ': ' . $snapshot->courseshortname,
            get_string('groupfield:title', 'message_wamoodle') . ': ' . $snapshot->title,
        ];

        foreach ($extralines as [$label, $value]) {
            if ($value === '') {
                continue;
            }
            $lines[] = $label . ': ' . $value;
        }

        $lines[] = get_string('groupfield:link', 'message_wamoodle') . ': ' . $snapshot->url;

        return implode("\n", $lines);
    }

    private static function format_time(int $timestamp): string {
        if ($timestamp <= 0) {
            return get_string('groupfield:notset', 'message_wamoodle');
        }

        return userdate($timestamp);
    }
}
