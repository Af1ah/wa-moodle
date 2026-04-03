<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class phone_resolver {
    public static function resolve_for_user(int $userid): ?string {
        global $DB;

        $mobilefield = config::get_mobile_field();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $mobilefield)) {
            throw new \moodle_exception('error:invalidmobilefield', 'message_wamoodle');
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id,' . $mobilefield, MUST_EXIST);
        $primary = $user->{$mobilefield} ?? '';
        $fallback = profile_field_manager::get_value_for_user($userid) ?? '';

        $normalized = self::normalize($primary);
        if ($normalized !== null) {
            return $normalized;
        }

        return self::normalize($fallback);
    }

    public static function normalize(?string $value): ?string {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === '') {
            return null;
        }

        $defaultcountrycode = config::get_default_country_code();
        if ($trimmed[0] !== '+' && $defaultcountrycode !== '' && !str_starts_with($digits, $defaultcountrycode)) {
            $digits = $defaultcountrycode . ltrim($digits, '0');
        }

        return $digits !== '' ? $digits : null;
    }
}
