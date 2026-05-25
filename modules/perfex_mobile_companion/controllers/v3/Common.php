<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once __DIR__ . '/../REST_Controller.php';

class Common extends REST_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function data_get($type = "")
    {
        $allowed_type = ["app_menu_items"];
        if (empty($type) || !in_array($type, $allowed_type)) {
            $this->response([
                'status' => FALSE,
                'message' => 'Not valid data'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        $data = $this->{$type}();
        if (empty($data)) {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code  
    }

    public function app_menu_items()
    {
        $CI = &get_instance();

        add_mobile_app_menu_item('dashboard', [
            'title'       => _l('als_dashboard'),
            'routerLink' => 'dashboard',
            'position'   => 1,
            'icon'       => 'menu'
        ]);

        if (
            has_permission('customers', '', 'view') || (have_assigned_customers() || (!have_assigned_customers() && has_permission('customers', '', 'create')))
        ) {
            add_mobile_app_menu_item('customers', [
                'title'        => _l('als_clients'),
                'routerLink'  => 'customers',
                'position'    => 5,
                'icon'        => 'user',
            ]);
        }

        if ((has_permission('proposals', '', 'view') || has_permission('proposals', '', 'view_own')) || (staff_has_assigned_proposals() && get_option('allow_staff_view_proposals_assigned') == 1)) {
            add_mobile_app_menu_item('proposals', [
                'title'       => _l('proposals'),
                'routerLink' => 'proposals',
                'position'   => 5,
                'icon' => 'file'
            ]);
        }

        if ((has_permission('estimates', '', 'view') || has_permission('estimates', '', 'view_own')) || (staff_has_assigned_estimates() && get_option('allow_staff_view_estimates_assigned') == 1)
        ) {
            add_mobile_app_menu_item('estimates', [
                'title'       => _l('estimates'),
                'routerLink' => 'estimates',
                'position'   => 10,
                'icon' => 'timer'
            ]);
        }

        if ((has_permission('invoices', '', 'view') || has_permission('invoices', '', 'view_own')) || (staff_has_assigned_invoices() && get_option('allow_staff_view_invoices_assigned') == 1)
        ) {
            add_mobile_app_menu_item('invoices', [
                'title'       => _l('invoices'),
                'routerLink' => 'invoices',
                'position'   => 15,
                'icon'       => 'invoice'
            ]);
        }

        if (
            has_permission('payments', '', 'view') || has_permission('invoices', '', 'view_own')
            || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices())
        ) {
            add_mobile_app_menu_item('payments', [
                'title'     => _l('payments'),
                'routerLink'     => 'payments',
                'position' => 20,
                'icon' => 'dollar'
            ]);
        }

        if (has_permission('credit_notes', '', 'view') || has_permission('credit_notes', '', 'view_own')) {
            add_mobile_app_menu_item('credit_notes', [
                'title'     => _l('credit_notes'),
                'routerLink'     => 'credit_notes',
                'position' => 25,
                'icon'     => 'edit'
            ]);
        }

        if (has_permission('items', '', 'view')) {
            add_mobile_app_menu_item('items', [
                'title'     => _l('items'),
                'routerLink'     => 'invoice_items',
                'position' => 30,
                'icon'     => 'items'
            ]);
        }

        if (has_permission('subscriptions', '', 'view') || has_permission('subscriptions', '', 'view_own')) {
            add_mobile_app_menu_item('subscriptions', [
                'title'     => _l('subscriptions'),
                'routerLink'     => 'subscriptions',
                'icon'     => 'calendar',
                'position' => 15
            ]);
        }

        if (has_permission('expenses', '', 'view') || has_permission('expenses', '', 'view_own')) {
            add_mobile_app_menu_item('expenses', [
                'title'     => _l('expenses'),
                'routerLink'     => 'expenses',
                'icon'     => 'dollar-square',
                'position' => 20
            ]);
        }

        if (has_permission('contracts', '', 'view') || has_permission('contracts', '', 'view_own')) {
            add_mobile_app_menu_item('contracts', [
                'title'     => _l('contracts'),
                'routerLink'     => 'contracts',
                'icon'     => 'contract',
                'position' => 25
            ]);
        }

        add_mobile_app_menu_item('projects', [
            'title'     => _l('projects'),
            'routerLink'     => 'projects',
            'icon'     => 'folder',
            'position' => 30
        ]);

        add_mobile_app_menu_item('tasks', [
            'title'     => _l('als_tasks'),
            'routerLink'     => 'tasks',
            'icon'     => 'note',
            'position' => 35
        ]);

        if ((!is_staff_member() && get_option('access_tickets_to_none_staff_members') == 1) || is_staff_member()) {
            add_mobile_app_menu_item('support', [
                'title'     => _l('support'),
                'routerLink' => 'tickets',
                'icon'     => 'poll',
                'position' => 40
            ]);
        }

        if (is_staff_member()) {
            add_mobile_app_menu_item('leads', [
                'title'     => _l('als_leads'),
                'routerLink'     => 'leads',
                'icon'     => 'lead',
                'position' => 45
            ]);
        }

        return $CI->app_menu->get('mobile_app_menu');
    }
}
