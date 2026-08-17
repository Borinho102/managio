<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__.'/RestController.php';

use FlutexAdminApi\RestController;

class Expenses extends RestController
{
    protected $staffInfo;

    public function __construct()
    {
        parent::__construct();
        register_language_files('flutex_admin_api');
        load_admin_language();
        
        $this->load->helper('flutex_admin_api');
        if (!isset(isAuthorized()['status'])) {
            $this->response(isAuthorized()['response'], isAuthorized()['response_code']);
        }

        $this->staffInfo = isAuthorized();

        if (staff_cant('view', 'expenses', $this->staffInfo['data']->staff_id) && staff_cant('view_own', 'expenses', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
    }
    
    public function expenses_get()
    {
        if (!empty($this->get()) && !in_array('id', array_keys($this->get()))) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
        
        $this->load->model('expenses_model');
        $expenseId = $this->get('id');
        
        $expenseData = $this->expenses_model->get($expenseId);
        
        if (!empty($expenseData)) {
            $this->response(['message' => _l('data_retrieved_successfully'), 'data' => $expenseData], RestController::HTTP_OK);
        } else {
            $this->response(['message' => _l('data_not_found')], RestController::HTTP_NOT_FOUND);
        }
    }
    
    public function search_get()
    {
        try {
            
            if (!empty($this->get()) && !in_array('search', array_keys($this->get()))) {
                $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
            }
            
            $keySearch = $this->get('search');
            $where = '';
            
            if ($keySearch) {
                $keySearch = trim(urldecode($keySearch));
                $keySearch = $this->db->escape_like_str($keySearch);
                $where .= '(expense_name LIKE "%' . $keySearch . '%" OR note LIKE "%' . $keySearch . '%")';
            }
            
            $this->load->model('expenses_model');
            $expenseData = $this->expenses_model->get('', $where);
            
            if (!empty($expenseData)) {
                $this->response(['message' => _l('data_retrieved_successfully'), 'data' => $expenseData], RestController::HTTP_OK);
            } else {
                $this->response(['message' => _l('data_not_found')], RestController::HTTP_NOT_FOUND);
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
    }
    
    public function expenses_post()
    {
        if (staff_cant('create', 'expenses', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
        
        try {
    		$this->form_validation->set_rules('category', 'Expense Category', 'trim|required|greater_than[0]');
            $this->form_validation->set_rules('amount', 'Amount', 'trim|required');
    		$this->form_validation->set_rules('date', 'Date', 'trim|required');
    		
            if (!$this->form_validation->run()) {
                $this->response(['message' => strip_tags(validation_errors()),'error' => $this->form_validation->error_array()], RestController::HTTP_BAD_REQUEST);
            } else {
                $this->load->model('currencies_model');
                $base_currency = $this->currencies_model->get_base_currency()->id;
                $currency = $this->clients_model->get_customer_default_currency($this->input->post('clientid'));
                $data = [
                    'expense_name' => $this->input->post('expense_name'),
                    'category' =>$this->input->post('category'),
                    'date' => $this->input->post('date'),
                    'amount' => $this->input->post('amount'),
                    'clientid' => $this->input->post('clientid'),
                    'note' => $this->input->post('note'),
                    'currency' => ($currency == '0') ? $base_currency : $currency,
                    //'currency' => $this->input->post('currency'),
                    //'tax' => $this->input->post('tax'),
                    //'tax2' => $this->input->post('tax2'),
                    //'paymentmode' => $this->input->post('paymentmode'),
                    //'reference_no' => $this->input->post('reference_no'),
                ];
                
                $this->load->model('expenses_model');
                $success = $this->expenses_model->add($data);
                if ($success) {
                    $this->response(['message' => _l('expense_added_successfully')], RestController::HTTP_OK);
                } else {
                    $this->response(['message' => _l('expense_add_failed')], RestController::HTTP_NOT_FOUND);
                }
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
    }
    
    public function expenses_put()
    {
        if (staff_cant('edit', 'expenses', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
        
        try {
            
            if (!empty($this->get()) && !in_array('id', array_keys($this->get()))) {
                $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_BAD_REQUEST);
            }
            
            $expenseId = $this->get('id');
            $this->load->model('expenses_model');
            $expense = $this->expenses_model->get($expenseId);
            
            if (is_object($expense)) {
                $data = array();
                parse_str(file_get_contents('php://input'), $data);
                $success = $this->expenses_model->update($data, $expenseId);
                if ($success) {
                    $this->response(['message' => _l('expense_updated_successfully')], RestController::HTTP_OK);
                } else {
                    $this->response(['message' => _l('expense_update_failed')], RestController::HTTP_NOT_FOUND);
                }
            } else {
                $this->response(['message' => _l('invalid_expense_id')], RestController::HTTP_NOT_FOUND);
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
    }
    
    public function expenses_delete()
    {
        $expenseId = $this->get('id');
        
        if (staff_cant('delete', 'expenses', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
        
        $this->load->model('expenses_model');
        $expense = $this->expenses_model->get($expenseId);
        if (is_object($expense)) {
            $output = $this->expenses_model->delete($expenseId);
            if ($output === TRUE) {
                $this->response(['message' => _l('expense_deleted_successfully')], RestController::HTTP_OK);
            } else {
                $this->response(['message' => _l('expense_delete_failed')], RestController::HTTP_NOT_FOUND);
            }
        } else {
            $this->response(['message' => _l('invalid_expense_id')], RestController::HTTP_NOT_FOUND);
        }
    }
}