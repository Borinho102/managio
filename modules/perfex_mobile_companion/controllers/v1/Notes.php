<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Notes extends REST_Controller
{
	function __construct()
	{
		parent::__construct();
	}

	public function data_get($id = '')
	{
		$input_data = $this->input->get();
		$this->load->model('misc_model');

		$data = $this->misc_model->get_notes($input_data['rel_id'], $input_data['rel_type']);
		if ($data) {
			foreach ($data as $key => $value) {
				$data[$key]['formatted_dateadded'] = _dt($value['dateadded']);
				$data[$key]['profile_url'] = staff_profile_image_url($value['addedfrom']);
			}

			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}

	private function __search_reminder($rel_id, $rel_type, $q)
	{
		$result = [
			'result'         => [],
			'type'           => 'reminders',
			'search_heading' => _l('reminders'),
		];

		$this->db->select(db_prefix() . 'reminders.*, firstname, lastname');
		$this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'reminders.staff');
		$this->db->from(db_prefix() . 'reminders');

		$this->db->group_start();
		$this->db->like('description', $q);
		$this->db->or_like(db_prefix() . 'reminders.id', $q);
		$this->db->group_end();

		$this->db->where('rel_id', $rel_id);
		$this->db->where('rel_type', $rel_type);

		if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
			$this->db->limit($this->input->get('limit'));
			$this->db->offset($this->input->get('offset'));
		}

		$this->db->order_by('id', 'DESC');
		$result['result'] = $this->db->get()->result_array();

		return $result['result'];
	}

	public function data_post()
	{
		$data = $this->input->post();

		$this->form_validation->set_rules('description', 'Description', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('rel_id', 'Relation ID', 'trim|required|greater_than[0]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->load->model('misc_model');

			$id = $this->misc_model->add_note($data, $data['rel_type'], $data['rel_id']);
			if ($id > 0 && !empty($id)) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Note Added Successfully',
					'insert_id' => $id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Note Add Fail'
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
				'message' => 'Invalid Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// delete data
			$this->load->model('misc_model');
			$output = $this->misc_model->delete_note($id);
			if ($output === TRUE) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Note Delete Successful.'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Note Delete Fail.'
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
				'message' => 'Invalid Note ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {

			$this->form_validation->set_rules('description', 'Description', 'trim|required|max_length[255]');
			$this->form_validation->set_rules('rel_id', 'Relation ID', 'trim|required|greater_than[0]');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$data = $this->input->post();

				$this->load->model('misc_model');
				$success = $this->misc_model->edit_note($data, $id);
				if ($success == true) {
					$message = array(
						'status' => TRUE,
						'message' => "Note Updated Successfully",
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Note Update Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			}
		}
	}
}
