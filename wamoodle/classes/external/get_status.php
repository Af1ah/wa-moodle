<?php
namespace message_wamoodle\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use message_wamoodle\local\sender_session_service;

final class get_status extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $params = self::validate_parameters(self::execute_parameters(), []);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $status = (new sender_session_service())->refresh();

        return [
            'ok' => $status->connectionstatus !== 'missing',
            'sessionstatus' => (string)($status->connectionstatus ?? ''),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether the backend is reachable'),
            'sessionstatus' => new external_value(PARAM_TEXT, 'Backend sender session status'),
        ]);
    }
}
