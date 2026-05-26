<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SSO Wekonex → Managio (Phase 1 : session staff / portail contact).
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

        $this->load->model('wekonex_bridge/wekonex_sync_model');
        $this->load->model('authentication_model');

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $role = (string) ($payload['role'] ?? 'alumni');
        $adminRoles = ['super_admin', 'admin', 'board_member'];

        if (in_array($role, $adminRoles, true)) {
            $staff = $this->wekonex_sync_model->find_staff_for_sso($email);
            if (!$staff) {
                $staff = $this->wekonex_bridge_model->ensure_sso_staff($payload);
            }

            if ($staff && (int) $staff->two_factor_auth_enabled === 0) {
                $this->login_staff_session($staff, $email, $role);

                return;
            }

            if ($staff && (int) $staff->two_factor_auth_enabled !== 0) {
                wekonex_bridge_log('sso_consume', false, [
                    'error_message' => 'Staff has 2FA enabled',
                    'payload' => ['email' => $email],
                ]);
                $this->redirect_sso_failure(
                    $payload,
                    _l('wekonex_bridge_sso_staff_2fa')
                );

                return;
            }
        }

        $contact = $this->wekonex_sync_model->find_contact_for_sso($payload);
        if ($contact) {
            hooks()->do_action('before_client_login', [
                'email' => $contact->email,
                'userid' => $contact->userid,
                'contact_user_id' => $contact->id,
            ]);

            $this->session->set_userdata([
                'client_user_id' => $contact->userid,
                'contact_user_id' => $contact->id,
                'client_logged_in' => true,
            ]);
            $this->authentication_model->update_login_info($contact->id, false);

            wekonex_bridge_log('sso_consume', true, [
                'payload' => [
                    'email' => $contact->email,
                    'role' => $role,
                    'session' => 'contact',
                ],
            ]);

            set_alert('success', _l('wekonex_bridge_sso_contact_ok'));
            redirect(site_url('clients'));

            return;
        }

        wekonex_bridge_log('sso_consume', false, [
            'error_message' => 'No matching staff or contact for SSO',
            'payload' => ['email' => $email, 'role' => $role],
        ]);

        $this->redirect_sso_failure(
            $payload,
            _l('wekonex_bridge_sso_no_account') . ' (' . html_escape($email) . ')'
        );
    }

    private function login_staff_session(object $staff, string $email, string $role): void
    {
        hooks()->do_action('before_staff_login', [
            'email' => $email,
            'userid' => $staff->staffid,
        ]);

        $this->session->set_userdata([
            'staff_user_id' => $staff->staffid,
            'staff_logged_in' => true,
        ]);
        $this->authentication_model->update_login_info($staff->staffid, true);

        wekonex_bridge_log('sso_consume', true, [
            'payload' => [
                'email' => $email,
                'role' => $role,
                'session' => 'staff',
            ],
        ]);

        set_alert('success', _l('wekonex_bridge_sso_staff_ok'));
        redirect(admin_url());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function redirect_sso_failure(array $payload, string $message): void
    {
        set_alert('warning', $message);

        $returnUrl = trim((string) ($payload['return_url'] ?? ''));
        if ($returnUrl !== '' && preg_match('#^https?://#i', $returnUrl)) {
            redirect($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'managio_sso=failed');
            return;
        }

        $fallback = rtrim((string) get_option('wekonex_bridge_wekonex_url'), '/');
        if ($fallback !== '') {
            redirect($fallback . '?managio_sso=failed');
            return;
        }

        redirect(admin_url('authentication'));
    }
}
