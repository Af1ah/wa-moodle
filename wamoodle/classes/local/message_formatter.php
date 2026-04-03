<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class message_formatter {
    public static function from_event(\stdClass $eventdata): array {
        $subject = trim((string)($eventdata->subject ?? 'Moodle notification'));
        $body = trim((string)($eventdata->fullmessage ?? $eventdata->smallmessage ?? ''));
        $url = trim((string)($eventdata->contexturl ?? ''));

        return [
            'subject' => $subject,
            'text' => $body !== '' ? $body : $subject,
            'url' => $url !== '' ? $url : null,
        ];
    }

    public static function as_whatsapp_text(array $message): string {
        return implode("\n\n", array_filter([
            $message['subject'] ?? '',
            $message['text'] ?? '',
            $message['url'] ?? '',
        ]));
    }
}
