<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Client interne PerfexGo (login_api + requêtes authentifiées) — Phase 0.3 / 0.4.
 */
class Wekonex_perfex_api
{
    /** @var CI_Controller */
    protected $ci;

    public function __construct()
    {
        $this->ci = &get_instance();
    }

    /**
     * @return array{success: bool, message: string, key?: string, token?: string, staff_id?: int, http_status?: int, raw?: mixed}
     */
    public function login(string $email, string $password): array
    {
        $url = site_url('perfex_mobile_companion/v1/login/login_api');

        $response = $this->httpPostForm($url, [
            'email' => $email,
            'password' => $password,
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => $response['error'] ?? 'HTTP request failed',
                'http_status' => $response['http_status'] ?? null,
                'raw' => $response['body'] ?? null,
            ];
        }

        $body = $response['body'];
        if (!is_array($body)) {
            return ['success' => false, 'message' => 'Invalid JSON response', 'raw' => $body];
        }

        if (empty($body['status'])) {
            return [
                'success' => false,
                'message' => $body['message'] ?? 'Login rejected',
                'http_status' => $response['http_status'],
                'raw' => $body,
            ];
        }

        $result = $body['result'] ?? [];
        $key = $result['key'] ?? null;
        $token = $result['token'] ?? null;
        $staffId = null;
        if (isset($result['data']['staffid'])) {
            $staffId = (int) $result['data']['staffid'];
        } elseif (isset($result['data']) && is_object($result['data']) && isset($result['data']->staffid)) {
            $staffId = (int) $result['data']->staffid;
        }

        if (empty($key) || empty($token)) {
            return [
                'success' => false,
                'message' => 'Missing API key or JWT in login response',
                'raw' => $body,
            ];
        }

        return [
            'success' => true,
            'message' => 'Login OK',
            'key' => (string) $key,
            'token' => (string) $token,
            'staff_id' => $staffId,
            'http_status' => $response['http_status'],
        ];
    }

    /**
     * Teste une requête GET authentifiée (ex. liste clients).
     *
     * @return array{success: bool, message: string, http_status?: int, raw?: mixed}
     */
    public function testAuthenticatedRequest(string $apiKey, string $jwtToken): array
    {
        $url = site_url('perfex_mobile_companion/v1/customers/data');

        $response = $this->httpGet($url, [
            'X-API-KEY' => $apiKey,
            'Authorization' => $jwtToken,
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => $response['error'] ?? 'HTTP request failed',
                'http_status' => $response['http_status'] ?? null,
            ];
        }

        $body = $response['body'];
        if ($response['http_status'] >= 200 && $response['http_status'] < 300) {
            return [
                'success' => true,
                'message' => 'Authenticated API call OK (customers/data)',
                'http_status' => $response['http_status'],
                'raw' => is_array($body) ? ['type' => 'array', 'count' => count($body)] : $body,
            ];
        }

        return [
            'success' => false,
            'message' => is_array($body) && !empty($body['message']) ? $body['message'] : 'API returned error',
            'http_status' => $response['http_status'],
            'raw' => $body,
        ];
    }

    /**
     * @return array{success: bool, http_status?: int, body?: mixed, error?: string}
     */
    protected function httpPostForm(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        return $this->executeCurl($ch);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function httpGet(string $url, array $headers = []): array
    {
        $headerLines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);

        return $this->executeCurl($ch);
    }

    /**
     * @return array{success: bool, http_status?: int, body?: mixed, error?: string}
     */
    protected function executeCurl($ch): array
    {
        $raw = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'error' => $error ?: 'cURL error', 'http_status' => $httpStatus];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = json_decode($raw);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => $httpStatus >= 200 && $httpStatus < 300,
                    'http_status' => $httpStatus,
                    'body' => $raw,
                ];
            }
        }

        return [
            'success' => true,
            'http_status' => $httpStatus,
            'body' => $decoded,
        ];
    }
}
