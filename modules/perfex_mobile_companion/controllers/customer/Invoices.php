<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Invoices extends REST_Controller
{

	function __construct()
	{
		parent::__construct();
		$this->load->model('invoices_model');
		$this->load->model('Customer_api_model');
	}

	public function data_get($id = '')
	{
		$where = ['clientid' => get_client_user_id()];

		if (get_option('exclude_invoice_from_client_area_with_draft_status') == 1) {
			$where['status !='] =  Invoices_model::STATUS_CANCELLED;
			$where[' status !='] = Invoices_model::STATUS_DRAFT;
		} else {
			$where['status !='] =  Invoices_model::STATUS_CANCELLED;
		}

		$this->db->join(db_prefix() . 'clients', '' . db_prefix() . 'clients.userid = ' . db_prefix() . 'invoices.clientid', 'left');
		$data = $this->invoices_model->get($id, $where);

		if ($data) {
			if ($id != '') {
				$template_name = 'invoice_send_to_customer';
				if ($data->sent == 1) {
					$template_name = 'invoice_send_to_customer_already_sent';
				}

				$data->tags = implode(',', get_tags_in($data->id, 'invoice'));
				$data->email_data 			 = prepare_mail_preview_data($template_name, $data->clientid);
				$data->invoice_number 		 = format_invoice_number($id);
				$data->number                = str_pad($data->number, get_option('number_padding_prefixes'), '0', STR_PAD_LEFT);
				$data->allowed_payment_modes = $data->allowed_payment_modes !== null ? unserialize($data->allowed_payment_modes) : [];
				$data->credits_applied	     = total_credits_applied_to_invoice($id);
				$items = get_items_table_data($data, 'invoice', 'html', true);
				$data->taxes = $items->taxes();
				$data->invoice_recurring_invoices = $this->invoices_model->get_invoice_recurring_invoices($id);
				foreach ($data->invoice_recurring_invoices as $_key => $invoice) {
					$data->invoice_recurring_invoices[$_key]->invoice_number = format_invoice_number($invoice->id);
				}
				$data->applied_credits = $this->credit_notes_model->get_applied_invoice_credits($id);

				foreach ($data->applied_credits as $_key => $credit) {
					$data->applied_credits[$_key]['credit_note_number'] = format_credit_note_number($credit['credit_id']);
				}
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

				$data->format_organization_info = format_organization_info();
				$data->bill_to     		= format_customer_info((object) $data, 'invoice', 'billing');
				if ($data->include_shipping == 1 && $data->show_shipping_on_invoice == 1) {
					$data->ship_to     = format_customer_info((object) $data, 'invoice', 'shipping');
				}

				if (is_invoice_overdue($data)) {
					$data->get_total_days_overdue = get_total_days_overdue($data->duedate);
				}

				if ($data->recurring > 0 || $data->is_recurring_from != NULL) {

					$recurring_invoice = $data;
					$show_recurring_invoice_info = true;

					if ($data->is_recurring_from != NULL) {
						$recurring_invoice = $this->invoices_model->get($data->is_recurring_from);
						// Maybe recurring invoice not longer recurring?
						if ($recurring_invoice->recurring == 0) {
							$show_recurring_invoice_info = false;
						} else {
							$next_recurring_date_compare = $recurring_invoice->last_recurring_date;
						}
					} else {
						$next_recurring_date_compare = $recurring_invoice->date;
						if ($recurring_invoice->last_recurring_date) {
							$next_recurring_date_compare = $recurring_invoice->last_recurring_date;
						}
					}

					if ($show_recurring_invoice_info) {
						if ($recurring_invoice->custom_recurring == 0) {
							$recurring_invoice->recurring_type = 'MONTH';
						}
						$data->next_date = date('Y-m-d', strtotime('+' . $recurring_invoice->recurring . ' ' . strtoupper($recurring_invoice->recurring_type), strtotime($next_recurring_date_compare)));
					}

					$data->show_recurring_invoice_info = $show_recurring_invoice_info;
					$data->recurring_invoice = (array) $recurring_invoice;
				}
			} else {
				foreach ($data as $key => $value) {
					$template_name = 'invoice_send_to_customer';
					if ($data[$key]['sent'] == 1) {
						$template_name = 'invoice_send_to_customer_already_sent';
					}

					$data[$key]['mail_data'] 			      = prepare_mail_preview_data($template_name, $data[$key]['clientid']);
					$data[$key]['invoice_number'] 		      = format_invoice_number($value['id']);
					$value['allowed_payment_modes']           = $value['allowed_payment_modes'] !== null ? unserialize($value['allowed_payment_modes']) : [];
					$data[$key]['items']       			      = get_items_by_type('invoice', $value['id']);
					$data[$key]['total_left_to_pay']	      = get_invoice_total_left_to_pay($value['id'], $value['total']);
					$data[$key]['credits_applied'] 		      = total_credits_applied_to_invoice($value['id']);

					$data[$key]['attachments'] 	 		      = $this->invoices_model->get_attachments($value['id']);
					$data[$key]['invoice_recurring_invoices'] = $this->invoices_model->get_invoice_recurring_invoices($value['id']);

					foreach ($data[$key]['invoice_recurring_invoices'] as $_key => $invoice) {
						$data[$key]['invoice_recurring_invoices'][$_key]->invoice_number = format_invoice_number($invoice->id);
					}

					$data[$key]['applied_credits'] = $this->credit_notes_model->get_applied_invoice_credits($value['id']);

					foreach ($data[$key]['applied_credits'] as $_key => $credit) {
						$data[$key]['applied_credits'][$_key]['credit_note_number'] = format_credit_note_number($credit['credit_id']);
					}
					$value['items'] = $data[$key]['items'];
					$items = get_items_table_data((object) $value, 'invoice', 'html', true);
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

					$data[$key]['taxes'] = $items->taxes();
					$data[$key]['deleteable'] = false;
					if ((get_option('delete_only_on_last_invoice') == 1 && is_last_invoice($value['id'])) || (get_option('delete_only_on_last_invoice') == 0)) {
						if (has_permission('invoices', '', 'delete')) {
							$data[$key]['deleteable'] = true;
						}
					}

					$data[$key]['client']         			    = new stdClass();
					$data[$key]['client']->company 				= $value['company'];
					$data[$key]['client']->show_primary_contact = $value['show_primary_contact'];

					$data[$key]['format_organization_info'] = format_organization_info();
					$data[$key]['bill_to']     		= format_customer_info((object) $data[$key], 'invoice', 'billing');
					if ($value['include_shipping'] == 1 && $value['show_shipping_on_invoice'] == 1) {
						$data[$key]['ship_to']     = format_customer_info((object) $data[$key], 'invoice', 'shipping');
					}

					if (is_invoice_overdue((object) $data[$key])) {
						$data[$key]['get_total_days_overdue'] = get_total_days_overdue($value['duedate']);
					}


					if ($value['recurring'] > 0 || $value['is_recurring_from'] != NULL) {

						$recurring_invoice = (object) $value;
						$show_recurring_invoice_info = true;

						if ($value['is_recurring_from'] != NULL) {
							$recurring_invoice = $this->invoices_model->get($value['is_recurring_from']);
							if ($recurring_invoice->recurring == 0) {
								$show_recurring_invoice_info = false;
							} else {
								$next_recurring_date_compare = $recurring_invoice->last_recurring_date;
							}
						} else {
							$next_recurring_date_compare = $recurring_invoice->date;
							if ($recurring_invoice->last_recurring_date) {
								$next_recurring_date_compare = $recurring_invoice->last_recurring_date;
							}
						}

						if ($show_recurring_invoice_info) {
							if ($recurring_invoice->custom_recurring == 0) {
								$recurring_invoice->recurring_type = 'MONTH';
							}
							$data[$key]['next_date'] = date('Y-m-d', strtotime('+' . $recurring_invoice->recurring . ' ' . strtoupper($recurring_invoice->recurring_type), strtotime($next_recurring_date_compare)));
						}

						$data[$key]['show_recurring_invoice_info'] = $show_recurring_invoice_info;
						$data[$key]['recurring_invoice'] = (array) $recurring_invoice;
					}

					if ($value['project_id'] != 0) {
						$this->load->model('projects_model');
						$data[$key]['project_data'] = $this->projects_model->get($value['project_id']);
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
		if ($this->input->get('search')) {
            $key = $this->input->get('search');
        }
		$data = $this->Customer_api_model->search_client('invoices', $key);
		

		if ($data) {
			foreach ($data as $key => $value) {
				$template_name = 'invoice_send_to_customer';
				if ($data[$key]['sent'] == 1) {
					$template_name = 'invoice_send_to_customer_already_sent';
				}

				$data[$key]['mail_data'] 			 = prepare_mail_preview_data($template_name, $data[$key]['clientid']);
				$data[$key]['invoice_number'] 		 = format_invoice_number($value['id']);
				$value['allowed_payment_modes'] = $value['allowed_payment_modes'] !== null ? unserialize($value['allowed_payment_modes']) : [];
				$data[$key]['items']       			 = get_items_by_type('invoice', $value['id']);
				$data[$key]['total_left_to_pay']	 = get_invoice_total_left_to_pay($value['id'], $value['total']);
				$data[$key]['credits_applied'] 		 = total_credits_applied_to_invoice($value['id']);

				$data[$key]['attachments'] 	 		 = $this->invoices_model->get_attachments($value['id']);
				$data[$key]['invoice_recurring_invoices'] = $this->invoices_model->get_invoice_recurring_invoices($value['id']);

				foreach ($data[$key]['invoice_recurring_invoices'] as $_key => $invoice) {
					$data[$key]['invoice_recurring_invoices'][$_key]->invoice_number = format_invoice_number($invoice->id);
				}

				$data[$key]['applied_credits'] = $this->credit_notes_model->get_applied_invoice_credits($value['id']);

				foreach ($data[$key]['applied_credits'] as $_key => $credit) {
					$data[$key]['applied_credits'][$_key]['credit_note_number'] = format_credit_note_number($credit['credit_id']);
				}
				$value['items'] = $data[$key]['items'];
				$items = get_items_table_data((object) $value, 'invoice', 'html', true);
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

				$data[$key]['taxes'] = $items->taxes();
				$data[$key]['deleteable'] = false;
				if ((get_option('delete_only_on_last_invoice') == 1 && is_last_invoice($value['id'])) || (get_option('delete_only_on_last_invoice') == 0)) {
					if (has_contact_permission('invoices')) {
						$data[$key]['deleteable'] = true;
					}
				}

				$data[$key]['client']         			    = new stdClass();
				$data[$key]['client']->company 				= $value['company'];
				$data[$key]['client']->show_primary_contact = $value['show_primary_contact'];

				$data[$key]['format_organization_info'] = format_organization_info();
				$data[$key]['bill_to']     		= format_customer_info((object) $data[$key], 'invoice', 'billing');
				if ($value['include_shipping'] == 1 && $value['show_shipping_on_invoice'] == 1) {
					$data[$key]['ship_to']     = format_customer_info((object) $data[$key], 'invoice', 'shipping');
				}

				if (is_invoice_overdue((object) $data[$key])) {
					$data[$key]['get_total_days_overdue'] = get_total_days_overdue($value['duedate']);
				}


				if ($value['recurring'] > 0 || $value['is_recurring_from'] != NULL) {

					$recurring_invoice = (object) $value;
					$show_recurring_invoice_info = true;

					if ($value['is_recurring_from'] != NULL) {
						$recurring_invoice = $this->invoices_model->get($value['is_recurring_from']);
						// Maybe recurring invoice not longer recurring?
						if ($recurring_invoice->recurring == 0) {
							$show_recurring_invoice_info = false;
						} else {
							$next_recurring_date_compare = $recurring_invoice->last_recurring_date;
						}
					} else {
						$next_recurring_date_compare = $recurring_invoice->date;
						if ($recurring_invoice->last_recurring_date) {
							$next_recurring_date_compare = $recurring_invoice->last_recurring_date;
						}
					}

					if ($show_recurring_invoice_info) {
						if ($recurring_invoice->custom_recurring == 0) {
							$recurring_invoice->recurring_type = 'MONTH';
						}
						$data[$key]['next_date'] = date('Y-m-d', strtotime('+' . $recurring_invoice->recurring . ' ' . strtoupper($recurring_invoice->recurring_type), strtotime($next_recurring_date_compare)));
					}

					$data[$key]['show_recurring_invoice_info'] = $show_recurring_invoice_info;
					$data[$key]['recurring_invoice'] = (array) $recurring_invoice;
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


}
