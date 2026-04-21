<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class notification_policy {
    /**
     * Default personal notifications enabled for WhatsApp when the admin sync runs.
     * Users can still change their own preferences in Moodle.
     */
    private const ALLOWED_PROVIDERS = [
        'mod_assign' => ['assign_overdue'],
        'mod_quiz' => [
            'attempt_grading_complete',
            'attempt_overdue',
            'confirmation',
        ],
        'moodle' => [
            'badgerecipientnotice',
            'badgecreatornotice',
            'coursecompleted',
            'gradenotifications',
        ],
    ];

    public static function is_supported(string $component, string $name): bool {
        if (!isset(self::ALLOWED_PROVIDERS[$component])) {
            return false;
        }

        $providers = self::ALLOWED_PROVIDERS[$component];
        return in_array('*', $providers, true) || in_array($name, $providers, true);
    }
}
