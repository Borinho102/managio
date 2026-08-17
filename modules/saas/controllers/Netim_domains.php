<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Netim_domains extends AdminController
{
    public $netim;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
        saas_access();
        $this->load->library('Saas_Netim', null, 'netim');
    }

    // ------------------------------------------------------------------
    // Admin: Netim API Settings
    // ------------------------------------------------------------------

    public function settings()
    {
        $data['title'] = 'Netim Domain Settings';

        if ($this->input->post()) {
            $fields = [
                'netim_api_login', 'netim_api_password', 'netim_sandbox_mode',
                'netim_nameserver_1', 'netim_nameserver_2', 'netim_server_ip',
                'netim_auto_dns', 'netim_auto_register',
            ];
            foreach ($fields as $field) {
                $val = $this->input->post($field, true);
                update_option($field, $val ?? '');
            }
            log_message('debug', '[Netim_domains::settings] Settings updated');
            set_alert('success', 'Netim settings saved successfully.');
            redirect('saas/netim_domains/settings');
        }

        $data['subview'] = $this->load->view('domain/netim_settings', $data, true);
        $this->load->view('_layout_main', $data);
    }

    // ------------------------------------------------------------------
    // Admin: Test API connection
    // ------------------------------------------------------------------

    public function test_connection()
    {
        if (!$this->input->is_ajax_request()) {
            redirect('saas/dashboard');
        }
        $result = $this->netim->testConnection();
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Connection successful!' : ($result['error'] ?? 'Failed'),
        ]);
        exit();
    }

    // ------------------------------------------------------------------
    // Admin: All domain purchase requests
    // ------------------------------------------------------------------

    public function requests()
    {
        $data['title'] = 'Netim Domain Requests';
        $data['requests'] = get_old_result('tbl_saas_netim_requests', [], 'array');

        // Enrich with company name
        if (!empty($data['requests'])) {
            foreach ($data['requests'] as &$req) {
                $company = get_old_result('tbl_saas_companies', ['id' => $req['company_id']], false);
                $req['company_name'] = $company ? $company->name : '—';
            }
            unset($req);
        }

        $data['subview'] = $this->load->view('domain/netim_requests', $data, true);
        $this->load->view('_layout_main', $data);
    }

    // ------------------------------------------------------------------
    // Admin: Register domain via Netim API (process a pending request)
    // ------------------------------------------------------------------

    public function register($request_id)
    {
        $request = get_old_result('tbl_saas_netim_requests', ['request_id' => $request_id], false);
        if (empty($request)) {
            set_alert('danger', 'Request not found.');
            redirect('saas/netim_domains/requests');
        }

        // Load contact
        $contact = get_old_result('tbl_saas_netim_contacts', ['contact_id' => $request->contact_id], false);
        if (empty($contact)) {
            set_alert('danger', 'Contact not found. Cannot register without WHOIS contact.');
            redirect('saas/netim_domains/requests');
        }

        // Create Netim contact if no handle yet
        $handle = $contact->netim_handle;
        if (empty($handle)) {
            log_message('debug', '[Netim_domains::register] Creating Netim contact for contact_id=' . $contact->contact_id);
            $contactResult = $this->netim->createContact([
                'first_name'   => $contact->first_name,
                'last_name'    => $contact->last_name,
                'email'        => $contact->email,
                'phone'        => $contact->phone,
                'address'      => $contact->address,
                'city'         => $contact->city,
                'state'        => $contact->state,
                'zipcode'      => $contact->zipcode,
                'country'      => $contact->country,
                'legal_type'   => $contact->legal_type,
                'company_name' => $contact->company_name,
            ]);

            if (!$contactResult['success']) {
                log_message('error', '[Netim_domains::register] Contact creation failed: ' . ($contactResult['error'] ?? ''));
                set_alert('danger', 'Failed to create Netim contact: ' . ($contactResult['error'] ?? 'Unknown error'));
                redirect('saas/netim_domains/requests');
            }

            $handle = $contactResult['data']['idContact'] ?? $contactResult['data']['handle'] ?? '';
            if (!empty($handle)) {
                $this->db->where('contact_id', $contact->contact_id);
                $this->db->update('tbl_saas_netim_contacts', ['netim_handle' => $handle]);
            }
        }

        if (empty($handle)) {
            set_alert('danger', 'Could not obtain Netim contact handle.');
            redirect('saas/netim_domains/requests');
        }

        // Register domain
        $regResult = $this->netim->registerDomain($request->domain_name, $handle);
        log_message('debug', '[Netim_domains::register] domain=' . $request->domain_name . ' result=' . json_encode($regResult));

        if (!$regResult['success']) {
            set_alert('danger', 'Domain registration failed: ' . ($regResult['error'] ?? 'Unknown error'));
            redirect('saas/netim_domains/requests');
        }

        // Save to tbl_saas_netim_domains
        $orderId   = $regResult['data']['orderId'] ?? $regResult['data']['id'] ?? null;
        $expiryRaw = $regResult['data']['expiryDate'] ?? null;
        $tld       = ltrim(strstr($request->domain_name, '.'), '.');

        $this->db->insert('tbl_saas_netim_domains', [
            'company_id'     => $request->company_id,
            'domain_name'    => $request->domain_name,
            'tld'            => $tld,
            'contact_handle' => $handle,
            'status'         => 'active',
            'expiry_date'    => !empty($expiryRaw) ? date('Y-m-d', strtotime($expiryRaw)) : null,
            'auto_renew'     => 1,
            'netim_order_id' => $orderId,
            'purchase_price' => $request->price,
            'currency'       => $request->currency,
            'registered_at'  => date('Y-m-d H:i:s'),
        ]);

        // Auto DNS setup
        if (get_option('netim_auto_dns') == '1') {
            $this->netim->autoConfigureDns($request->domain_name);
        }

        // Update tbl_saas_companies domain_url
        $this->db->where('id', $request->company_id);
        $this->db->update('tbl_saas_companies', ['domain_url' => 'https://' . $request->domain_name]);

        // Auto-approve domain request in tbl_saas_domain_requests
        $this->db->insert_or_update = false;
        $this->db->where('company_id', $request->company_id);
        $existing = $this->db->get('tbl_saas_domain_requests')->row();
        if ($existing) {
            $this->db->where('request_id', $existing->request_id);
            $this->db->update('tbl_saas_domain_requests', [
                'custom_domain' => $request->domain_name,
                'status'        => 'approved',
            ]);
        } else {
            $this->db->insert('tbl_saas_domain_requests', [
                'company_id'    => $request->company_id,
                'custom_domain' => $request->domain_name,
                'status'        => 'approved',
            ]);
        }

        // Mark request as processed
        $this->db->where('request_id', $request_id);
        $this->db->update('tbl_saas_netim_requests', [
            'status'       => 'registered',
            'processed_at' => date('Y-m-d H:i:s'),
        ]);

        log_activity('Netim Domain Registered [Domain: ' . $request->domain_name . ' Company: ' . $request->company_id . ']');
        set_alert('success', 'Domain ' . $request->domain_name . ' registered successfully!');
        redirect('saas/netim_domains/requests');
    }

    // ------------------------------------------------------------------
    // Admin: All registered domains list
    // ------------------------------------------------------------------

    public function domain_list()
    {
        $data['title'] = 'Netim Registered Domains';
        $data['domains'] = get_old_result('tbl_saas_netim_domains', [], 'array');

        if (!empty($data['domains'])) {
            foreach ($data['domains'] as &$d) {
                $company = get_old_result('tbl_saas_companies', ['id' => $d['company_id']], false);
                $d['company_name'] = $company ? $company->name : '—';
            }
            unset($d);
        }

        $data['subview'] = $this->load->view('domain/netim_domain_list', $data, true);
        $this->load->view('_layout_main', $data);
    }

    // ------------------------------------------------------------------
    // Admin: Reject a pending request
    // ------------------------------------------------------------------

    public function reject($request_id)
    {
        $this->db->where('request_id', $request_id);
        $this->db->update('tbl_saas_netim_requests', [
            'status'       => 'rejected',
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
        set_alert('warning', 'Domain request rejected.');
        redirect('saas/netim_domains/requests');
    }

    // ------------------------------------------------------------------
    // Admin: Configure DNS for already-registered domain
    // ------------------------------------------------------------------

    public function configure_dns($domain_id)
    {
        $domain = get_old_result('tbl_saas_netim_domains', ['domain_id' => $domain_id], false);
        if (empty($domain)) {
            set_alert('danger', 'Domain not found.');
            redirect('saas/netim_domains/domain_list');
        }

        $results = $this->netim->autoConfigureDns($domain->domain_name);

        if ($results === false) {
            set_alert('danger', 'DNS configuration failed. Check server IP setting.');
        } else {
            $this->db->where('domain_id', $domain_id);
            $this->db->update('tbl_saas_netim_domains', ['dns_configured' => 1]);
            log_activity('Netim DNS Configured [Domain: ' . $domain->domain_name . ']');
            set_alert('success', 'DNS records configured for ' . $domain->domain_name);
        }
        redirect('saas/netim_domains/domain_list');
    }

    // ------------------------------------------------------------------
    // AJAX: Domain availability check (used by admin and client)
    // ------------------------------------------------------------------

    public function check_availability()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $domain = trim($this->input->post('domain', true));
        if (empty($domain)) {
            echo json_encode(['success' => false, 'error' => 'Domain is required.']);
            exit();
        }

        if (!$this->netim->isConfigured()) {
            echo json_encode(['success' => false, 'error' => 'Netim API not configured.']);
            exit();
        }

        $result = $this->netim->checkDomain($domain);
        echo json_encode($result);
        exit();
    }
}
