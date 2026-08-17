<?php

defined('BASEPATH') || exit('No direct script access allowed');

class VerifyPurchase extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function activate()
    {
        get_instance()->load->library('flutex_admin_api');
        $response = $this->flutex_admin_api->activate($this->input->post('module_name'),$this->input->post('purchase_code'), $this->input->post('username'));
        if ($response['status']) {
            $response['return_url'] = $this->input->post('return_url');
        }
        echo json_encode($response);
    }
}
