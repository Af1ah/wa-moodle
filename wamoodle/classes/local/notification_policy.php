<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class notification_policy {
    /**
     * Default notifications (e.g. assignments, quizzes) that are enabled for
     * WhatsApp by default when the plugin settings are synchronized.
     * This no longer restricts what can be sent; Moodle preferences handle UI logic.
     */
    private const ALLOWED_PROVIDERS = [
        'mod_assign' => ['*'],
        'mod_feedback' => ['*'],
        'mod_forum' => ['*'],
        'mod_lesson' => ['*'],
        'mod_quiz' => ['*'],
        'enrol_manual' => ['expiry_notification'],
        'enrol_self' => ['expiry_notification'],
        'moodle' => [
            'badgerecipientnotice',
            'badgecreatornotice',
            'competencyplancomment',
            'competencyusercompcomment',
            'coursecompleted',
            'coursecontentupdated',
            'enrolcoursewelcomemessage',
            'gradenotifications',
            'insights',
            'newlogin',
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
