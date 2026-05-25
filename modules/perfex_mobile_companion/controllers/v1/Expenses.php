<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Expenses extends REST_Controller
{
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('expenses_model');
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->get_table('expenses', $id);

        if ($data) {
            if ($id != '') {
                $data->formatted_amount = app_format_money($data->amount, $data->currency);
                $data->formatted_date = _d($data->date);

                $data->is_invoice_paid      = false;
                if ($data->billable == 1) {
                    if ($data->invoiceid != null) {
                        if (total_rows(db_prefix() . 'invoices', [
                            'id' => $data->invoiceid,
                            'status' => 2,
                        ]) > 0) {
                            $data->is_invoice_paid = true;
                        }
                    }
                }

                if ($data->recurring > 0 || $data->recurring_from != NULL) {
                    $recurring_expense = $data;
                    $show_recurring_expense_info = true;

                    if ($data->recurring_from != NULL) {
                        $recurring_expense = $this->expenses_model->get($data->recurring_from);
                        // Maybe recurring expense not longer recurring?
                        if ($recurring_expense->recurring == 0) {
                            $show_recurring_expense_info = false;
                        } else {
                            $next_recurring_date_compare = $recurring_expense->last_recurring_date;
                        }
                    } else {
                        $next_recurring_date_compare = $recurring_expense->date;
                        if ($recurring_expense->last_recurring_date) {
                            $next_recurring_date_compare = $recurring_expense->last_recurring_date;
                        }
                    }

                    if ($show_recurring_expense_info) {
                        $next_date = date('Y-m-d', strtotime('+' . $recurring_expense->repeat_every . ' ' . strtoupper($recurring_expense->recurring_type), strtotime($next_recurring_date_compare)));
                        $data->next_date = $next_date;
                    }


                    if ($data->recurring_from != NULL) {
                        $data->expense_recurring_from = _l('expense_recurring_from', $recurring_expense->category_name . (!empty($recurring_expense->expense_name) ? ' (' . $recurring_expense->expense_name . ')' : ''));
                    }
                }
            }
            $data = $this->Api_model->get_api_custom_data($data, "expenses", $id);
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

        $data = $this->Api_model->search('expenses', $key);
        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]['formatted_amount']     = app_format_money($value['amount'], $value['currency']);
                $data[$key]['formatted_date']       = _d($value['date']);
                $data[$key]['is_invoice_paid']      = false;
                if ($data[$key]['billable'] == 1) {
                    if ($value['invoiceid'] != null) {
                        if (total_rows(db_prefix() . 'invoices', [
                            'id' => $value['invoiceid'],
                            'status' => 2,
                        ]) > 0) {
                            $data[$key]['is_invoice_paid'] = true;
                        }
                    }
                }

                if ($value['recurring'] > 0 || $value['recurring_from'] != NULL) {
                    $recurring_expense = (object) $value;
                    $show_recurring_expense_info = true;

                    if ($value['recurring_from'] != NULL) {
                        $recurring_expense = $this->expenses_model->get($value['recurring_from']);
                        // Maybe recurring expense not longer recurring?
                        if ($recurring_expense->recurring == 0) {
                            $show_recurring_expense_info = false;
                        } else {
                            $next_recurring_date_compare = $recurring_expense->last_recurring_date;
                        }
                    } else {
                        $next_recurring_date_compare = $recurring_expense->date;
                        if ($recurring_expense->last_recurring_date) {
                            $next_recurring_date_compare = $recurring_expense->last_recurring_date;
                        }
                    }

                    if ($show_recurring_expense_info) {
                        $next_date = date('Y-m-d', strtotime('+' . $recurring_expense->repeat_every . ' ' . strtoupper($recurring_expense->recurring_type), strtotime($next_recurring_date_compare)));
                        $data[$key]['next_date'] = $next_date;
                    }


                    if ($value['recurring_from'] != NULL) {
                        $data[$key]['expense_recurring_from'] = _l('expense_recurring_from', $recurring_expense->category_name . (!empty($recurring_expense->expense_name) ? ' (' . $recurring_expense->expense_name . ')' : ''));
                    }
                }
            }

            $data = $this->Api_model->get_api_custom_data($data, "expenses");

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Expense ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->load->model('expenses_model');
            $is_exist = $this->expenses_model->get($id);
            if (is_object($is_exist)) {
                $output = $this->expenses_model->delete($id);
                if ($output === TRUE) {
                    // success
                    $message = array(
                        'status' => TRUE,
                        'message' => 'Expense Deleted Successfully'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                } else {
                    // error
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Expense Delete Fail'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Invalid Expense ID'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_post()
    {
        $data = $this->input->post();

        $this->form_validation->set_rules('category', 'Expense Category', 'trim|required|max_length[255]|callback_validate_category');
        $this->form_validation->set_rules('date', 'Expense date', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('category', 'Expense Category', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('date', 'Invoice date', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('amount', 'Amount', 'trim|required|decimal|greater_than[0]');
        $data['note'] = $data['note'] ?? "";

        if ($this->form_validation->run() == FALSE) {
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->load->model('expenses_model');

            if (empty($data['billable'])) {
                unset($data['billable']);
            }

            if (empty($data['send_invoice_to_customer'])) {
                unset($data['send_invoice_to_customer']);
            }

            if (empty($data['send_invoice_to_customer'])) {
                unset($data['send_invoice_to_customer']);
            }

            $id = $this->expenses_model->add($data);
            if ($id > 0 && !empty($id)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Expense added successfully.',
                    'insert_id' => $id
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Expense add fail.'
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
                'message' => 'Invalid Expense ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->form_validation->set_rules('category', 'Expense Category', 'trim|required|max_length[255]|callback_validate_category');
            $this->form_validation->set_rules('date', 'Expense date', 'trim|required|max_length[255]');
            $this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');
            $this->form_validation->set_rules('amount', 'Amount', 'trim|required|decimal|greater_than[0]');
            $data['note'] = $data['note'] ?? "";

            if ($this->form_validation->run() == FALSE) {
                $message = array(
                    'status' => FALSE,
                    'error' => $this->form_validation->error_array(),
                    'message' => validation_errors()
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $this->load->model('expenses_model');
                $is_exist = $this->expenses_model->get($id);
                if (!is_object($is_exist)) {
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Expense ID Doesn\'t Not Exist.'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
                if (is_object($is_exist)) {
                    $data = $this->input->post();

                    if (empty($data['billable'])) {
                        unset($data['billable']);
                    }

                    if (empty($data['send_invoice_to_customer'])) {
                        unset($data['send_invoice_to_customer']);
                    }

                    if (empty($data['send_invoice_to_customer'])) {
                        unset($data['send_invoice_to_customer']);
                    }

                    $success = $this->expenses_model->update($data, $id);
                    if ($success == true) {
                        $message = array(
                            'status' => TRUE,
                            'message' => "Expense Updated Successfully",
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    } else {
                        // error
                        $message = array(
                            'status' => FALSE,
                            'message' => 'Expense Update Fail'
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    }
                } else {
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Invalid Expense ID'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            }
        }
    }

    public function data_attachment_post($id = '')
    {

        if (isset($_FILES['file']) && _perfex_upload_error($_FILES['file']['error'])) {
            $message = array(
                'status' => FALSE,
                'message' => _perfex_upload_error($_FILES['file']['error'])
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $path = get_upload_path_by_type('expense') . $id . '/';
        if (isset($_FILES['file']['name'])) {
            hooks()->do_action('before_api_upload_expense_attachment', $id);
            $tmpFilePath = $_FILES['file']['tmp_name'];
            // Make sure we have a filepath
            if (!empty($tmpFilePath) && $tmpFilePath != '') {
                _maybe_create_upload_path($path);
                $filename    = $_FILES['file']['name'];
                $newFilePath = $path . $filename;
                // Upload the file into the temp dir
                if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                    $attachment   = [];
                    $attachment[] = [
                        'file_name' => $filename,
                        'filetype'  => $_FILES['file']['type'],
                    ];

                    $this->misc_model->add_attachment_to_database($id, 'expense', $attachment);
                }
            }
            $message = array(
                'status' => TRUE,
                'message' => 'Attachments Uploaded Successfully.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Expense Attachments add fail.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_attachment_delete($id = '')
    {

        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Expense ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->load->model('expenses_model');
            $is_exist = $this->expenses_model->get($id);
            if (is_object($is_exist)) {
                $output = $this->expenses_model->delete_expense_attachment($id);
                if ($output === TRUE) {
                    // success
                    $message = array(
                        'status' => TRUE,
                        'message' => _l('deleted', _l('expense_receipt'))
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                } else {
                    // error
                    $message = array(
                        'status' => FALSE,
                        'message' => _l('problem_deleting', _l('expense_receipt_lowercase'))
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Invalid Expense ID'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function validate_category($value)
    {
        $this->form_validation->set_message('validate_category', 'The {field} is not found.');

        $this->load->model('expenses_model');
        $is_exist = $this->expenses_model->get_category($value);

        if ($is_exist) {
            return TRUE;
        }
        return FALSE;
    }

    public function copy_get($id)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Expense ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $new_id = $this->expenses_model->copy($id);
        if ($new_id > 0 && !empty($new_id)) {
            // success
            $message = array(
                'status' => TRUE,
                'message' => _l('expense_copy_success'),
                'insert_id' => $new_id
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => _l('expense_copy_fail')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }
    public function convert_to_invoice_get($id)
    {
        $draft_invoice = false;
        if ($this->input->get('save_as_draft')) {
            $draft_invoice = true;
        }

        $params = [];
        if ($this->input->get('include_note') == 'true') {
            $params['include_note'] = true;
        }

        if ($this->input->get('include_name') == 'true') {
            $params['include_name'] = true;
        }

        $invoiceid = $this->expenses_model->convert_to_invoice($id, $draft_invoice, $params);
        if ($invoiceid) {
            $message = array(
                'status' => TRUE,
                'message' => 'Expense convert to invoice successfully',
                'insert_id' => $invoiceid
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Expense convert to invoice fail'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }
}
