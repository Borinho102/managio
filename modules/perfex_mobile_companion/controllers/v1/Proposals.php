<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Proposals extends REST_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('clients_model');
		$this->load->model('proposals_model');
	}

	public function data_get($id = '')
	{
		// If the id parameter doesn't exist return all the
		$where = 'rel_id =' . get_client_user_id() . ' AND rel_type ="customer"';

        $client = $this->clients_model->get(get_client_user_id());

        if (!is_null($client->leadid)) {
            $where .= ' OR rel_type="lead" AND rel_id=' . $client->leadid;
        }

        if (get_option('exclude_proposal_from_client_area_with_draft_status') == 1) {
            $where .= ' AND status != 6';
        }

        $data = $this->proposals_model->get($id, $where);

		if ($data) {
			if ($id != '') {
				$data->tags = implode(',', get_tags_in($data->id, 'proposal'));

				$data->proposal_number = format_proposal_number($id);
				$data->formatted_total = app_format_money($data->total, $data->name);
				$data->formatted_date = _d($data->date);
				$data->formatted_open_till = _d($data->open_till);

				if($data->estimate_id != NULL){
					$data->formated_estimate_id = format_estimate_number($data->estimate_id);
				}

				if($data->invoice_id != NULL) {
					$data->formated_invoice_id = format_invoice_number($data->invoice_id);
				}

				if($data->rel_type == 'customer') {
					$data->client = get_client($data->rel_id);
				}
				
				foreach ($data->items as $key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'proposal')
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

			$data = $this->Api_model->get_api_custom_data($data, "proposal", $id);
			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function data_comment_get($id = '')
	{
		$data = $this->proposals_model->get_comments($id);
	
		if ($data) {

			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function data_comment_post()
	{
		
		$_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);
		if (empty($_POST) || !isset($_POST)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Data Not Acceptable OR Not Provided'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
		$this->form_validation->set_rules('content', 'Description', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('proposalid', 'Proposal ID', 'trim|required|greater_than[0]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
		
			$data = $this->input->post();
			
			$id = $this->proposals_model->add_comment($data,true);
			if ($id > 0 && !empty($id)) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Comment Added Successfully',
					'insert_id' => $id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Comment Add Fail'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_comment_delete($id = '')
	{
		$id = $this->security->xss_clean($id);
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Comment ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// delete data
			$output = $this->proposals_model->remove_comment($id);
			if ($output === TRUE) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Comment Delete Successful.'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Comment Delete Fail.'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_comment_put($id = '')
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
				'message' => 'Invalid Comment ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {

			$this->form_validation->set_rules('content', 'Content', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('proposalid', 'Proposal ID', 'trim|required|greater_than[0]');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$data = $this->input->post();

				$success = $this->proposals_model->edit_comment($data, $id);
				if ($success == true) {
					$message = array(
						'status' => TRUE,
						'message' => "Comment Updated Successfully",
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Comment Update Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			}
		}
	}

	public function data_search_get($key = '')
	{
		$data = $this->Api_model->search('proposals', $key);
		if ($data) {
			foreach ($data as $key => $value) {
				$data[$key]['proposal_number'] = format_proposal_number($value['id']);
				$data[$key]['formatted_total'] 	= app_format_money($value['total'], $value['name']);
				$data[$key]['formatted_date'] 	     = _d($value['date']);
				$data[$key]['formatted_open_till'] 	 = _d($value['open_till']);
				$data[$key]['attachments'] 	 = $this->proposals_model->get_attachments($value['id']);
				
				if($data[$key]['estimate_id'] != NULL){
					$data[$key]['formated_estimate_id'] = format_estimate_number($data[$key]['estimate_id']);
				}

				if($data[$key]['invoice_id'] != NULL) {
					$data[$key]['formated_invoice_id'] = format_invoice_number($data[$key]['invoice_id']);
				}

				if($value['rel_type'] == 'customer') {
					$data[$key]['client'] = get_client($value['rel_id']);
				}

				$data[$key]['items'] = get_items_by_type('proposal', $value['id']);
				foreach ($data[$key]['items'] as $_key => $item) {

					$item_taxes = $this->db
						->where('rel_type', 'proposal')
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

			$data = $this->Api_model->get_api_custom_data($data, "proposal");

			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	public function convert_to_estimate_post($id) {
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
			$estimate_id = $this->estimates_model->add($data);
			if ($estimate_id) {
				$this->db->where('id', $id);
                $this->db->update(db_prefix() . 'proposals', [
                    'estimate_id' => $estimate_id,
                    'status'      => 3,
                ]);
                log_activity('Proposal Converted to Estimate [EstimateID: ' . $estimate_id . ', ProposalID: ' . $id . ']');

                hooks()->do_action('proposal_converted_to_estimate', ['proposal_id' => $id, 'estimate_id' => $estimate_id]);

				// success
				$message = array(
					'status' => TRUE,
					'message' => _l('proposal_converted_to_estimate_success'),
					'insert_id' => $estimate_id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => _l('proposal_converted_to_estimate_fail')
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function convert_to_invoice_post($id) {
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
                $this->db->update(db_prefix() . 'proposals', [
                    'invoice_id' => $invoice_id,
                    'status'     => 3,
                ]);
                log_activity('Proposal Converted to Invoice [InvoiceID: ' . $invoice_id . ', ProposalID: ' . $id . ']');
                hooks()->do_action('proposal_converted_to_invoice', ['proposal_id' => $id, 'invoice_id' => $invoice_id]);
                
				$message = array(
					'status' => TRUE,
					'message' => _l('proposal_converted_to_invoice_success'),
					'insert_id' => $invoice_id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => _l('proposal_converted_to_invoice_fail')
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_pdf_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Proposal ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$proposal        = $this->proposals_model->get($id);
		$proposal        = hooks()->apply_filters('before_admin_view_proposal_pdf', $proposal);
		$proposal_number = format_proposal_number($proposal->id);

		try {
			$pdf = proposal_pdf($proposal);
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

		$pdf_content = $pdf->Output(mb_strtoupper(slug_it($proposal_number)) . '.pdf', $type);

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
				'message' => 'Invalid Proposal ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$is_exist = $this->proposals_model->get($id);
			if (is_object($is_exist)) {
				$output = $this->proposals_model->delete($id);
				if ($output === TRUE) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Proposal Deleted Successfully'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Proposal Delete Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			} else {
				$message = array(
					'status' => FALSE,
					'message' => 'Invalid Proposal ID'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
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
			$success = $this->proposals_model->delete_attachment($id);
			if ($success == true) {
				$message = array(
					'status' => TRUE,
					'message' => 'Proposal Attachment Deleted',
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}

		$message = array(
			'status' => FALSE,
			'message' => 'Failed to delete Proposal Attachment',
		);
		$this->response($message, REST_Controller::HTTP_OK);
	}

	public function data_post()
	{
		$data = $this->input->post();

		$this->form_validation->set_rules('subject', 'Subject', 'trim|required|max_length[191]');
		$this->form_validation->set_rules('rel_type', 'Rel Type', 'trim|required|in_list[lead,customer]');
		$this->form_validation->set_rules('rel_id', 'Rel Id', 'trim|required|greater_than[0]');
		$this->form_validation->set_rules('proposal_to', 'Proposal to', 'trim|required|max_length[191]');
		$this->form_validation->set_rules('email', 'Email', 'trim|valid_email|required|max_length[150]');
		$this->form_validation->set_rules('newitems[]', 'Items', 'required');
		$this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('date', 'date', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('status', 'Status', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
		$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');
		$data['address'] = $data['address'] ?? "";

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$data['open_till'] = _d(date('Y-m-d', strtotime('+' . get_option('proposal_due_after') . ' DAY', strtotime(date('Y-m-d')))));

			$id = $this->proposals_model->add($data);
			if ($id > 0 && !empty($id)) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Proposal Added Successfully',
					'insert_id' => $id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Proposal Add Fail'
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
				'message' => 'Invalid Proposal ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->form_validation->set_rules('subject', 'Subject', 'trim|required|max_length[191]');
			$this->form_validation->set_rules('rel_type', 'Rel Type', 'trim|required|in_list[lead,customer]');
			$this->form_validation->set_rules('rel_id', 'Rel Id', 'trim|required|greater_than[0]');
			$this->form_validation->set_rules('proposal_to', 'Proposal to', 'trim|required|max_length[191]');
			$this->form_validation->set_rules('email', 'Email', 'trim|valid_email|required|max_length[150]');
			$this->form_validation->set_rules('items[]', 'Items', 'required');
			$this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('status', 'Status', 'trim|required|numeric|greater_than[0]');
			$this->form_validation->set_rules('date', 'date', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('subtotal', 'Sub Total', 'trim|required|decimal|greater_than[0]');
			$this->form_validation->set_rules('total', 'Total', 'trim|required|decimal|greater_than[0]');
			$_POST['address'] = $_POST['address'] ?? "";

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$is_exist = $this->proposals_model->get($id);
				if (!is_object($is_exist)) {
					$message = array(
						'status' => FALSE,
						'message' => 'Proposal ID Doesn\'t Not Exist.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
				if (is_object($is_exist)) {
					$data = $this->input->post();
					$data['isedit'] = "";
					$success = $this->proposals_model->update($data, $id);
					if ($success == true) {
						$message = array(
							'status' => TRUE,
							'message' => "Proposal Updated Successfully",
						);
						$this->response($message, REST_Controller::HTTP_OK);
					} else {
						// error
						$message = array(
							'status' => FALSE,
							'message' => 'Proposal Update Fail'
						);
						$this->response($message, REST_Controller::HTTP_OK);
					}
				} else {
					$message = array(
						'status' => FALSE,
						'message' => 'Invalid Proposal ID'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			}
		}
	}

	public function copy_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Proposal ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$new_id = $this->proposals_model->copy($id);
		if ($new_id > 0 && !empty($new_id)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => _l('proposal_copy_success'),
				'insert_id' => $new_id
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('proposal_copy_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function mark_action_status_get($status, $id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Proposal ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->proposals_model->mark_action_status($status, $id,true);
		if ($success) {
			$message = array(
				'status' => TRUE,
				'message' => _l('proposal_status_changed_success'),
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('proposal_status_changed_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function send_expiry_reminder_delete($id)
	{
		$canView = user_can_view_proposal($id);
        if (!$canView) {
            access_denied('proposals');
        } else {
            if (!has_permission('proposals', '', 'view') && !has_permission('proposals', '', 'view_own') && $canView == false) {
                access_denied('proposals');
            }
        }

		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Proposal ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$success = $this->proposals_model->send_expiry_reminder($id);
		if ($success) {
			$message = array(
				'status' => TRUE,
				'message' => _l('sent_expiry_reminder_success'),
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => _l('sent_expiry_reminder_fail')
			);
			$this->response($message, REST_Controller::HTTP_OK);
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
}
