<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

final class evolution_client {
    private function request(string $method, string $path, ?array $payload = null, array $queryparams = []): array {
        $curl = new \curl();
        $headers = [
            'apikey: ' . config::get_evolution_api_key(),
            'Content-Type: application/json',
        ];
        $options = [
            'CURLOPT_CUSTOMREQUEST' => $method,
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_TIMEOUT' => 20,
        ];

        $url = config::get_evolution_url() . $path;
        if ($queryparams) {
            $url .= '?' . http_build_query($queryparams);
        }

        if ($method === 'GET') {
            $response = $curl->get($url, [], $options);
        } else {
            $response = $curl->post($url, $payload !== null ? json_encode($payload) : '', $options);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        $decoded = json_decode($response, true);
        $data = is_array($decoded) ? $decoded : [];

        if ($httpcode < 200 || $httpcode >= 300) {
            $message = $data['response']['message'][0] ?? $data['error']['message'] ?? $data['message'] ?? get_string('error:evolution', 'message_wamoodle');
            throw new \moodle_exception('error:evolution', 'message_wamoodle', '', $message);
        }

        return $data;
    }

    public function fetch_instances(): array {
        return $this->request('GET', '/instance/fetchInstances');
    }

    public function fetch_instance(string $instancename): ?array {
        foreach ($this->fetch_instances() as $instance) {
            if (($instance['name'] ?? null) === $instancename
                || ($instance['instance']['instanceName'] ?? null) === $instancename) {
                return $instance;
            }
        }

        return null;
    }

    public function create_instance(string $instancename, ?string $phonenumber = null): array {
        $payload = [
            'instanceName' => $instancename,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ];

        if ($phonenumber !== null) {
            $payload['number'] = $phonenumber;
        }

        return $this->request('POST', '/instance/create', $payload);
    }

    public function connect_instance(string $instancename, ?string $phonenumber = null): array {
        $queryparams = [];
        if ($phonenumber !== null) {
            $queryparams['number'] = $phonenumber;
        }

        return $this->request('GET', '/instance/connect/' . rawurlencode($instancename), null, $queryparams);
    }

    public function get_connection_state(string $instancename): ?array {
        try {
            return $this->request('GET', '/instance/connectionState/' . rawurlencode($instancename));
        } catch (\moodle_exception $exception) {
            if (str_contains($exception->getMessage(), 'does not exist')) {
                return null;
            }

            throw $exception;
        }
    }

    public function send_text_message(string $instancename, string $number, string $text): array {
        return $this->request('POST', '/message/sendText/' . rawurlencode($instancename), [
            'number' => $number,
            'text' => $text,
        ]);
    }
}
