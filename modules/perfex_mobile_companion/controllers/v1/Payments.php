<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Payments extends REST_Controller
{
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('payments_model');
        $this->load->model('invoices_model');
        $this->load->model('Api_model');
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->payment_get($id);

        if ($data) {
            if ($id != '') {
                $data->invoice_number          = format_invoice_number($data->invoiceid);
                $data->formatted_amount          = app_format_money($data->amount, $data->currency_name);
                $data->formatted_daterecorded = _d($data->daterecorded);
                $data->formatted_invoice_date = _d($data->invoice_date);
                $data->formatted_invoice_total = app_format_money($data->invoice_total, $data->currency_name);
                if ($data->invoice_status != Invoices_model::STATUS_PAID && $data->invoice_status != Invoices_model::STATUS_CANCELLED) {
                    $data->invoice_amount_due = app_format_money(get_invoice_total_left_to_pay($data->invoiceid, $data->invoice_total), $data->currency_name);
                }

                $this->load->model('payment_modes_model');
                $payment_gateways = $this->payment_modes_model->get_payment_gateways(true);

                $outputPaymentMode = $data->payment_mode_name;
                if (empty($data->paymentmodeid)) {
                    foreach ($payment_gateways as $gateway) {
                        if ($data->paymentmode == $gateway['id']) {
                            $outputPaymentMode = $gateway['name'];
                        }
                    }
                }

                if (!empty($data->paymentmethod)) {
                    $outputPaymentMode .= ' - ' . $data->paymentmethod;
                }

                $data->payment_mode_name = $outputPaymentMode;
                $data->format_organization_info = format_organization_info();

                $data->invoice = $this->invoices_model->get($data->invoiceid);
                $data->format_customer_info = format_customer_info($data->invoice, 'payment', 'billing');

                $data->currency = get_currency($data->currency_name);
            }
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_search_get($key = '')
    {
        if ($this->input->get('search')) {
            $key = $this->input->get('search');
        }

        // If the key parameter doesn't exist return all the
        $data = $this->Api_model->search('payments', $key);

        if ($data) {

            $this->load->model('payment_modes_model');
            $payment_gateways = $this->payment_modes_model->get_payment_gateways(true);

            foreach ($data as $key => $value) {
                $data[$key]['invoice_number']          = format_invoice_number($value['invoiceid']);
                $data[$key]['formatted_amount']        = app_format_money($value['amount'], $value['currency_name']);
                $data[$key]['formatted_daterecorded']  = _d($value['daterecorded']);
                $data[$key]['formatted_invoice_date']  = _d($data[$key]['invoice_date']);
                $data[$key]['formatted_invoice_total'] = app_format_money($data[$key]['invoice_total'], $data[$key]['currency_name']);

                if ($data[$key]['invoice_status'] != Invoices_model::STATUS_PAID && $data[$key]['invoice_status'] != Invoices_model::STATUS_CANCELLED) {
                    $data[$key]['invoice_amount_due']  = app_format_money(get_invoice_total_left_to_pay($data[$key]['invoiceid'], $data[$key]['invoice_total']), $data[$key]['currency_name']);
                }

                $outputPaymentMode = $value['payment_mode_name'];
                if (is_null($value['paymentmodeid'])) {
                    foreach ($payment_gateways as $gateway) {
                        if ($value['paymentmode'] == $gateway['id']) {
                            $outputPaymentMode = $gateway['name'];
                        }
                    }
                }

                if (!empty($value['paymentmethod'])) {
                    $outputPaymentMode .= ' - ' . $value['paymentmethod'];
                }

                $data[$key]['payment_mode_name']        = $outputPaymentMode;

                $data[$key]['format_organization_info'] = format_organization_info();
                $data[$key]['invoice']                  = $this->invoices_model->get($data[$key]['invoiceid']);
                $data[$key]['format_customer_info']     = format_customer_info($data[$key]['invoice'], 'payment', 'billing');
                $data[$key]['currency'] = get_currency($value['currency_name']);
            }
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_post()
    {
        // form validation
        $this->form_validation->set_rules('date', 'Payment Date', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('paymentmode', 'Payment Mode', 'trim|required');
        $this->form_validation->set_rules('amount', 'Amount Received', 'trim|required|greater_than[0]');

        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $data = $this->input->post();
            // insert data
            $this->load->model('payments_model');
            $output = $this->payments_model->add($data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Payment add successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Payment add fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_put($id = "")
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

        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Payment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->form_validation->set_rules('date', 'Payment Date', 'trim|required|max_length[255]');
            $this->form_validation->set_rules('paymentmode', 'Payment Mode', 'trim|required');
            $this->form_validation->set_rules('amount', 'Amount Received', 'trim|required|greater_than[0]');

            if ($this->form_validation->run() == FALSE) {
                $message = array(
                    'status' => FALSE,
                    'error' => $this->form_validation->error_array(),
                    'message' => validation_errors()
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $this->load->model('payments_model');
                $is_exist = $this->payments_model->get($id);
                if (!is_object($is_exist)) {
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Payment ID Doesn\'t Not Exist.'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
                if (is_object($is_exist)) {
                    $data = $this->input->post();
                    $success = $this->payments_model->update($data, $id);
                    if ($success == true) {
                        $message = array(
                            'status' => TRUE,
                            'message' => "Payment Updated Successfully",
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    } else {
                        // error
                        $message = array(
                            'status' => FALSE,
                            'message' => 'Payment Update Fail'
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    }
                } else {
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Invalid Payment ID'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            }
        }
    }

    public function data_pdf_get($id)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Payment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $payment        = $this->payments_model->get($id);
        $this->load->model('invoices_model');
        $payment->invoice_data = $this->invoices_model->get($payment->invoiceid);

        try {
            $pdf = payment_pdf($payment);
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        $type = 'D';

        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }

        if ($this->input->get('print')) {
            $type = 'I';
        }

        $pdf_content = $pdf->Output(mb_strtoupper(slug_it(_l('payment') . '-' . $payment->paymentid)) . '.pdf', $type);

        $message = array(
            'status' => TRUE,
            'pdf' => 'data:application/pdf;base64,' . base64_encode($pdf_content)
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Payment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('payments_model');
            $output = $this->payments_model->delete($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('deleted', _l('payment'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => _l('problem_deleting', _l('payment_lowercase'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
