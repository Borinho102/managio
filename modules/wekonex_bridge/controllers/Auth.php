<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SSO Wekonex → Managio (Phase 0 : validation jeton ; Phase 1 : session staff/contact).
 */
class Auth extends App_Controller
{
    public function consume()
    {
        if (!wekonex_bridge_is_enabled()) {
            show_error('Wekonex Bridge is disabled.', 503);
        }

        $token = $this->input->get('token', true);
        if (empty($token)) {
            wekonex_bridge_log('sso_consume', false, ['error_message' => 'Missing token']);
            show_error('Missing SSO token.', 400);
        }

        $verification = wekonex_bridge_verify_sso_token($token);
        if (!$verification['valid']) {
            wekonex_bridge_log('sso_consume', false, ['error_message' => $verification['message'] ?? 'Invalid token']);
            show_error($verification['message'] ?? 'Invalid token', 401);
        }

        $payload = $verification['payload'];
        $this->load->model('wekonex_bridge/wekonex_bridge_model');

        if ($this->wekonex_bridge_model->sso_token_already_used($payload['nonce'])) {
            wekonex_bridge_log('sso_consume', false, ['error_message' => 'Token already used']);
            show_error('Token already used.', 401);
        }

        $this->wekonex_bridge_model->store_sso_token($payload['nonce'], $payload, $token);

        wekonex_bridge_log('sso_consume', true, [
            'payload' => [
                'email' => $payload['email'] ?? null,
                'role' => $payload['role'] ?? null,
                'tenant_id' => $payload['tenant_id'] ?? null,
            ],
        ]);

        // Phase 1 : créer session staff / contact selon $payload['role']
        $wekonexUrl = rtrim(get_option('wekonex_bridge_wekonex_url'), '/');
        set_alert('success', _l('wekonex_bridge_sso_validated'));

        redirect($wekonexUrl ?: admin_url());
    }
}
