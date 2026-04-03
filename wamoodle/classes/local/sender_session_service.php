<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class sender_session_service {
    public function __construct(
        private readonly evolution_client $client = new evolution_client(),
        private readonly session_state_repository $repository = new session_state_repository(),
    ) {
    }

    public function refresh(): \stdClass {
        $instancename = config::get_sender_session_id();
        $instance = $this->client->fetch_instance($instancename);
        $connection = $this->client->get_connection_state($instancename);

        if ($instance === null && $connection === null) {
            return $this->repository->upsert($instancename, [
                'connectionstatus' => 'missing',
                'ownerjid' => null,
                'profilename' => null,
                'phonenumber' => null,
                'qrcode' => null,
                'qrbase64' => null,
                'pairingcode' => null,
                'lasterror' => null,
            ]);
        }

        $status = $connection['instance']['state'] ?? $instance['connectionStatus'] ?? $instance['instance']['status'] ?? 'unknown';
        return $this->repository->upsert($instancename, [
            'connectionstatus' => (string)$status,
            'ownerjid' => $instance['ownerJid'] ?? $instance['instance']['owner'] ?? null,
            'profilename' => $instance['profileName'] ?? $instance['instance']['profileName'] ?? null,
            'phonenumber' => $instance['number'] ?? null,
            'lasterror' => null,
        ]);
    }

    public function start_qr_session(): \stdClass {
        $instancename = config::get_sender_session_id();
        $instance = $this->client->fetch_instance($instancename);
        $response = $instance === null
            ? $this->client->create_instance($instancename)
            : $this->client->connect_instance($instancename);

        return $this->repository->upsert($instancename, [
            'connectionstatus' => 'connecting',
            'qrcode' => $response['qrcode']['code'] ?? $response['code'] ?? null,
            'qrbase64' => $response['qrcode']['base64'] ?? null,
            'pairingcode' => $response['qrcode']['pairingCode'] ?? $response['pairingCode'] ?? null,
            'lasterror' => null,
        ]);
    }

    public function start_pairing_session(string $phonenumber): \stdClass {
        $instancename = config::get_sender_session_id();
        $number = phone_resolver::normalize($phonenumber);
        if ($number === null) {
            throw new \invalid_parameter_exception(get_string('error:invalidnumber', 'message_wamoodle'));
        }

        $instance = $this->client->fetch_instance($instancename);
        $response = $instance === null
            ? $this->client->create_instance($instancename, $number)
            : $this->client->connect_instance($instancename, $number);

        return $this->repository->upsert($instancename, [
            'connectionstatus' => 'connecting',
            'phonenumber' => $number,
            'pairingcode' => $response['qrcode']['pairingCode'] ?? $response['pairingCode'] ?? null,
            'qrcode' => $response['qrcode']['code'] ?? $response['code'] ?? null,
            'qrbase64' => $response['qrcode']['base64'] ?? null,
            'lasterror' => null,
        ]);
    }

    public function send_text(string $number, string $text): array {
        $session = $this->refresh();
        if ($session->connectionstatus !== 'open') {
            throw new \moodle_exception('error:sessionnotopen', 'message_wamoodle');
        }

        return $this->client->send_text_message(config::get_sender_session_id(), $number, $text);
    }

    public function get_local_state(): ?\stdClass {
        return $this->repository->get(config::get_sender_session_id());
    }
}
