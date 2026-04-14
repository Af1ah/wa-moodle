<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class config {
    public const PROFILE_FIELD_SHORTNAME = 'wamoodlewhatsapp';
    public const DEFAULT_PLUGIN_CODE = 'message_wamoodle';

    public static function is_enabled(): bool {
        return (bool)get_config('message_wamoodle', 'enable');
    }

    public static function get_backend_url(): string {
        $url = trim((string)get_config('message_wamoodle', 'plugbolt_base_url'));
        return $url !== '' ? $url : 'https://plugbolt.vercel.app';
    }

    public static function get_plugin_code(): string {
        return self::DEFAULT_PLUGIN_CODE;
    }

    public static function get_client_key(): string {
        return trim((string)get_config('message_wamoodle', 'plugbolt_client_key'));
    }

    public static function get_plugin_secret(): string {
        return trim((string)get_config('message_wamoodle', 'plugbolt_plugin_secret'));
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
            && self::get_backend_url() !== ''
            && self::get_client_key() !== ''
            && self::get_plugin_secret() !== '';
    }
}
