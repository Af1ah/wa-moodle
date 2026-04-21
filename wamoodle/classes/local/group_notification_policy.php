<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class group_notification_policy {
    public const REMINDER_ASSIGN_7D = 'assign_7d';
    public const REMINDER_ASSIGN_24H = 'assign_24h';
    public const REMINDER_QUIZ_3H = 'quiz_3h';

    /**
     * @return array<int, array{code: string, offset: int, upperbound: int}>
     */
    public static function get_reminders(string $modname): array {
        if ($modname === 'assign') {
            return [
                [
                    'code' => self::REMINDER_ASSIGN_7D,
                    'offset' => WEEKSECS,
                    'upperbound' => DAYSECS,
                ],
                [
                    'code' => self::REMINDER_ASSIGN_24H,
                    'offset' => DAYSECS,
                    'upperbound' => 0,
                ],
            ];
        }

        if ($modname === 'quiz') {
            return [
                [
                    'code' => self::REMINDER_QUIZ_3H,
                    'offset' => 3 * HOURSECS,
                    'upperbound' => 0,
                ],
            ];
        }

        return [];
    }

    public static function supports_module(string $modname): bool {
        return in_array($modname, ['assign', 'quiz'], true);
    }

    public static function get_schedule_field(string $modname): ?string {
        if ($modname === 'assign') {
            return 'duedate';
        }

        if ($modname === 'quiz') {
            return 'timeopen';
        }

        return null;
    }

    public static function get_schedule_label(string $modname): string {
        return $modname === 'quiz'
            ? get_string('groupfield:opens', 'message_wamoodle')
            : get_string('groupfield:due', 'message_wamoodle');
    }

    public static function is_reminder_due(string $code, int $trackedtime, int $now): bool {
        if ($trackedtime <= 0 || $now >= $trackedtime) {
            return false;
        }

        foreach (self::get_reminders(self::get_modname_for_code($code)) as $reminder) {
            if ($reminder['code'] !== $code) {
                continue;
            }

            $windowstart = $trackedtime - $reminder['offset'];
            $windowend = $reminder['upperbound'] > 0 ? $trackedtime - $reminder['upperbound'] : $trackedtime;

            return $now >= $windowstart && $now < $windowend;
        }

        return false;
    }

    public static function get_modname_for_code(string $code): string {
        return str_starts_with($code, 'quiz_') ? 'quiz' : 'assign';
    }
}
