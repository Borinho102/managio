<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wekonex_bridge_model extends App_Model
{
    public const INTEGRATION_PERMISSIONS = [
        'customers' => ['view', 'create', 'edit'],
        'invoices'  => ['view', 'create', 'edit'],
        'payments'  => ['view', 'create'],
        'items'     => ['view'],
        'estimates' => ['view', 'create'],
    ];

    public function sso_token_already_used(string $nonce): bool
    {
        $table = db_prefix() . 'wekonex_sso_tokens';
        if (!$this->db->table_exists($table)) {
            return false;
        }

        $hash = hash('sha256', $nonce);

        return $this->db->where('token_hash', $hash)->count_all_results($table) > 0;
    }

    public function store_sso_token(string $nonce, array $payload, string $rawToken): void
    {
        $table = db_prefix() . 'wekonex_sso_tokens';
        if (!$this->db->table_exists($table)) {
            return;
        }

        $hash = hash('sha256', $nonce);
        $expires = !empty($payload['exp']) ? date('Y-m-d H:i:s', (int) $payload['exp']) : date('Y-m-d H:i:s', time() + 120);

        $this->db->insert($table, [
            'token_hash' => $hash,
            'payload' => json_encode($payload),
            'expires_at' => $expires,
            'used_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Phase 0.3 — Crée ou récupère le staff « Wekonex Integration ».
     *
     * @return array{success: bool, message: string, staff_id?: int, created?: bool}
     */
    public function ensure_integration_staff(string $email, string $password): array
    {
        $email = trim($email);
        if ($email === '' || $password === '') {
            return ['success' => false, 'message' => 'Email and password are required'];
        }

        $existing = $this->db->where('email', $email)->get(db_prefix() . 'staff')->row();
        if ($existing) {
            update_option('wekonex_bridge_api_staff_id', (string) $existing->staffid);
            update_option('wekonex_bridge_api_staff_email', $email);

            if ($password !== '') {
                $this->load->model('staff_model');
                $this->staff_model->update([
                    'password' => $password,
                ], $existing->staffid);
            }

            return [
                'success' => true,
                'message' => _l('wekonex_bridge_staff_exists'),
                'staff_id' => (int) $existing->staffid,
                'created' => false,
            ];
        }

        $this->load->model('staff_model');

        $staffId = $this->staff_model->add([
            'firstname' => 'Wekonex',
            'lastname'  => 'Integration',
            'email'     => $email,
            'password'  => $password,
            'active'    => 1,
            'send_welcome_email' => false,
            'permissions' => self::INTEGRATION_PERMISSIONS,
        ]);

        if (!$staffId) {
            return ['success' => false, 'message' => _l('wekonex_bridge_staff_create_failed')];
        }

        update_option('wekonex_bridge_api_staff_id', (string) $staffId);
        update_option('wekonex_bridge_api_staff_email', $email);

        log_activity('Wekonex Bridge: integration staff created [ID:' . $staffId . ']');

        return [
            'success' => true,
            'message' => _l('wekonex_bridge_staff_created'),
            'staff_id' => (int) $staffId,
            'created' => true,
        ];
    }

    public function get_integration_staff(): ?object
    {
        $staffId = (int) get_option('wekonex_bridge_api_staff_id');
        if ($staffId <= 0) {
            $email = get_option('wekonex_bridge_api_staff_email');
            if ($email !== '') {
                $row = $this->db->where('email', $email)->get(db_prefix() . 'staff')->row();
                if ($row) {
                    update_option('wekonex_bridge_api_staff_id', (string) $row->staffid);
                    return $row;
                }
            }
            return null;
        }

        return $this->db->where('staffid', $staffId)->get(db_prefix() . 'staff')->row();
    }

    /**
     * Phase 0.4 — Login PerfexGo + test JWT / X-API-KEY.
     *
     * @return array{success: bool, message: string, login?: array, api_test?: array}
     */
    public function run_perfexgo_api_test(?string $email = null, ?string $password = null): array
    {
        $CI = &get_instance();
        $CI->load->library(WEKONEX_BRIDGE_MODULE . '/Wekonex_perfex_api');

        $staff = $this->get_integration_staff();
        $email = $email ?: ($staff->email ?? get_option('wekonex_bridge_api_staff_email'));
        $password = $password ?? get_option('wekonex_bridge_api_staff_password');

        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'message' => _l('wekonex_bridge_api_missing_credentials'),
            ];
        }

        $login = $CI->wekonex_perfex_api->login($email, $password);
        if (!$login['success']) {
            $this->save_api_test_result(false, $login['message']);
            return [
                'success' => false,
                'message' => $login['message'],
                'login' => $login,
            ];
        }

        update_option('wekonex_bridge_api_key', $login['key']);
        update_option('wekonex_bridge_api_token', $login['token']);
        if (!empty($login['staff_id'])) {
            update_option('wekonex_bridge_api_staff_id', (string) $login['staff_id']);
        }

        $apiTest = $CI->wekonex_perfex_api->testAuthenticatedRequest($login['key'], $login['token']);

        $overall = $apiTest['success'];
        $message = $overall
            ? _l('wekonex_bridge_api_test_ok')
            : ($apiTest['message'] ?? _l('wekonex_bridge_api_test_failed'));

        $this->save_api_test_result($overall, $message);

        wekonex_bridge_log('perfexgo_api_test', $overall, [
            'http_status' => $apiTest['http_status'] ?? $login['http_status'] ?? null,
            'payload' => [
                'login' => true,
                'api_test' => $overall,
            ],
            'error_message' => $overall ? null : $message,
        ]);

        return [
            'success' => $overall,
            'message' => $message,
            'login' => [
                'success' => true,
                'http_status' => $login['http_status'] ?? null,
                'key_preview' => substr($login['key'], 0, 8) . '…',
            ],
            'api_test' => $apiTest,
        ];
    }

    public function save_api_test_result(bool $success, string $message): void
    {
        update_option('wekonex_bridge_api_last_test', date('Y-m-d H:i:s'));
        update_option('wekonex_bridge_api_last_test_status', $success ? 'ok' : 'failed');
        update_option('wekonex_bridge_api_last_test_message', $message);
    }

    public function get_api_credentials_for_wekonex(): array
    {
        return [
            'email' => get_option('wekonex_bridge_api_staff_email'),
            'api_key' => get_option('wekonex_bridge_api_key'),
            'api_token' => get_option('wekonex_bridge_api_token'),
            'staff_id' => get_option('wekonex_bridge_api_staff_id'),
            'last_test' => get_option('wekonex_bridge_api_last_test'),
            'last_test_status' => get_option('wekonex_bridge_api_last_test_status'),
        ];
    }
}
