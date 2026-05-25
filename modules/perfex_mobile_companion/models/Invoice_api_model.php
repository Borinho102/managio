<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Invoice_api_model extends App_Model
{

    
    public const STATUS_UNPAID = 1;

    public const STATUS_PAID = 2;

    public const STATUS_PARTIALLY = 3;

    public const STATUS_OVERDUE = 4;

    public const STATUS_CANCELLED = 5;

    public const STATUS_DRAFT = 6;

    public const STATUS_DRAFT_NUMBER = 1000000000;

    private $statuses = [
        self::STATUS_UNPAID,
        self::STATUS_PAID,
        self::STATUS_PARTIALLY,
        self::STATUS_OVERDUE,
        self::STATUS_CANCELLED,
        self::STATUS_DRAFT,
    ];

    private $shipping_fields = [
        'shipping_street',
        'shipping_city',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_statuses()
    {
        return $this->statuses;
    }

    /**
     * Get invoice total from all statuses
     * @since  Version 1.0.2
     * @param  mixed $data $_POST data
     * @return array
     */
    public function get_invoices_total($data)
    {
        $this->load->model('currencies_model');

        if (isset($data['currency'])) {
            $currencyid = $data['currency'];
        } elseif (isset($data['customer_id']) && $data['customer_id'] != '') {
            $currencyid = $this->clients_model->get_customer_default_currency($data['customer_id']);
            if ($currencyid == 0) {
                $currencyid = $this->currencies_model->get_base_currency()->id;
            }
        } elseif (isset($data['project_id']) && $data['project_id'] != '') {
            $this->load->model('projects_model');
            $currencyid = $this->projects_model->get_currency($data['project_id'])->id;
        } else {
            $currencyid = $this->currencies_model->get_base_currency()->id;
        }

        $result            = [];
        $result['due']     = [];
        $result['paid']    = [];
        $result['overdue'] = [];

        $has_permission_view                = has_contact_permission('invoices', get_contact_user_id());
        $noPermissionsQuery                 = get_invoices_where_sql_for_client(get_client_user_id());

        for ($i = 1; $i <= 3; $i++) {
            $select = 'id,total';
            if ($i == 1) {
                $select .= ', (SELECT total - (SELECT COALESCE(SUM(amount),0) FROM ' . db_prefix() . 'invoicepaymentrecords WHERE invoiceid = ' . db_prefix() . 'invoices.id) - (SELECT COALESCE(SUM(amount),0) FROM ' . db_prefix() . 'credits WHERE ' . db_prefix() . 'credits.invoice_id=' . db_prefix() . 'invoices.id)) as outstanding';
            } elseif ($i == 2) {
                $select .= ',(SELECT SUM(amount) FROM ' . db_prefix() . 'invoicepaymentrecords WHERE invoiceid=' . db_prefix() . 'invoices.id) as total_paid';
            }
            $this->db->select($select);
            $this->db->from(db_prefix() . 'invoices');
            $this->db->where('currency', $currencyid);
            // Exclude cancelled invoices
            $this->db->where('status !=', self::STATUS_CANCELLED);
            // Exclude draft
            $this->db->where('status !=', self::STATUS_DRAFT);

            // } elseif (isset($data['customer_id']) && $data['customer_id'] != '') {
                $this->db->where('clientid', get_client_user_id());
            // }

            if ($i == 3) {
                $this->db->where('status', self::STATUS_OVERDUE);
            } elseif ($i == 1) {
                $this->db->where('status !=', self::STATUS_PAID);
            }

            if (isset($data['years']) && count($data['years']) > 0) {
                $this->db->where_in('YEAR(date)', $data['years']);
            } else {
                $this->db->where('YEAR(date)', date('Y'));
            }

            if (!$has_permission_view) {
                $whereUser = $noPermissionsQuery;
                $this->db->where('(' . $whereUser . ')');
            }

            $invoices = $this->db->get()->result_array();
            foreach ($invoices as $invoice) {
                if ($i == 1) {
                    $result['due'][] = $invoice['outstanding'];
                } elseif ($i == 2) {
                    $result['paid'][] = $invoice['total_paid'];
                } elseif ($i == 3) {
                    $result['overdue'][] = $invoice['total'];
                }
            }
        }
       
        $currency             = get_currency($currencyid);
        $result['due']        = array_sum($result['due']);
        $result['paid']       = array_sum($result['paid']);
        $result['overdue']    = array_sum($result['overdue']);
        $result['currency']   = $currency;
        $result['currencyid'] = $currencyid;

        return $result;
    }
  

 
}