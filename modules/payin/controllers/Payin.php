<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read Payin_gateway $payin_gateway
 * @property-read Invoices_model $invoices_model
 * @property-read CI_Session $session
 */
class Payin extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('payin/Payin_gateway');
        $this->load->model('invoices_model');
        if (!class_exists('Payin_client', false)) {
            require_once module_dir_path(PAYIN_MODULE_NAME) . 'libraries/Payin_client.php';
        }
    }

    public function success(int $id, string $hash, string $token): void
    {
        check_invoice_restrictions($id, $hash);

        $pending = $this->session->userdata('payin_pending_' . $token);
        if (empty($pending)) {
            $stored = get_option('payin_pending_' . $token);
            if (!empty($stored)) {
                $pending = json_decode($stored, true);
            }
        }
        $invoiceUrl = site_url('invoice/' . $id . '/' . $hash);

        if (empty($pending) || (int) ($pending['invoice_id'] ?? 0) !== $id || ($pending['hash'] ?? '') !== $hash) {
            set_alert('danger', 'PayIn payment session expired. Please try again.');
            redirect($invoiceUrl);
            return;
        }

        try {
            $client = $this->payin_gateway->makeClient();
            $payload = $client->decodeCallbackPayload();
            $client->assertSuccessful($payload, (float) $pending['amount']);
            $transactionId = $client->transactionId($payload);

            if (total_rows('invoicepaymentrecords', ['transactionid' => $transactionId]) === 0) {
                $success = $this->payin_gateway->addPayment([
                    'amount'        => (float) $pending['amount'],
                    'invoiceid'     => $id,
                    'transactionid' => $transactionId,
                    'paymentmethod' => 'PayIn',
                ]);
                if (!$success) {
                    set_alert('danger', 'Payment was received but could not be recorded. Reference: ' . $transactionId);
                    redirect($invoiceUrl);
                    return;
                }
            }

            $this->session->unset_userdata('payin_pending_' . $token);
            delete_option('payin_pending_' . $token);
            set_alert('success', _l('online_payment_recorded_success'));
        } catch (Throwable $e) {
            log_message('error', 'PayIn success callback failed: ' . $e->getMessage());
            set_alert('danger', 'PayIn: ' . $e->getMessage());
        }

        redirect($invoiceUrl);
    }

    public function cancel(int $id, string $hash, string $token): void
    {
        check_invoice_restrictions($id, $hash);
        $this->session->unset_userdata('payin_pending_' . $token);
        delete_option('payin_pending_' . $token);
        set_alert('warning', _l('payment_cancelled') ?: 'Payment cancelled.');
        redirect(site_url('invoice/' . $id . '/' . $hash));
    }

    public function connect(): void
    {
        $this->requireStaff();
        try {
            $company = $this->currentCompany();
            $client = Payin_client::fromSsoConfig();
            $client->provisionCompany($company);
            set_alert('success', 'PayIn wallet connected.');
        } catch (Throwable $e) {
            log_message('error', 'PayIn connect failed: ' . $e->getMessage());
            set_alert('danger', 'PayIn: ' . $e->getMessage());
        }
        redirect($this->settingsRedirect());
    }

    public function sync(): void
    {
        $this->connect();
    }

    public function open(): void
    {
        $this->requireStaff();
        try {
            $company = $this->currentCompany();
            $client = Payin_client::fromSsoConfig();
            if (empty($company->payin_user_id)) {
                $client->provisionCompany($company);
                $company = $this->currentCompany();
            }
            $payload = $client->consumePayload((int) $company->payin_user_id, (string) $company->domain);
            $this->load->view('payin/sso_redirect', [
                'consumeUrl' => $payload['consume_url'],
                'token'      => $payload['token'],
            ]);
        } catch (Throwable $e) {
            log_message('error', 'PayIn open wallet failed: ' . $e->getMessage());
            set_alert('danger', 'PayIn: ' . $e->getMessage());
            redirect($this->settingsRedirect());
        }
    }

    public function refresh(): void
    {
        $this->requireStaff();
        $this->session->unset_userdata('payin_wallet_header');
        set_alert('success', 'PayIn wallet balance refreshed.');
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url());
    }

    private function requireStaff(): void
    {
        if (!is_staff_logged_in()) {
            redirect(admin_url());
        }
    }

    private function currentCompany()
    {
        if (!function_exists('get_company_subscription')) {
            throw new RuntimeException('SaaS company context is not available.');
        }

        $company = get_company_subscription();
        if (empty($company) || empty($company->domain)) {
            throw new RuntimeException('Company not found.');
        }

        $row = function_exists('get_old_result')
            ? get_old_result('tbl_saas_companies', ['domain' => $company->domain], false)
            : null;
        if (!empty($row)) {
            $company->id = $row->id;
            $company->companies_id = $row->id;
            $company->db_name = $row->db_name ?? ($company->db_name ?? null);
            $company->email = $company->email ?: ($row->email ?? '');
            $company->name = $company->name ?: ($row->name ?? '');
            $company->mobile = $company->mobile ?? ($row->mobile ?? null);
            $company->payin_user_id = $row->payin_user_id ?? ($company->payin_user_id ?? null);
            $company->payin_merchant_id = $row->payin_merchant_id ?? ($company->payin_merchant_id ?? null);
        }

        if (empty($company->db_name) && function_exists('company_db_name')) {
            $company->db_name = company_db_name($company->domain) ?: null;
        }
        if (empty($company->db_name)) {
            $company->db_name = $this->session->userdata('db_name') ?: ($this->db->database ?? null);
        }

        return $company;
    }

    private function settingsRedirect(): string
    {
        return admin_url('settings?group=payment_gateways&tab=online_payments_payin_tab');
    }
}
