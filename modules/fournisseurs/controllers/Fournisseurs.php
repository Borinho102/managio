<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Legacy Fournisseurs controller — redirects to Purchase vendors (single source of truth).
 */
class Fournisseurs extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        redirect(admin_url('purchase/vendors'));
    }

    public function fournisseur($id = '')
    {
        if ($id === '' || $id === null) {
            redirect(admin_url('purchase/vendor'));
        }

        // Prefer linked pur_vendor when migration column exists
        $CI = &get_instance();
        if ($CI->db->table_exists(db_prefix() . 'pur_vendor')
            && $CI->db->field_exists('fournisseur_id', db_prefix() . 'pur_vendor')
        ) {
            $CI->db->where('fournisseur_id', (int) $id);
            $vendor = $CI->db->get(db_prefix() . 'pur_vendor')->row();
            if ($vendor) {
                redirect(admin_url('purchase/vendor/' . $vendor->userid));
            }
        }

        redirect(admin_url('purchase/vendors'));
    }

    public function delete($id)
    {
        redirect(admin_url('purchase/vendors'));
    }

    public function change_status($id, $status)
    {
        redirect(admin_url('purchase/vendors'));
    }
}
