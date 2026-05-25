<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Proposals extends REST_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('proposals_model');
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

		$this->form_validation->set_rules('subject', 'Subject', 'trim|required|max_length[191]');
		$this->form_validation->set_rules('rel_type', 'Rel Type', 'trim|required|in_list[lead,customer]');
		$this->form_validation->set_rules('rel_id', 'Rel Id', 'trim|required|greater_than[0]');
		$this->form_validation->set_rules('newitems[]', 'Items', 'required');

		$data = $this->input->post();

		$currencyid = 0;
		if ($data['rel_type'] == 'customer') {
			$client = $this->clients_model->get($data['rel_id']);
			if (empty($client)) {
				$message = array(
					'status' => FALSE,
					'error' => 'rel_id',
					'message' => 'Customer not exists [ID: ' . $_POST['rel_id'] . ']'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
			$currencyid = $this->clients_model->get_customer_default_currency($_POST['rel_id']);
		} else {
			$client = $this->leads_model->get($data['rel_id']);
			if (empty($client)) {
				$message = array(
					'status' => FALSE,
					'error' => 'rel_id',
					'message' => 'Lead not exists [ID: ' . $_POST['rel_id'] . ']'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}

		// Initialize subtotal and total variables
		$subtotal = 0;
		foreach ($data['newitems'] as $key => $item) {
			$data['newitems'][$key]['long_description'] = '';
			$data['newitems'][$key]['order'] = $key;
			$data['newitems'][$key]['unit'] = '';
			$itemSubtotal = $item['qty'] * $item['rate'];
			$subtotal += $itemSubtotal;
		}

		$this->load->model('currencies_model');
		$base_currency     = $this->currencies_model->get_base_currency()->id;
		
		$data['date'] = _d(date('Y-m-d'));
		$data['subtotal'] = $subtotal;
		$data['total'] = $subtotal;
		$data['address'] = $client->address ?? "";
		$data['city'] = $client->city ?? "";
		$data['state'] = $client->state ?? "";
		$data['country'] = $client->country ?? "";
		$data['zip'] = $client->zip ?? "";
		$data['currency'] = ($currencyid == 0 ? $base_currency : $currencyid);
		$data['email'] = $client->email;
		$data['proposal_to'] = $client->email;
		$data['status'] = 6;

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
	public function send_to_email_post($id)
	{
		$canView = user_can_view_proposal($id);
		if (!$canView) {
			access_denied('proposals');
		} else {
			if (!has_permission('proposals', '', 'view') && !has_permission('proposals', '', 'view_own') && $canView == false) {
				access_denied('proposals');
			}
		}

		if ($this->input->post()) {

			$success = $this->proposals_model->send_proposal_to_email(
				$id,
				$this->input->post('attach_pdf'),
				$this->input->post('cc')
			);

			if ($success) {
				$message = array(
					'status' => TRUE,
					'message' => _l('proposal_sent_to_email_success'),
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => _l('proposal_sent_to_email_fail')
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}
}
