<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Credit_notes extends REST_Controller
{
	function __construct()
	{
		// Construct the parent class
		parent::__construct();
		$this->load->model('credit_notes_model');
		$this->load->model('clients_model');
	}

	public function data_get($id = '')
	{
		// If the id parameter doesn't exist return all the
		$data = $this->Api_model->get_table('creditnotes', $id);

		if ($data) {
			if ($id != '') {
				$data->credit_note_number = format_credit_note_number($id);
				$data->number                = str_pad($data->number, get_option('number_padding_prefixes'), '0', STR_PAD_LEFT);
				$data->formatted_total = app_format_money($data->total, $data->currency);
				$data->formatted_date = _d($data->date);
				$data->formatted_adjustment = app_format_money($data->adjustment, $data->currency);
				$data->formatted_remaining_credits = app_format_money($data->remaining_credits, $data->currency);
				$data->formatted_credits_used 	   = app_format_money($data->credits_used, $data->currency);
				$data->formatted_subtotal 	   	   = app_format_money($data->subtotal, $data->currency_name);
				if (!empty($data->project_id)) {
					$data->project_name = get_project_name_by_id($data->project_id);
				}

				foreach ($data->applied_credits as $_key => $credit) {
					$data->applied_credits[$_key]['invoice_number'] = format_invoice_number($credit['invoice_id']);
					$data->applied_credits[$_key]['amount_credited'] = app_format_money($credit['amount'], $data->currency_name);
				}

				$items = get_items_table_data($data, 'credit_note', 'html', true);
				$data->taxes = $items->taxes();
				foreach ($data->taxes as $__key => $__tax) {
					$data->taxes[$__key]['formatted_taxrate'] = app_format_number($__tax['taxrate']);
					$data->taxes[$__key]['formatted_total_tax'] = app_format_money($__tax['total_tax'], $data->currency_name);
				}

				foreach ($data->items as $key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'credit_note')
						->where('itemid', $item['id'])
						->get(db_prefix() . 'item_tax')
						->result_array();
					$data->items[$key]['taxrate'] = [];
					if (!empty($item_taxes)) {
						foreach ($item_taxes as $item_tax) {
							$data->items[$key]['taxrate'][] = [
								'name' => $item_tax['taxname'],
								'taxrate' => $item_tax['taxrate'],
								'formatted_taxrate' => app_format_money($item_tax['taxrate'], $data->currency)
							];
						}
					}
				}
			}
			$data = $this->Api_model->get_api_custom_data($data, "credit_note", $id);
			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	private function total_credits_used_by_credit_note($id)
	{
		return sum_from_table(db_prefix() . 'credits', [
			'field' => 'amount',
			'where' => ['credit_id' => $id],
		]);
	}

	public function data_search_get($key = '')
	{
		$this->load->model('credit_notes_model');
		$data = $this->Api_model->search('creditnotes', $key);
		if ($data) {
			foreach ($data as $key => $value) {
				$data[$key]['credit_note_number'] 	 = format_credit_note_number($value['id']);
				$data[$key]['formatted_total'] 	     = app_format_money($value['total'], $value['currency']);
				$data[$key]['formatted_date'] 	     = _d($value['date']);
				$data[$key]['attachments'] 	 		 = $this->credit_notes_model->get_attachments($value['id']);;

				$client          = $this->clients_model->get($value['clientid']);
				$data[$key]['client'] = $client;
				if (!$data[$key]['client']) {
					$data[$key]['client']          = new stdClass();
					$data[$key]['client']->company = $value['deleted_customer_name'];
				}

				if (!empty($data[$key]['project_id'])) {
					$data[$key]['project_name'] 	 = get_project_name_by_id($data[$key]['project_id']);
				}

				$data[$key]['items']       		  = get_items_by_type('credit_note', $value['id']);

				$data[$key]['refunds']            = $this->credit_notes_model->get_refunds($value['id']);

				$data[$key]['applied_credits']    = $this->credit_notes_model->get_applied_credits($value['id']);
				$data[$key]['formatted_subtotal'] = app_format_money($value['subtotal'], $value['currency']);
				foreach ($data[$key]['applied_credits'] as $_key => $credit) {
					$data[$key]['applied_credits'][$_key]['invoice_number'] = format_invoice_number($credit['invoice_id']);
					$data[$key]['applied_credits'][$_key]['amount_credited'] = app_format_money($credit['amount'], $value['currency']);
				}

				$data[$key]['remaining_credits'] 		   = $this->credit_notes_model->total_remaining_credits_by_credit_note($value['id']);
				$data[$key]['formatted_remaining_credits'] = app_format_money($data[$key]['remaining_credits'], $value['currency']);
				$data[$key]['credits_used']      		   = $this->total_credits_used_by_credit_note($value['id']);
				$data[$key]['formatted_credits_used'] 	   = app_format_money($data[$key]['credits_used'], $value['currency']);

				$value['items'] = $data[$key]['items'];
				$items = get_items_table_data((object) $value, 'credit_note', 'html', true);

				foreach ($data[$key]['items'] as $_key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'credit_note')
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

				$data[$key]['taxes'] = $items->taxes();
				foreach ($data[$key]['taxes'] as $__key => $__tax) {
					$data[$key]['taxes'][$__key]['formatted_taxrate'] = app_format_number($__tax['taxrate']);
					$data[$key]['taxes'][$__key]['formatted_total_tax'] = app_format_money($__tax['total_tax'], $value['currency']);
				}
			}

			$data = $this->Api_model->get_api_custom_data($data, "credit_note");

			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function available_creditable_invoices_get($id)
	{
		$this->load->model('credit_notes_model');
		$data = $this->credit_notes_model->get_available_creditable_invoices($id);
		if ($data) {
			foreach ($data as $key => $value) {
				$data[$key]['invoice_number'] = format_invoice_number($value['id']);
				$data[$key]['formatted_date'] = _d($value['date']);
				$data[$key]['invoice_amount'] = app_format_money($value['total'], $value['currency_name']);
				$data[$key]['invoice_balance_due'] = app_format_money($value['total_left_to_pay'], $value['currency_name']);
			}
			$this->response($data, REST_Controller::HTTP_OK);
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK);
		}
	}

	public function apply_credits_to_invoices_post($id)
	{
		$id = $this->security->xss_clean($id);
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Credit Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('credit_notes_model');
			$is_exist = $this->credit_notes_model->get($id);
			if (is_object($is_exist)) {
				$output = false;
				foreach ($this->input->post('amount') as $invoice_id => $amount) {
					if ($this->credit_notes_model->apply_credits($id, ['amount' => $amount, 'invoice_id' => $invoice_id])) {
						update_invoice_status($invoice_id, true);
						$output = true;
					}
				}

				if ($output === TRUE) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => _l('credits_successfully_applied_to_invoices')
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Something Went Wrong.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			} else {
				$message = array(
					'status' => FALSE,
					'message' => 'Invalid Credit Note ID'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_delete($id = '')
	{
		$id = $this->security->xss_clean($id);
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Credit Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('credit_notes_model');
			$is_exist = $this->credit_notes_model->get($id);
			if (is_object($is_exist)) {
				$output = $this->credit_notes_model->delete($id);
				if ($output === TRUE) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Credit Note Deleted Successfully'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Credit Note Delete Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			} else {
				$message = array(
					'status' => FALSE,
					'message' => 'Invalid Credit Note ID'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_post()
	{
		$data = $this->input->post();

		$this->form_validation->set_rules('clientid', 'Customer', 'trim|required|numeric|greater_than[0]');
		$this->form_validation->set_rules('currency', 'Currency', 'trim|required|numeric|greater_than[0]');
		$this->form_validation->set_rules('date', 'Credit Note Date', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('newitems[]', 'Items', 'required');
		$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
		$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');
		$this->form_validation->set_rules('number', 'Credit Note Number', 'trim|required|numeric|callback_validate_creditnotes_number[0]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('credit_notes_model');
			$id = $this->credit_notes_model->add($data);
			if ($id > 0 && !empty($id)) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Credit Note Added Successfully',
					'insert_id' => $id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Credit Note Add Fail'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function create_refund_post($id = '')
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Credit Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->form_validation->set_rules('refunded_on', 'Date', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('amount', 'Amount', 'trim|required|greater_than[0]');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$this->load->model('credit_notes_model');
				$is_exist = $this->credit_notes_model->get($id);
				if (!is_object($is_exist)) {
					$message = array(
						'status' => FALSE,
						'message' => 'Credit Note ID Doesn\'t Not Exist.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}

				if (is_object($is_exist)) {
					$data                = $this->input->post();
					$data['refunded_on'] = to_sql_date($data['refunded_on']);
					$data['staff_id']    = get_staff_user_id();
					$success             = $this->credit_notes_model->create_refund($id, $data);

					if ($success) {
						$message = array(
							'status'  => TRUE,
							'message' => _l('added_successfully', _l('refund')),
						);
						$this->response($message, REST_Controller::HTTP_OK);
					} else {
						// error
						$message = array(
							'status'  => FALSE,
							'message' => 'Credit Note Refund Fail'
						);
						$this->response($message, REST_Controller::HTTP_OK);
					}
				} else {
					$message = array(
						'status'  => FALSE,
						'message' => 'Invalid Credit Note ID'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			}
		}
	}

	public function edit_refund_put($id = '')
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
				'message' => 'Invalid Refund ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->form_validation->set_rules('refunded_on', 'Date', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('amount', 'Amount', 'trim|required|greater_than[0]');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$data                = $this->input->post();
				$data['refunded_on'] = to_sql_date($data['refunded_on']);
				$success             = $this->credit_notes_model->edit_refund($id, $data);
				$message = array(
					'status'  => TRUE,
					'message' => _l('updated_successfully', _l('refund')),
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function validate_creditnotes_number($number, $credit_notes_id)
	{
		$isedit = 'false';
		if (!empty($credit_notes_id)) {
			$isedit = 'true';
		}
		$this->form_validation->set_message('validate_creditnotes_number', 'The {field} is already in use');
		$original_number = null;
		$date            = $this->input->post('date');
		if (!empty($credit_notes_id)) {
			$data = $this->Api_model->get_table('creditnotes', $credit_notes_id);
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

		if (total_rows(db_prefix() . 'creditnotes', [
			'YEAR(date)' => date('Y', strtotime(to_sql_date($date))),
			'number' => $number,
		]) > 0) {
			return FALSE;
		} else {
			return TRUE;
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
				'message' => 'Invalid Credit Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->form_validation->set_rules('clientid', 'Customer', 'trim|required|numeric|greater_than[0]');
			$this->form_validation->set_rules('currency', 'Currency', 'trim|required|numeric|greater_than[0]');
			$this->form_validation->set_rules('date', 'Credit Note Date', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('items[]', 'Items', 'required');
			$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
			$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');
			$this->form_validation->set_rules('number', 'Credit Note Number', 'trim|required|numeric|callback_validate_creditnotes_number[' . $id . ']');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$this->load->model('credit_notes_model');
				$is_exist = $this->credit_notes_model->get($id);
				if (!is_object($is_exist)) {
					$message = array(
						'status' => FALSE,
						'message' => 'Credit Note ID Doesn\'t Not Exist.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
				if (is_object($is_exist)) {
					$data = $this->input->post();
					$data['isedit'] = "";
					$success = $this->credit_notes_model->update($data, $id);
					if ($success == true) {
						$message = array(
							'status' => TRUE,
							'message' => "Credit Note Updated Successfully",
						);
						$this->response($message, REST_Controller::HTTP_OK);
					} else {
						// error
						$message = array(
							'status' => FALSE,
							'message' => 'Credit Note Update Fail'
						);
						$this->response($message, REST_Controller::HTTP_OK);
					}
				} else {
					$message = array(
						'status' => FALSE,
						'message' => 'Invalid Credit Note ID'
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
				'message' => 'Invalid Credt Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$credit_note        = $this->credit_notes_model->get($id);
		$credit_note        = hooks()->apply_filters('before_admin_view_credit_note_pdf', $credit_note);
		$credit_note_number = format_credit_note_number($credit_note->id);

		try {
			$pdf = credit_note_pdf($credit_note);
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

		$pdf_content = $pdf->Output(mb_strtoupper(slug_it($credit_note_number)) . '.pdf', $type);

		$message = array(
			'status' => TRUE,
			'pdf' => 'data:application/pdf;base64,' . base64_encode($pdf_content)
		);
		$this->response($message, REST_Controller::HTTP_OK);
	}

	public function credit_note_from_invoice_get($id)
	{

		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Invoice ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$new_id = $this->credit_notes_model->credit_note_from_invoice($id);

		if ($new_id > 0 && !empty($new_id)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => _l('new_credit_note'),
				'insert_id' => $new_id
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Failed to create credit note'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function mark_credit_note_status_get($status, $id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Credit Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->credit_notes_model->mark($id, $status);
		if ($success) {
			$message = array(
				'status' => TRUE,
				'message' => 'Credit Note Status Updated',
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Credit Note Status Update Fail',
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function delete_credit_note_applied_credit_delete($credit_id, $id, $invoice_id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Credit Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->credit_notes_model->delete_applied_credit($credit_id, $id, $invoice_id);
		$message = array(
			'status' => TRUE,
			'message' => 'Credited Invoice Deleted',
		);
		$this->response($message, REST_Controller::HTTP_OK);
	}

	public function delete_refund_delete($refund_id, $credit_note_id)
	{
		if (empty($credit_note_id) && !is_numeric($credit_note_id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Credit Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->credit_notes_model->delete_refund($refund_id, $credit_note_id);
		if ($success === TRUE) {
			$message = array(
				'status' => TRUE,
				'message' => 'Credit Note Refund Deleted',
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Failed to delete Credit Note Refund',
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function delete_attachment_delete($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Attachment ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$file = $this->misc_model->get_file($id);
		if ($file->staffid == get_staff_user_id() || is_admin()) {
			$success = $this->credit_notes_model->delete_attachment($id);
			if ($success == true) {
				$message = array(
					'status' => TRUE,
					'message' => 'Credit Note Attachment Deleted',
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}

		$message = array(
			'status' => FALSE,
			'message' => 'Failed to delete Credit Note Attachment',
		);
		$this->response($message, REST_Controller::HTTP_OK);
	}
}
