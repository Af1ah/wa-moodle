<?php
namespace message_wamoodle\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use message_wamoodle\local\phone_resolver;
use message_wamoodle\local\sender_session_service;

final class send_test_message extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'number' => new external_value(PARAM_RAW_TRIMMED, 'Recipient phone number'),
            'message' => new external_value(PARAM_RAW_TRIMMED, 'Test message'),
        ]);
    }

    public static function execute(string $number, string $message): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'number' => $number,
            'message' => $message,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $recipient = phone_resolver::normalize($params['number']);
        if ($recipient === null) {
            throw new \invalid_parameter_exception('Recipient number is invalid');
        }

        $response = (new sender_session_service())->send_text($recipient, $params['message']);

        return [
            'ok' => !empty($response['key']['id']) || !empty($response['messageId']),
            'messageid' => (string)($response['key']['id'] ?? $response['messageId'] ?? ''),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether the test message succeeded'),
            'messageid' => new external_value(PARAM_TEXT, 'Backend message ID'),
        ]);
    }
}
