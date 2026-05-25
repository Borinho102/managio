<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Invoices extends REST_Controller
{

	function __construct()
	{
		parent::__construct();
		$this->load->model('invoices_model');
	}

	public function data_get($id = '')
	{
		// If the id parameter doesn't exist return all the
		$data = $this->Api_model->get_table('invoices', $id);

		if ($data) {
			if ($id != '') {
				$template_name = 'invoice_send_to_customer';
				if ($data->sent == 1) {
					$template_name = 'invoice_send_to_customer_already_sent';
				}

				$data->email_data = prepare_mail_preview_data($template_name, $data->clientid);

				$data->invoice_number = format_invoice_number($id);
				$data->formatted_total = app_format_money($data->total, $data->currency_name);
				$data->formatted_date = _d($data->date);
				$data->formatted_duedate = _d($data->duedate);
				$data->allowed_payment_modes = unserialize($data->allowed_payment_modes);
				$data->formatted_total_left_to_pay = app_format_money($data->total_left_to_pay, $data->currency_name);

				foreach ($data->items as $key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'invoice')
						->where('itemid', $item['id'])
						->get(db_prefix() . 'item_tax')
						->result_array();
					$data->items[$key]['taxrate'] = [];
					if (!empty($item_taxes)) {
						foreach ($item_taxes as $item_tax) {
							$data->items[$key]['taxrate'][] = [
								'name' => $item_tax['taxname'],
								'taxrate' => $item_tax['taxrate'],
							];
						}
					}
				}

				$data->deleteable = false;
				if ((get_option('delete_only_on_last_invoice') == 1 && is_last_invoice($data->id)) || (get_option('delete_only_on_last_invoice') == 0)) {
					if (has_permission('invoices', '', 'delete')) {
						$data->deleteable = true;
					}
				}
			}

			$data = $this->Api_model->get_api_custom_data($data, "invoice", $id);
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
		$data = $this->Api_model->search('invoices', $key);
		if ($data) {
			foreach ($data as $key => $value) {
				$template_name = 'invoice_send_to_customer';
				if ($data[$key]['sent'] == 1) {
					$template_name = 'invoice_send_to_customer_already_sent';
				}

				$data[$key]['mail_data'] = prepare_mail_preview_data($template_name, $data[$key]['clientid']);

				$data[$key]['invoice_number'] 			  = format_invoice_number($value['id']);
				$data[$key]['formatted_total'] 	    	  = app_format_money($value['total'], $value['currency_name']);
				$data[$key]['formatted_date'] 	    	  = _d($value['date']);
				$data[$key]['formatted_duedate'] 		  = _d($value['duedate']);
				$data[$key]['allowed_payment_modes']	  = unserialize($value['allowed_payment_modes']);
				$data[$key]['items']       			 	  = get_items_by_type('invoice', $value['id']);
				$data[$key]['total_left_to_pay']	 	  = get_invoice_total_left_to_pay($value['id'], $value['total']);
				$data[$key]['formatted_total_left_to_pay'] = app_format_money($data[$key]['total_left_to_pay'], $value['currency_name']);

				foreach ($data[$key]['items'] as $_key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'invoice')
						->where('itemid', $item['id'])
						->get(db_prefix() . 'item_tax')
						->result_array();
					$data[$key]['items'][$_key]['taxrate'] = [];
					if (!empty($item_taxes)) {
						foreach ($item_taxes as $item_tax) {
							$data[$key]['items'][$_key]['taxrate'][] = [
								'name' => $item_tax['taxname'],
								'taxrate' => $item_tax['taxrate'],
							];
						}
					}
				}

				$data[$key]['deleteable'] = false;
				if ((get_option('delete_only_on_last_invoice') == 1 && is_last_invoice($value['id'])) || (get_option('delete_only_on_last_invoice') == 0)) {
					if (has_permission('invoices', '', 'delete')) {
						$data[$key]['deleteable'] = true;
					}
				}

				if ($value['project_id'] != 0) {
					$this->load->model('projects_model');
					$data[$key]['project_data'] = $this->projects_model->get($value['project_id']);
				}
			}
			$data = $this->Api_model->get_api_custom_data($data, "invoice");

			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data was found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function data_activity_get($id)
	{

		$data = $this->invoices_model->get_invoice_activity($id);

		if ($data) {
			foreach ($data as $key => $value) {
				$_custom_data = false;

				$data[$key]['time_ago'] = time_ago($value['date']);
				$data[$key]['description'] = _l($value['description']);

				if (!empty($value['additional_data'])) {
					$additional_data = unserialize($value['additional_data']);

					$i = 0;
					foreach ($additional_data as $__data) {
						if (strpos($__data, '<original_status>') !== false) {
							$original_status = get_string_between($__data, '<original_status>', '</original_status>');
							$additional_data[$i] = format_invoice_status($original_status, '', false);
						} else if (strpos($__data, '<new_status>') !== false) {
							$new_status = get_string_between($__data, '<new_status>', '</new_status>');
							$additional_data[$i] = format_invoice_status($new_status, '', false);
						} else if (strpos($__data, '<custom_data>') !== false) {
							$_custom_data = get_string_between($__data, '<custom_data>', '</custom_data>');
							unset($additional_data[$i]);
						}
						$i++;
					}
					$_formatted_activity = _l($value['description'], $additional_data);
					if ($_custom_data !== false) {
						$_formatted_activity .= ' - ' . $_custom_data;
					}
					$data[$key]['description'] = $_formatted_activity;
				}

				if (is_numeric($value['staffid']) && $value['staffid'] != 0) {
					$data[$key]['profile_url'] = staff_profile_image_url($value['staffid']);
				}
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
		$data = $this->input->post();

		$this->form_validation->set_rules('clientid', 'Customer', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('number', 'Invoice number', 'trim|required|max_length[255]|callback_validate_invoice_number[0]');
		$this->form_validation->set_rules('date', 'Invoice date', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('newitems[]', 'Items', 'required');
		$this->form_validation->set_rules('allowed_payment_modes[]', 'Allow Payment Mode', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
		$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			unset($data['removed_items']);
			$id = $this->invoices_model->add($data);
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

	public function send_to_email_post($id)
	{
		$data = $this->input->post();

		try {
			$statementData = [];
			if ($this->input->post('attach_statement')) {
				$statementData['attach'] = true;
				$statementData['from']   = to_sql_date($this->input->post('statement_from'));
				$statementData['to']     = to_sql_date($this->input->post('statement_to'));
			}

			$success = $this->invoices_model->send_invoice_to_client(
				$id,
				'',
				$this->input->post('attach_pdf'),
				$this->input->post('cc'),
				false,
				$statementData
			);
		} catch (Exception $e) {
			$_message = $e->getMessage();
			$message = array(
				'status' => FALSE,
				'message' => $_message
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		// In case client use another language
		load_admin_language();

		if ($success) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => _l('invoice_sent_to_client_success')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' =>  _l('invoice_sent_to_client_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function data_delete($id = '')
	{
		$id = $this->security->xss_clean($id);
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$is_exist = $this->invoices_model->get($id);
			if (is_object($is_exist)) {
				$output = $this->invoices_model->delete($id);
				if ($output === TRUE) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Invoice Deleted Successfully'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Invoice Delete Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			} else {
				$message = array(
					'status' => FALSE,
					'message' => 'Invalid Invoice ID'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_activity_delete($id = '')
	{
		$id = $this->security->xss_clean($id);
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Activity ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			if (is_admin()) {
				$this->db->where('id', $id);
				$this->db->delete(db_prefix() . 'sales_activity');

				$message = array(
					'status' => TRUE,
					'message' => 'Invoice Activity Deleted Successfully'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$message = array(
					'status' => FALSE,
					'message' => 'Invoice Activity Delete Fail'
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
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->form_validation->set_rules('number', 'Invoice number', 'trim|required|max_length[255]|callback_validate_invoice_number[' . $id . ']');
			$this->form_validation->set_rules('date', 'Invoice date', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('items[]', 'Items', 'required');
			$this->form_validation->set_rules('allowed_payment_modes[]', 'Allow Payment Mode', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
			$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');
			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {

				$is_exist = $this->invoices_model->get($id);
				if (!is_object($is_exist)) {
					$message = array(
						'status' => FALSE,
						'message' => 'Invoice ID Doesn\'t Not Exist.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
				if (is_object($is_exist)) {
					$data = $this->input->post();
					$data['isedit'] = "";
					$success = $this->invoices_model->update($data, $id);
					if ($success == true) {
						$message = array(
							'status' => TRUE,
							'message' => "Invoice Updated Successfully",
						);
						$this->response($message, REST_Controller::HTTP_OK);
					} else {
						// error
						$message = array(
							'status' => FALSE,
							'message' => 'Invoice Update Fail'
						);
						$this->response($message, REST_Controller::HTTP_OK);
					}
				} else {
					$message = array(
						'status' => FALSE,
						'message' => 'Invalid Invoice ID'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			}
		}
	}

	public function validate_invoice_number($number, $invoiceid)
	{
		$isedit = 'false';
		if (!empty($invoiceid)) {
			$isedit = 'true';
		}
		$this->form_validation->set_message('validate_invoice_number', 'The {field} is already in use');
		$original_number = null;
		$date            = $this->input->post('date');
		if (!empty($invoiceid)) {
			$data = $this->Api_model->get_table('invoices', $invoiceid);
			$original_number = $data->number;
			if (empty($date)) {
				$date = $data->date;
			}
		}
		$number          = trim($number);
		$number          = ltrim($number, '0');

		if ($isedit == 'true') {
			if ($number == $original_number) {
				return TRUE;
			}
		}

		if (total_rows(db_prefix() . 'invoices', [
			'YEAR(date)' => date('Y', strtotime(to_sql_date($date))),
			'number' => $number,
		]) > 0) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	public function data_pdf_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$invoice        = $this->invoices_model->get($id);
		$invoice        = hooks()->apply_filters('before_admin_view_invoice_pdf', $invoice);
		$invoice_number = format_invoice_number($invoice->id);

		try {
			$pdf = invoice_pdf($invoice);
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

		$pdf_content = $pdf->Output(mb_strtoupper(slug_it($invoice_number)) . '.pdf', $type);

		$message = array(
			'status' => TRUE,
			'pdf' => 'data:application/pdf;base64,' . base64_encode($pdf_content)
		);
		$this->response($message, REST_Controller::HTTP_OK);
	}

	public function mark_as_sent_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->invoices_model->set_invoice_sent($id, true);
		if ($success) {
			$message = array(
				'status' => TRUE,
				'message' => _l('invoice_marked_as_sent'),
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('invoice_marked_as_sent_failed')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function mark_as_cancelled_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->invoices_model->mark_as_cancelled($id);

		if ($success) {
			$message = array(
				'status' => TRUE,
				'message' => _l('invoice_marked_as_cancelled_successfully'),
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('something_went_wrong')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function unmark_as_cancelled_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->invoices_model->unmark_as_cancelled($id);
		if ($success) {
			$message = array(
				'status' => TRUE,
				'message' => _l('invoice_unmarked_as_cancelled'),
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('something_went_wrong')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function copy_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$new_id = $this->invoices_model->copy($id);
		if ($new_id > 0 && !empty($new_id)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => _l('invoice_copy_success'),
				'insert_id' => $new_id
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('invoice_copy_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}
}
