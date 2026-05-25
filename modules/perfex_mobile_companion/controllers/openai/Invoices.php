<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Invoices extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('invoices_model');
        $this->load->model('clients_model');
    }

    public function data_post()
    {
        $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);

        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $this->form_validation->set_data($_POST);
        $this->form_validation->set_rules('customer_id', 'Customer', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('newitems[]', 'Items', 'required');

        // // $this->form_validation->set_rules('billing_street', 'Billing Street', 'trim|required|max_length[255]');

        if ($this->form_validation->run() == FALSE) {
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $client = $this->clients_model->get($_POST['customer_id']);
            if (empty($client)) {
                $message = array(
                    'status' => FALSE,
                    'error' => 'customer_id',
                    'message' => 'Customer not exists [ID: ' . $_POST['customer_id'] . ']'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }

            // Initialize subtotal and total variables
            $subtotal = 0;
            foreach ($_POST['newitems'] as $key => $item) {
                $_POST['newitems'][$key]['long_description'] = '';
                $_POST['newitems'][$key]['order'] = $key;
                $_POST['newitems'][$key]['unit'] = '';

                $itemSubtotal = $item['qty'] * $item['rate'];
                $subtotal += $itemSubtotal;
            }

            $this->load->model('currencies_model');
            $base_currency     = $this->currencies_model->get_base_currency()->id;
            $currencyid = $this->clients_model->get_customer_default_currency($_POST['customer_id']);
            
            $invoice = [
                'clientid' => $_POST['customer_id'],
                'newitems' => $_POST['newitems'],
                'date' => _d(date('Y-m-d')),
                'duedate' => _d(date('Y-m-d', strtotime('+1 day'))),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'billing_street' => $client->billing_street,
                'number' =>str_pad(get_option('next_invoice_number'), get_option('number_padding_prefixes'), '0', STR_PAD_LEFT),
                'currency' => ($currencyid == 0 ? $base_currency : $currencyid)
            ];
            
            $id = $this->invoices_model->add($invoice);
            if ($id > 0 && !empty($id)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Invoice Added Successfully',
                    'insert_id' => $id
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Invoice Add Fail'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
