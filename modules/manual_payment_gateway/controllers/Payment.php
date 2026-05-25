<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Payment extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('manual_payment_gateway_model');
        $this->load->model('invoices_model');
    }

    public function process($invoice_id,$invoice_hash,$amount,$payment_id = null)
    {
        check_invoice_restrictions($invoice_id, $invoice_hash);

        if(!$payment_id)
        {
            set_alert('danger', _l('mpg_not_found'));
            redirect(site_url('invoice/' . $invoice_id . '/' . $invoice_hash));
        }

        $invoice = $this->invoices_model->get($invoice_id);

        preg_match('/\d+$/', $payment_id, $matches);

        $mpg_id = isset($matches[0]) ? (int) $matches[0] : null;
        if(empty($mpg_id))
        {
            set_alert('danger', _l('mpg_not_found'));
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        $manual_payment_gateway = $this->manual_payment_gateway_model->get($mpg_id);
        $formData =  json_decode($manual_payment_gateway->form_fields,true);
        $formFields = !empty($manual_payment_gateway->form_fields) && count($formData) > 0 ? $formData : [];

        $data = [];
        $data['total'] = $amount;
        $data['invoice'] = $invoice;
        $data['invoice_hash'] = $invoice_hash;
        $data['manual_payment_gateway'] = $manual_payment_gateway;
        $data['formFields'] = $formFields;

        $this->app_css->add('mpg-css', module_dir_url(MANUAL_PAYMENT_GATEWAY_MODULE_NAME, 'assets/css/style.css'), PAYMENT_GATEWAYS_ASSETS_GROUP);
        $this->load->view('pay', $data);
    }

    public function submit_request()
    {
        $this->load->library('form_validation');

        $this->form_validation->set_rules('invoice_id', 'Invoice ID', 'required');
        $this->form_validation->set_rules('invoice_hash', 'Invoice Hash', 'required');
        $this->form_validation->set_rules('payment_id', 'Payment Mode', 'required');
        $this->form_validation->set_rules('amount', 'Amount', 'required');

        if ($this->form_validation->run() == false)
        {
            $this->session->set_flashdata('custom_alert', [
                'type' => 'danger', // Can be: success, info, warning, danger
                'message' => strip_tags(validation_errors()),
            ]);
            redirect($_SERVER['HTTP_REFERER']);
        }

        $requestData = $this->input->post();

        $invoice = $this->invoices_model->get($requestData['invoice_id']);
        $manual_payment_gateway = $this->manual_payment_gateway_model->get($requestData['payment_id']);
        $formData =  json_decode($manual_payment_gateway->form_fields,true);
        $formFields = !empty($manual_payment_gateway->form_fields) && count($formData) > 0 ? $formData : [];

        $paymentDetails = [];
        foreach($formFields as $key => $formField)
        {
            if($formField['type'] == 'file')
            {
                if(isset($_FILES[$formField['name']]))
                {
                    $file_path = $this->manual_payment_gateway_model->upload_file($formField['name']);
                    array_push($paymentDetails,[
                        'label' => $formField['label'],
                        'type' => $formField['type'],
                        'value' => $file_path,
                        'width' => $formField['width']
                    ]);
                }
            }
            else
            {
                array_push($paymentDetails,[
                    'label' => $formField['label'],
                    'type' => $formField['type'],
                    'value' => isset($requestData[$formField['name']]) ? $requestData[$formField['name']] : null,
                    'width' => $formField['width']
                ]);
            }
        }

        $data = [
            'invoice_id'   => $requestData['invoice_id'],
            'gateway_id'   => $requestData['payment_id'],
            'gateway_name' => $manual_payment_gateway->name,
            'user_id'      => $invoice->clientid,
            'details'      => json_encode($paymentDetails),
            'status'       => 2,
            'amount'       => $requestData['amount'],
            'message'      => null,
        ];

        $result = $this->manual_payment_gateway_model->save_request($data);

        if (is_staff_logged_in())
        {
            // Redirect staff or admin
            set_alert('success', _l('mpg_request_success'));
            redirect(admin_url('manual_payment_gateway/payment_requests')); // or any admin route
        }
        elseif (is_client_logged_in())
        {
            $uid = get_contact_user_id();
            $admins = $this->staff_model->get('', ['active' => 1]);

            foreach ($admins as $admin)
            {
                if (staff_can('update_requests', MANUAL_PAYMENT_GATEWAY_MODULE_NAME,$admin['staffid']))
                {
                    $notification_data = [
                        'description'     => _l('mpg_payment_for_invoice') . ' ' . format_invoice_number($invoice->id),
                        'touserid'        => $admin['staffid'],
                        'fromcompany'     => 0,
                        'fromuserid'      => $uid,
                        'link'            => 'manual_payment_gateway/payment_requests/'.$result,
                        'additional_data' => serialize([]),
                    ];

                    add_notification($notification_data);
                }
            }

            // Redirect customer
            set_alert('success', _l('mpg_request_success'));
            redirect(site_url('/')); // or any client route
        }
        else
        {
            // Not logged in, redirect to log in
            redirect(site_url('authentication/login'));
        }
    }

    public function mark_as_read()
    {
        $id =$this->input->get('id');
        $this->manual_payment_gateway_model->mark_as_read($id);
        return true;
    }
}