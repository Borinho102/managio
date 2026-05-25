<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Items extends REST_Controller
{

	function __construct()
	{
		// Construct the parent class
		parent::__construct();
	}

	public function data_get($id = '')
	{
		// If the id parameter doesn't exist return all the
		$data = $this->Api_model->get_table('invoice_items', $id);

		if ($data) {
			$data = $this->Api_model->get_api_custom_data($data, "items", $id);
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
		$data = $this->Api_model->search('invoice_items', $key);
		if ($data) {
			$data = $this->Api_model->get_api_custom_data($data, "items");

			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function data_groups_get($key = '')
	{
		$this->load->model('invoice_items_model');
		$data = $this->invoice_items_model->get_groups();
		if ($data) {
			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function data_copy_get($id)
	{
		$this->load->model('invoice_items_model');
		
		$data = (array) $this->invoice_items_model->get($id);

        $id = $this->invoice_items_model->copy($data);
        if ($id) {
			$message = array(
				'status' => TRUE,
				'message' => _l('item_copy_success'),
				'insert_id' => $id
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->response([
				'status' => FALSE,
				'message' => _l('item_copy_fail')
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function data_post()
	{
		$data = $this->input->post();

		$this->form_validation->set_rules('description', 'Description', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('rate', 'Rate', 'trim|required|greater_than[0]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('invoice_items_model');

			$id = $this->invoice_items_model->add($data);
			if ($id > 0 && !empty($id)) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Item Added Successfully',
					'insert_id' => $id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Item Add Fail'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_put($id = '')
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
				'message' => 'Invalid Item ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {

			$this->form_validation->set_rules('description', 'Description', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('rate', 'Rate', 'trim|required|greater_than[0]');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {

				$update_data = $this->input->post();
				// update data
				$this->load->model('invoice_items_model');
				$update_data['itemid'] =  $id;
				$output = $this->invoice_items_model->edit($update_data);
				if ($output > 0 && !empty($output)) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Item Update Successful.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Item Update Fail.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			}
		}
	}

	public function data_delete($id = '')
	{
		$id = $this->security->xss_clean($id);
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Item ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('invoice_items_model');
			$is_exist = $this->invoice_items_model->get($id);
			if (is_object($is_exist)) {
				$output = $this->invoice_items_model->delete($id);
				if ($output === TRUE) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Item Deleted Successfully'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Item Delete Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			} else {
				$message = array(
					'status' => FALSE,
					'message' => 'Invalid Item ID'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}
}
