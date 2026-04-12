<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

final class broker_client {
    private function request(string $method, string $path, ?array $payload = null, array $extraheaders = []): array {
        global $CFG;

        $body = $payload !== null ? json_encode($payload) : '{}';
        $timestamp = gmdate('c');
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]), config::get_plugin_secret());

        $headers = array_merge([
            'Content-Type: application/json',
            'x-client-key: ' . config::get_client_key(),
            'x-site-url: ' . $CFG->wwwroot,
            'x-signature-timestamp: ' . $timestamp,
            'x-signature-nonce: ' . $nonce,
            'x-signature: ' . $signature,
        ], $extraheaders);

        $curl = new \curl();
        $options = [
            'CURLOPT_CUSTOMREQUEST' => $method,
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_TIMEOUT' => 20,
        ];

        $url = config::get_backend_url() . $path;
        if ($method === 'GET') {
            $response = $curl->get($url, [], $options);
        } else {
            $response = $curl->post($url, $body, $options);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        $decoded = json_decode($response, true);
        $data = is_array($decoded) ? $decoded : [];

        if ($httpcode < 200 || $httpcode >= 300) {
            $errordetails = $data['message'] ?? $data['error'] ?? "HTTP $httpcode";
            throw new \moodle_exception('error:backend', 'message_wamoodle', '', null, is_string($errordetails) ? $errordetails : json_encode($errordetails));
        }

        return $data;
    }

    public function verify_license(): array {
        return $this->request('POST', '/api/licenses/verify', [
            'pluginCode' => config::get_plugin_code(),
            'siteUrl' => $GLOBALS['CFG']->wwwroot,
        ]);
    }

    public function get_license_status(): array {
        return $this->request('GET', '/api/licenses/status');
    }

    public function get_sender_status(): array {
        return $this->request('GET', '/api/evolution/status');
    }

    public function send_text_message(string $number, string $text, string $idempotencykey): array {
        return $this->request('POST', '/api/evolution/send', [
            'number' => $number,
            'text' => $text,
            'siteUrl' => $GLOBALS['CFG']->wwwroot,
        ], [
            'x-idempotency-key: ' . $idempotencykey,
        ]);
    }

    public function fetch_groups_cached(): array {
        $cache = \cache::make('message_wamoodle', 'evolutiongroups');
        $cachekey = md5(config::get_client_key() . '_broker_groups');
        $groups = $cache->get($cachekey);

        if ($groups !== false) {
            return $groups;
        }

        $response = $this->request('GET', '/api/evolution/groups');
        $groups = $response['groups'] ?? [];
        $cache->set($cachekey, $groups);
        return $groups;
    }

    public function join_group(string $invitecode): array {
        return $this->request('POST', '/api/evolution/group/join', [
            'inviteCode' => $invitecode, 
        ]);
    }

    public function send_button_message(string $number, array $payload, string $idempotencykey): array {
        return $this->request('POST', '/api/evolution/send/buttons', array_merge(
            ['number' => $number],
            $payload
        ), [
            'x-idempotency-key: ' . $idempotencykey,
        ]);
    }
}
