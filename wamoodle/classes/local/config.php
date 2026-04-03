<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class config {
    public const PROFILE_FIELD_SHORTNAME = 'wamoodlewhatsapp';

    public static function is_enabled(): bool {
        return (bool)get_config('message_wamoodle', 'enable');
    }

    public static function get_evolution_url(): string {
        return rtrim((string)get_config('message_wamoodle', 'evolutionurl'), '/');
    }

    public static function get_evolution_api_key(): string {
        return (string)get_config('message_wamoodle', 'evolutionapikey');
    }

    public static function get_sender_session_id(): string {
        return (string)get_config('message_wamoodle', 'sendersessionid');
    }

    public static function get_default_country_code(): string {
        return preg_replace('/\D+/', '', (string)get_config('message_wamoodle', 'defaultcountrycode')) ?? '';
    }

    public static function get_mobile_field(): string {
        $field = clean_param((string)get_config('message_wamoodle', 'mobilefield'), PARAM_ALPHANUMEXT);
        return $field !== '' ? $field : 'phone2';
    }

    public static function is_configured(): bool {
        return self::is_enabled()
            && self::get_evolution_url() !== ''
            && self::get_evolution_api_key() !== ''
            && self::get_sender_session_id() !== '';
    }
}
