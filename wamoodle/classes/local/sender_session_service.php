<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class sender_session_service {
    public function __construct(
        private readonly broker_client $client = new broker_client(),
        private readonly session_state_repository $repository = new session_state_repository(),
    ) {
    }

    public function refresh(): \stdClass {
        $instancename = config::get_sender_session_id();
        $response = $this->client->get_sender_status();
        $session = $response['session'] ?? [];
        return $this->repository->upsert($instancename, [
            'connectionstatus' => (string)($session['connectionStatus'] ?? 'missing'),
            'ownerjid' => $session['ownerJid'] ?? null,
            'profilename' => $session['profileName'] ?? null,
            'phonenumber' => $session['phoneNumber'] ?? null,
            'pairingcode' => null,
            'qrcode' => null,
            'qrbase64' => null,
            'lasterror' => null,
        ]);
    }

    public function send_text(string $number, string $text, ?string $idempotencykey = null): array {
        $session = $this->refresh();
        if ($session->connectionstatus !== 'open') {
            throw new \moodle_exception('error:sessionnotopen', 'message_wamoodle');
        }

        return $this->client->send_text_message(
            $number,
            $text,
            $idempotencykey ?? hash('sha256', config::get_sender_session_id() . '|' . $number . '|' . $text . '|' . microtime(true))
        );
    }

    public function send_buttons(string $number, array $payload, ?string $idempotencykey = null): array {
        $session = $this->refresh();
        if ($session->connectionstatus !== 'open') {
            throw new \moodle_exception('error:sessionnotopen', 'message_wamoodle');
        }

        return $this->client->send_button_message(
            $number,
            $payload,
            $idempotencykey ?? hash('sha256', config::get_sender_session_id() . '|' . $number . '|btn|' . microtime(true))
        );
    }

    public function get_local_state(): ?\stdClass {
        return $this->repository->get(config::get_sender_session_id());
    }
}
