<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class default_preferences_manager {
    public static function sync_site_defaults(): void {
        require_once($GLOBALS['CFG']->libdir . '/messagelib.php');

        $providers = get_message_providers();
        $defaults = get_message_output_default_preferences();

        foreach ($providers as $provider) {
            $component = (string)$provider->component;
            $name = (string)$provider->name;
            $preference = 'message_provider_' . $component . '_' . $name . '_enabled';
            $current = [];

            if (isset($defaults->{$preference}) && trim((string)$defaults->{$preference}) !== '') {
                $current = array_values(array_unique(array_filter(explode(',', (string)$defaults->{$preference}))));
            }

            $enabled = notification_policy::is_supported($component, $name);
            $haswamoodle = in_array('wamoodle', $current, true);

            if ($enabled && !$haswamoodle) {
                $current[] = 'wamoodle';
                set_config($preference, implode(',', $current), 'message');
                continue;
            }

            if (!$enabled && $haswamoodle) {
                $current = array_values(array_filter($current, static function(string $processor): bool {
                    return $processor !== 'wamoodle';
                }));
                set_config($preference, $current ? implode(',', $current) : 'none', 'message');
            }
        }
    }
}
