<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Estimates extends REST_Controller
{
	function __construct()
	{
		// Construct the parent class
		parent::__construct();
		$this->load->model('Customer_api_model');
		$this->load->model('estimates_model');
		$this->load->model('invoices_model');
	}

	public function data_get($id = '')
	{
		$data = $this->Api_model->get_table('estimates', $id);

		if ($data) {
			if ($id != '') {
				$data->estimate_number = format_estimate_number($id);
				$data->number                = str_pad($data->number, get_option('number_padding_prefixes'), '0', STR_PAD_LEFT);
				if ($data->invoiceid) {
					$data->invoice_number = format_invoice_number($data->invoiceid);
				}

				$data->format_organization_info = format_organization_info();
				$data->bill_to     		= format_customer_info((object) $data, 'estimate', 'billing');
				if ($data->include_shipping == 1 && $data->show_shipping_on_estimate == 1) {
					$data->ship_to     = format_customer_info((object) $data, 'estimate', 'shipping');
				}

				$data->tags = implode(',', get_tags_in($data->id, 'estimate'));
				$data->formatted_total = app_format_money($data->total, $data->currency);
				$data->currency_symbol = $this->currencies_model->get($data->currency)->symbol;
				$data->formatted_discount_total 	= app_format_money($data->discount_total, $data->currency);
				$data->formatted_total_tax 	= app_format_money($data->total_tax, $data->currency);
				$data->formatted_date = _d($data->date);
				$data->formatted_expirydate = _d($data->expirydate);



				foreach ($data->items as $key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'estimate')
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
			}
			$data = $this->Api_model->get_api_custom_data($data, "estimate", $id);
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

		$data = $this->Customer_api_model->search_client('estimates', $key);

		if ($data) {
			foreach ($data as $key => $value) {
				$data[$key]['estimate_number'] = format_estimate_number($value['id']);

				if ($data[$key]['invoiceid']) {
					$data[$key]['invoice_number'] = format_invoice_number($data[$key]['invoiceid']);
				}

				$data[$key]['formatted_total'] 	= app_format_money($value['total'], $value['currency']);
				$data[$key]['currency_symbol'] 	= $this->currencies_model->get($value['currency'])->symbol;
				$data[$key]['formatted_discount_total'] 	= app_format_money($value['discount_total'], $value['currency']);
				$data[$key]['formatted_total_tax'] 	= app_format_money($value['total_tax'], $value['currency']);
				$data[$key]['formatted_date'] 	     = _d($value['date']);
				$data[$key]['formatted_expirydate'] 	 = _d($value['expirydate']);

				$data[$key]['attachments'] 	 		 = $this->estimates_model->get_attachments($value['id']);
				$data[$key]['items']       			 = get_items_by_type('estimate', $value['id']);

				foreach ($data[$key]['items'] as $_key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'estimate')
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
			}

			$data = $this->Api_model->get_api_custom_data($data, "estimate");
			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function data_activity_get($id)
	{
		$this->load->model('estimates_model');
		$data = $this->estimates_model->get_estimate_activity($id);

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
							$additional_data[$i] = format_estimate_status($original_status, '', false);
						} else if (strpos($__data, '<new_status>') !== false) {
							$new_status = get_string_between($__data, '<new_status>', '</new_status>');
							$additional_data[$i] = format_estimate_status($new_status, '', false);
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

	public function data_delete($id = '')
	{
		$id = $this->security->xss_clean($id);
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Estimate ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('estimates_model');
			$is_exist = $this->estimates_model->get($id);
			if (is_object($is_exist)) {
				$output = $this->estimates_model->delete($id);
				if ($output === TRUE) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Estimate Deleted Successfully'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Estimate Delete Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			} else {
				$message = array(
					'status' => FALSE,
					'message' => 'Invalid Estimate ID'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_post()
	{
		$data = $this->input->post();

		$this->form_validation->set_rules('clientid', 'Customer', 'trim|required|numeric|greater_than[0]');
		$this->form_validation->set_rules('project_id', 'Project', 'trim|numeric|greater_than[0]');
		$this->form_validation->set_rules('include_shipping', 'Include Shipping', 'trim|numeric|greater_than_equal_to[0]|less_than_equal_to[1]');
		$this->form_validation->set_rules('show_shipping_on_estimate', 'Show shipping on estimate', 'trim|numeric|greater_than_equal_to[0]|less_than_equal_to[1]');
		$this->form_validation->set_rules('currency', 'Currency', 'trim|required|numeric|greater_than[0]');
		$this->form_validation->set_rules('status', 'Status', 'trim|required|numeric|greater_than[0]');
		$this->form_validation->set_rules('date', 'Estimate date', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('quantity', 'Quantity', 'trim|numeric|greater_than[0]');
		$this->form_validation->set_rules('newitems[]', 'Items', 'required');
		$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
		$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');
		$this->form_validation->set_rules('number', 'Estimate Number', 'trim|required|numeric|callback_validate_estimate_number[0]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('estimates_model');
			$data['expirydate'] = _d(date('Y-m-d', strtotime('+' . get_option('estimate_due_after') . ' DAY', strtotime(date('Y-m-d')))));
			$id = $this->estimates_model->add($data);
			if ($id > 0 && !empty($id)) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Estimate Added Successfully',
					'insert_id' => $id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Estimate Add Fail'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function validate_estimate_number($number, $estimateid)
	{
		$isedit = 'false';
		if (!empty($estimateid)) {
			$isedit = 'true';
		}
		$this->form_validation->set_message('validate_estimate_number', 'The {field} is already in use');
		$original_number = null;
		$date            = $this->input->post('date');
		if (!empty($estimateid)) {
			$data = $this->Api_model->get_table('estimates', $estimateid);
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

		if (total_rows(db_prefix() . 'estimates', [
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
				'message' => 'Invalid Estimate ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->form_validation->set_rules('clientid', 'Customer', 'trim|required|numeric|greater_than[0]');
			$this->form_validation->set_rules('project_id', 'Project', 'trim|numeric|greater_than[0]');
			$this->form_validation->set_rules('include_shipping', 'Include Shipping', 'trim|numeric|greater_than_equal_to[0]|less_than_equal_to[1]');
			$this->form_validation->set_rules('show_shipping_on_estimate', 'Show shipping on estimate', 'trim|numeric|greater_than_equal_to[0]|less_than_equal_to[1]');
			$this->form_validation->set_rules('currency', 'Currency', 'trim|required|numeric|greater_than[0]');
			$this->form_validation->set_rules('status', 'Status', 'trim|required|numeric|greater_than[0]');
			$this->form_validation->set_rules('date', 'Estimate date', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('quantity', 'Quantity', 'trim|numeric|greater_than[0]');
			$this->form_validation->set_rules('items[]', 'Items', 'required');
			$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
			$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');
			$this->form_validation->set_rules('number', 'Estimate Number', 'trim|required|numeric|callback_validate_estimate_number[' . $id . ']');
			$_POST['shipping_street'] = $_POST['shipping_street'] ?? "";

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$this->load->model('estimates_model');
				$is_exist = $this->estimates_model->get($id);
				if (!is_object($is_exist)) {
					$message = array(
						'status' => FALSE,
						'message' => 'Estimate ID Doesn\'t Not Exist.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
				if (is_object($is_exist)) {
					$data = $this->input->post();
					$data['isedit'] = "";
					$success = $this->estimates_model->update($data, $id);
					if ($success == true) {
						$message = array(
							'status' => TRUE,
							'message' => "Estimate Updated Successfully",
						);
						$this->response($message, REST_Controller::HTTP_OK);
					} else {
						// error
						$message = array(
							'status' => FALSE,
							'message' => 'Estimate Update Fail'
						);
						$this->response($message, REST_Controller::HTTP_OK);
					}
				} else {
					$message = array(
						'status' => FALSE,
						'message' => 'Invalid Estimate ID'
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
				'message' => 'Invalid Estimate ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$estimate        = $this->estimates_model->get($id);
		$estimate        = hooks()->apply_filters('before_admin_view_estimate_pdf', $estimate);
		$estimate_number = format_estimate_number($estimate->id);

		try {
			$pdf = estimate_pdf($estimate);
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

		$pdf_content = $pdf->Output(mb_strtoupper(slug_it($estimate_number)) . '.pdf', $type);

		$message = array(
			'status' => TRUE,
			'pdf' => 'data:application/pdf;base64,' . base64_encode($pdf_content)
		);
		$this->response($message, REST_Controller::HTTP_OK);
	}

	public function copy_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Estimate ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$new_id = $this->estimates_model->copy($id);
		if ($new_id > 0 && !empty($new_id)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => _l('estimate_copied_successfully'),
				'insert_id' => $new_id
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('estimate_copied_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function convert_to_invoice_get($id, $status)
	{

		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Estimate ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		if ($status == 1) {
			$draft_invoice = true;
		} else {
			$draft_invoice = false;
		}

		$this->load->model('estimates_model');
		$invoiceid = $this->estimates_model->convert_to_invoice($id, false, $draft_invoice);
		if ($invoiceid) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => _l('estimate_convert_to_invoice_successfully'),
				'insert_id' => $invoiceid
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Failed to convert estimate into invoice'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function mark_action_status_get($status, $id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Estimate ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->estimates_model->mark_action_status($status, $id, true);
		if ($success) {
			$message = array(
				'status' => TRUE,
				'message' => _l('estimate_status_changed_success'),
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('estimate_status_changed_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function convert_to_invoice_post($id)
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
			$this->load->model('invoices_model');
			$invoice_id = $this->invoices_model->add($data);
			if ($invoice_id) {
				// success

				$this->db->where('id', $id);
				$this->db->update(db_prefix() . 'estimmates', [
					'invoice_id' => $invoice_id,
					'status'     => 3,
				]);
				log_activity('Estimate Converted to Invoice [InvoiceID: ' . $invoice_id . ', EstimateID: ' . $id . ']');
				hooks()->do_action('estimate_converted_to_invoice', ['estimate_id' => $id, 'invoice_id' => $invoice_id]);

				$message = array(
					'status' => TRUE,
					'message' => _l('estimate_converted_to_invoice_success'),
					'insert_id' => $invoice_id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => _l('estimate_converted_to_invoice_fail')
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_estimate_accepted_post($action, $id)
	{
		$_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);

		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Estimate ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
		$success = $this->estimates_model->mark_action_status($action, $id, true);

		$accepted = false;
		if (is_array($success) && $success['invoiced'] == true) {
			$accepted = true;
			$invoice  = $this->invoices_model->get($success['invoiceid']);
			process_digital_signature_image($this->input->post('signature'), ESTIMATE_ATTACHMENTS_FOLDER . $id);

			$this->db->where('id', $id);
			$this->db->update(db_prefix() . 'estimates', get_acceptance_info_array());
			$message = array(
				'status' => true,
				'data' => $invoice,
				'message' => _l('clients_estimate_invoiced_successfully')
			);
			$this->response($message, REST_Controller::HTTP_OK);
			$redURL = site_url('invoice/' . $invoice->id . '/' . $invoice->hash);
		} elseif (is_array($success) && $success['invoiced'] == false || $success === true) {
			if ($action == 4) {
				$accepted = true;
				$message = array(
					'status' => true,
					'message' => _l('clients_estimate_accepted_not_invoiced')
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$message = array(
					'status' => true,
					'message' => _l('clients_estimate_declined')
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		} else {
			set_alert('warning', _l('clients_estimate_failed_action'));
			$message = array(
				'status' => false,
				'message' => _l('clients_estimate_failed_action')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
		if ($action == 4 && $accepted = true) {
			process_digital_signature_image($this->input->post('signature'), ESTIMATE_ATTACHMENTS_FOLDER . $id);

			$this->db->where('id', $id);
			$this->db->update(db_prefix() . 'estimates', get_acceptance_info_array());
		}
		if ($success) {

			$message = array(
				'status' => TRUE,
				'message' => _l('estimate_status_changed_success'),
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('estimate_status_changed_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}
}
