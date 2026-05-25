<?php

defined('BASEPATH') or exit('No direct script access allowed');

function wekonex_bridge_ensure_options(): void
{
    $defaults = [
        'wekonex_bridge_api_staff_id' => '',
        'wekonex_bridge_api_staff_email' => 'integration@wekonex.local',
        'wekonex_bridge_api_staff_password' => '',
        'wekonex_bridge_api_key' => '',
        'wekonex_bridge_api_token' => '',
        'wekonex_bridge_api_last_test' => '',
        'wekonex_bridge_api_last_test_status' => '',
        'wekonex_bridge_api_last_test_message' => '',
    ];

    foreach ($defaults as $name => $value) {
        if (get_option($name) === false) {
            add_option($name, $value);
        }
    }
}

function wekonex_bridge_is_enabled(): bool
{
    return get_option('wekonex_bridge_enabled') === '1';
}

function wekonex_bridge_sso_secret(): string
{
    return (string) get_option('wekonex_bridge_sso_secret');
}

function wekonex_bridge_webhook_secret(): string
{
    return (string) get_option('wekonex_bridge_webhook_secret');
}

function wekonex_bridge_log(string $action, bool $success, array $context = []): void
{
    $CI = &get_instance();
    $table = db_prefix() . 'wekonex_sync_logs';

    if (!$CI->db->table_exists($table)) {
        return;
    }

    $CI->db->insert($table, [
        'direction' => $context['direction'] ?? 'inbound',
        'action' => $action,
        'success' => $success ? 1 : 0,
        'http_status' => $context['http_status'] ?? null,
        'payload' => !empty($context['payload']) ? json_encode($context['payload']) : null,
        'error_message' => $context['error_message'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Vérifie un jeton SSO signé : base64url(payload).signature (HMAC-SHA256).
 *
 * @return array{valid: bool, payload?: array, message?: string}
 */
function wekonex_bridge_verify_sso_token(string $token): array
{
    $secret = wekonex_bridge_sso_secret();
    if ($secret === '') {
        return ['valid' => false, 'message' => 'SSO secret not configured'];
    }

    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return ['valid' => false, 'message' => 'Invalid token format'];
    }

    [$encodedPayload, $signature] = $parts;
    $payloadJson = wekonex_bridge_base64url_decode($encodedPayload);
    if ($payloadJson === false) {
        return ['valid' => false, 'message' => 'Invalid payload encoding'];
    }

    $expected = hash_hmac('sha256', $encodedPayload, $secret);
    if (!hash_equals($expected, $signature)) {
        return ['valid' => false, 'message' => 'Invalid signature'];
    }

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        return ['valid' => false, 'message' => 'Invalid payload JSON'];
    }

    if (empty($payload['exp']) || time() > (int) $payload['exp']) {
        return ['valid' => false, 'message' => 'Token expired'];
    }

    if (empty($payload['nonce'])) {
        return ['valid' => false, 'message' => 'Missing nonce'];
    }

    return ['valid' => true, 'payload' => $payload];
}

function wekonex_bridge_base64url_decode(string $data)
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($data, '-_', '+/'), true);
}

/**
 * Idempotency : retourne true si la clé existe déjà (replay).
 */
function wekonex_bridge_idempotency_seen(string $key, string $action): bool
{
    $CI = &get_instance();
    $table = db_prefix() . 'wekonex_idempotency_keys';

    if (!$CI->db->table_exists($table)) {
        return false;
    }

    $row = $CI->db->where('idempotency_key', $key)->get($table)->row();
    return $row !== null;
}

function wekonex_bridge_idempotency_store(string $key, string $action, ?string $responseHash = null): void
{
    $CI = &get_instance();
    $table = db_prefix() . 'wekonex_idempotency_keys';

    if (!$CI->db->table_exists($table)) {
        return;
    }

    if ($CI->db->where('idempotency_key', $key)->count_all_results($table) > 0) {
        return;
    }

    $CI->db->insert($table, [
        'idempotency_key' => $key,
        'action' => $action,
        'response_hash' => $responseHash,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function wekonex_bridge_verify_webhook_request(): bool
{
    $secret = wekonex_bridge_webhook_secret();
    if ($secret === '') {
        return true;
    }

    $CI = &get_instance();
    $header = $CI->input->get_request_header('X-Wekonex-Webhook-Secret', true);

    return is_string($header) && hash_equals($secret, $header);
}
