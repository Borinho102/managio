<?php

defined('BASEPATH') or exit('No direct script access allowed');

class App_usercontrol_area_constructor
{
    private $ci;

    public function __construct()
    {
        if (!defined('AFFILIATE_MODULE_NAME')) {
            define('AFFILIATE_MODULE_NAME', 'affiliate');
        }
        if (!defined('VERSION_AFF')) {
            define('VERSION_AFF', 106);
        }

        $this->ci = &get_instance();

        if (!function_exists('affiliate_enqueue_portal_assets')) {
            $this->ci->load->helper('affiliate/affiliate');
        }

        $this->ci->load->library('form_validation');
        $this->ci->form_validation->set_error_delimiters('<p class="text-danger alert-validation">', '</p>');

        $this->ci->form_validation->set_message('required', _l('form_validation_required'));
        $this->ci->form_validation->set_message('valid_email', _l('form_validation_valid_email'));
        $this->ci->form_validation->set_message('matches', _l('form_validation_matches'));
        $this->ci->form_validation->set_message('is_unique', _l('form_validation_is_unique'));

        $vars = [
            'isRTL'      => 'false',
            'menu'       => [],
            'departments'=> [],
            'priorities' => [],
            'ticket_statuses' => [],
            'currencies' => [],
        ];

        if (function_exists('affiliate_enqueue_portal_assets')) {
            try {
                affiliate_enqueue_portal_assets();
            } catch (Throwable $e) {
                log_message('error', 'affiliate portal assets: ' . $e->getMessage());
            }
        } else {
            $functions = module_dir_path(AFFILIATE_MODULE_NAME, 'views/usercontrol/functions.php');
            if (is_file($functions)) {
                include_once $functions;
            }
            if (function_exists('theme_affiliate')) {
                theme_affiliate();
            }
        }

        try {
            $this->ci->load->model('tickets_model');
            $this->ci->load->model('departments_model');
            $this->ci->load->model('currencies_model');
            $this->ci->load->model('invoices_model');
            $this->ci->load->model('estimates_model');
            $this->ci->load->model('proposals_model');
            $this->ci->load->model('projects_model');
            $this->ci->load->model('announcements_model');
            $this->ci->load->model('contracts_model');
            $this->ci->load->model('knowledge_base_model');
            $this->ci->load->model('affiliate/affiliate_model');
            $this->ci->load->model('affiliate/authentication_affiliate_model');

            if (function_exists('is_affiliate_logged_in') && is_affiliate_logged_in()) {
                $affiliate            = $this->ci->affiliate_model->get_member(get_affiliate_user_id());
                $GLOBALS['affiliate'] = $affiliate;

                if (!$affiliate || (($affiliate->status ?? 1) == 0)) {
                    $this->ci->authentication_affiliate_model->logout(true);
                    redirect(site_url('affiliate/authentication_affiliate/login'));
                }

                $vars['affiliate'] = $affiliate;
            }

            if (function_exists('is_client_logged_in') && is_client_logged_in()) {
                $contact            = $this->ci->clients_model->get_contact(get_contact_user_id());
                $GLOBALS['contact'] = $contact;

                if (!$contact || $contact->active == 0) {
                    $this->ci->authentication_model->logout(true);
                    redirect(site_url());
                }

                $vars['total_undismissed_announcements'] = $this->ci->announcements_model->get_total_undismissed_announcements();
                $vars['client']                          = $this->ci->clients_model->get($contact->userid);
                $vars['contact']                         = $contact;
            }

            hooks()->do_action('affiliate_init');

            if (isset($this->ci->departments_model)) {
                $vars['departments'] = $this->ci->departments_model->get(false, true);
            }
            if (isset($this->ci->tickets_model)) {
                $vars['priorities']      = $this->ci->tickets_model->get_priority();
                $vars['ticket_statuses'] = $this->ci->tickets_model->get_ticket_status();
                if (get_option('services') == 1) {
                    $vars['services'] = $this->ci->tickets_model->get_service();
                }
            }
            if (isset($this->ci->currencies_model)) {
                $vars['currencies'] = $this->ci->currencies_model->get();
            }
            if (isset($this->ci->app_menu) && method_exists($this->ci->app_menu, 'get_theme_items')) {
                $vars['menu'] = $this->ci->app_menu->get_theme_items();
            }
        } catch (Throwable $e) {
            log_message('error', 'affiliate portal bootstrap: ' . $e->getMessage());
        }

        $vars['isRTL'] = $vars['isRTL'] ?? 'false';
        $vars = hooks()->apply_filters('customers_area_autoloaded_vars', $vars);

        $this->ci->load->vars($vars);
    }
}
