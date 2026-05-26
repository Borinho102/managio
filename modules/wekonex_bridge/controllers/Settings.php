<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_admin()) {
            access_denied('wekonex_bridge');
        }
        $this->load->model('wekonex_bridge/wekonex_bridge_model');
    }

    public function index()
    {
        if ($this->input->post('save_settings')) {
            update_option('wekonex_bridge_enabled', $this->input->post('wekonex_bridge_enabled') ? '1' : '0');
            update_option('wekonex_bridge_wekonex_url', trim($this->input->post('wekonex_bridge_wekonex_url', true)));
            update_option('wekonex_bridge_sso_secret', trim($this->input->post('wekonex_bridge_sso_secret', true)));
            update_option('wekonex_bridge_webhook_secret', trim($this->input->post('wekonex_bridge_webhook_secret', true)));
            update_option('wekonex_bridge_log_days', (int) $this->input->post('wekonex_bridge_log_days'));
            update_option('wekonex_bridge_sso_auto_staff', $this->input->post('wekonex_bridge_sso_auto_staff') ? '1' : '0');

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('wekonex_bridge/settings'));
        }

        if ($this->input->post('create_integration_staff')) {
            $email = trim($this->input->post('api_staff_email', true));
            $password = $this->input->post('api_staff_password', false);

            $result = $this->wekonex_bridge_model->ensure_integration_staff($email, $password);

            if ($result['success']) {
                if ($password !== '') {
                    update_option('wekonex_bridge_api_staff_password', $password);
                }
                set_alert('success', $result['message']);
            } else {
                set_alert('danger', $result['message']);
            }

            redirect(admin_url('wekonex_bridge/settings#api-setup'));
        }

        if ($this->input->post('test_perfexgo_api')) {
            $email = trim($this->input->post('api_staff_email', true));
            $password = $this->input->post('api_staff_password', false);
            if ($password !== '') {
                update_option('wekonex_bridge_api_staff_password', $password);
            }
            if ($email !== '') {
                update_option('wekonex_bridge_api_staff_email', $email);
            }

            $result = $this->wekonex_bridge_model->run_perfexgo_api_test(
                $email ?: null,
                $password !== '' ? $password : null
            );

            if ($result['success']) {
                set_alert('success', $result['message']);
            } else {
                set_alert('danger', $result['message']);
            }

            redirect(admin_url('wekonex_bridge/settings#api-setup'));
        }

        $data['title'] = _l('wekonex_bridge_settings');
        $data['sso_consume_url'] = site_url('wekonex_bridge/auth/consume');
        $data['webhook_ping_url'] = site_url('wekonex_bridge/webhook/ping');
        $data['login_api_url'] = site_url('perfex_mobile_companion/v1/login/login_api');
        $data['integration_staff'] = $this->wekonex_bridge_model->get_integration_staff();
        $data['api_credentials'] = $this->wekonex_bridge_model->get_api_credentials_for_wekonex();
        $data['default_api_email'] = get_option('wekonex_bridge_api_staff_email') ?: 'integration@wekonex.local';

        $this->load->view('wekonex_bridge/settings', $data);
    }
}
