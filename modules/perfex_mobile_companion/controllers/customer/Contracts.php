<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Contracts extends REST_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('contracts_model');
        $this->load->model('contract_types_model');
        $this->load->model('Customer_api_model');
		$this->load->helper('download');
	}

	public function data_get($id = '')
	{
		$data =  $this->contracts_model->get($id);

		if ($data) {
			if ($id != '') {

				if($data->client != NULL) {
					$data->client = get_client($data->client);
				}
				if($data->project_id != NULL) {
					$data->project = get_project($data->project_id);
				}
                if(intval($data->contract_type) > 0){
                    $data->contract_type = $this->contract_types_model->get(intval($data->contract_type));
                }
			}

			$data = $this->Api_model->get_api_custom_data($data, "contracts", $id);
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
		$data = $this->Customer_api_model->search_client('contracts', $key);
		if ($data) {
			foreach ($data as $key => $value) {
				if($data[$key]['client'] != NULL) {
					$data[$key]['client'] = get_client($data[$key]['client']);
				}
				if($data[$key]['project_id'] != NULL) {
					$data[$key]['project'] = get_project($data[$key]['project_id']);
				}
                if(intval($data[$key]['contract_type']) > 0){
                    $data[$key]['contract_type'] = $this->contract_types_model->get(intval($data[$key]['contract_type']));
                }
				
			}

			$data = $this->Api_model->get_api_custom_data($data, "contracts");

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
				'message' => 'Invalid Contract ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$is_exist = $this->contracts_model->get($id);
			if (is_object($is_exist)) {
				$output = $this->contracts_model->delete($id);
				if ($output === TRUE) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Contract Deleted Successfully'
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
					'message' => 'Invalid Contract ID'
				);
				$this->response($message, REST_Controller::HTTP_OK);
			}
		}
	}

	public function data_post()
	{
		$data = $this->input->post();

		$this->form_validation->set_rules('subject', 'Subject', 'trim|required|max_length[191]');
		$this->form_validation->set_rules('client', 'Customer Id', 'trim|required|greater_than[0]');
		$this->form_validation->set_rules('datestart', 'Start date', 'trim|required|max_length[255]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {

			$id = $this->contracts_model->add($data);
			if ($id > 0 && !empty($id)) {
				// success
				$message = array(
					'status' => TRUE,
					'message' => 'Contract Added Successfully',
					'insert_id' => $id
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				// error
				$message = array(
					'status' => FALSE,
					'message' => 'Contract Add Fail'
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
				'message' => 'Invalid Contract ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			$this->form_validation->set_rules('subject', 'Subject', 'trim|required|max_length[191]');
			$this->form_validation->set_rules('client', 'Customer Id', 'trim|required|greater_than[0]');
			$this->form_validation->set_rules('datestart', 'Start date', 'trim|required|max_length[255]');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$is_exist = $this->contracts_model->get($id);
				if (!is_object($is_exist)) {
					$message = array(
						'status' => FALSE,
						'message' => 'Contract ID Doesn\'t Not Exist.'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
				if (is_object($is_exist)) {
					$data = $this->input->post();
					$success = $this->contracts_model->update($data, $id);
					if ($success == true) {
						$message = array(
							'status' => TRUE,
							'message' => "Contract Updated Successfully",
						);
						$this->response($message, REST_Controller::HTTP_OK);
					} else {
						// error
						$message = array(
							'status' => FALSE,
							'message' => 'Contract Update Fail'
						);
						$this->response($message, REST_Controller::HTTP_OK);
					}
				} else {
					$message = array(
						'status' => FALSE,
						'message' => 'Invalid Contract ID'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			}
		}
	}

	public function contract_type_get()
	{
		// If the id parameter doesn't exist return all the
		$data = $this->contract_types_model->get();

		if ($data) {
			$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
		} else {
			$this->response([
				'status' => FALSE,
				'message' => 'No data found'
			], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
		}
	}
	public function copy_get($id = '')
	{
		// If the id parameter doesn't exist return all the
		$id = $this->contracts_model->copy($id);

		if ($id > 0 && !empty($id)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => 'Contract Copy Successfully',
				'insert_id' => $id
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Contract Copy Fail'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}
	}

	public function mark_as_signed_get($id = ''){

		$res = $this->contracts_model->mark_as_signed($id);

		if (!empty($res)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => 'Contract mark as signed successfully',
				// 'insert_id' => $id
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Contract mark as signed fail'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

	}

	public function unmark_as_signed_get($id = ''){
		
		$res = $this->contracts_model->unmark_as_signed($id);

		if (!empty($res)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => 'Contract unmark as signed successfully',
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Contract unmark as signed fail'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

	}

	public function clear_signature_get($id = ''){

		$res = $this->contracts_model->clear_signature($id);

		if (!empty($res)) {
			// success
			$message = array(
				'status' => TRUE,
				'message' => 'Contract clear signature successfully',
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			// error
			$message = array(
				'status' => FALSE,
				'message' => 'Contract clear signature fail'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

	}

	public function data_files_get($id)
    {
        $files = $this->contracts_model->get_contract_attachments('', $id);

        if ($files) {
            $this->response($files, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

	public function data_files_post($id)
    {
        if (handle_contract_attachment($id)) {
            $message = array(
                'status' => TRUE,
                'message' => 'File Uploaded Successfully.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Contract File upload fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }
	public function data_files_delete($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid File ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            if ($this->contracts_model->delete_contract_attachment($id)) {
                $message = array(
                    'status' => TRUE,
                    'message' => 'File Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'File Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

	public function data_comment_get($id = '')
	{
		$data = $this->contracts_model->get_comments($id);

		if ($data) {
			foreach ($data as $key => $value) {
				$data[$key]['profile_url'] = staff_profile_image_url($value['staffid']);
				$data[$key]['full_name'] = get_staff_full_name($value['staffid']);
				$data[$key]['time_ago'] = time_ago($value['dateadded']);
			}

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

		$data = $this->input->post();
		
		$this->form_validation->set_rules('content', 'Description', 'trim|required|max_length[255]');
		$this->form_validation->set_rules('contract_id', 'Contract ID', 'trim|required|greater_than[0]');

		if ($this->form_validation->run() == FALSE) {
			$message = array(
				'status' => FALSE,
				'error' => $this->form_validation->error_array(),
				'message' => validation_errors()
			);
			$this->response($message, REST_Controller::HTTP_OK);
		} else {
			try {
				
				$id = $this->contracts_model->add_comment($data);
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
			} catch (Exception $e) {
				// handle exception
				$message = array(
					'status' => FALSE,
					'message' => 'An error occurred: ' . $e->getMessage()
				);
				$this->response($message, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
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
			$output = $this->contracts_model->remove_comment($id);
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
			$this->form_validation->set_rules('contract_id', 'Contract ID', 'trim|required|greater_than[0]');

			if ($this->form_validation->run() == FALSE) {
				$message = array(
					'status' => FALSE,
					'error' => $this->form_validation->error_array(),
					'message' => validation_errors()
				);
				$this->response($message, REST_Controller::HTTP_OK);
			} else {
				$data = $this->input->post();

				$success = $this->contracts_model->edit_comment($data, $id);
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

	public function data_sign_contract_post($id)
	{
		$_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);
        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

		$data = $this->input->post();
			try {
				process_digital_signature_image($data['signature'], CONTRACTS_UPLOADS_FOLDER . $id);
			
				$id = 	$this->contracts_model->add_signature($id);
				if ($id > 0 && !empty($id)) {
					// success
					$message = array(
						'status' => TRUE,
						'message' => 'Signature Added Successfully',
						'insert_id' => $id
					);
					$this->response($message, REST_Controller::HTTP_OK);
				} else {
					// error
					$message = array(
						'status' => FALSE,
						'message' => 'Signature Add Fail'
					);
					$this->response($message, REST_Controller::HTTP_OK);
				}
			} catch (Exception $e) {
				// handle exception
				$message = array(
					'status' => FALSE,
					'message' => 'An error occurred: ' . $e->getMessage()
				);
				$this->response($message, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
			}
	}

	public function data_file_download_get($id, $hash)
    {
		check_contract_restrictions($id, $hash);
        $contract = $this->contracts_model->get($id);
		if (!is_client_logged_in()) {
            load_client_language($contract->client);
        }

		$pdf = contract_pdf($contract);
		
		$pdf->Output(slug_it($contract->subject . '-' . get_option('companyname')) . '.pdf', 'D');
        if ($pdf ) {
            $this->response($pdf, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

	public function data_pdf_get($id)
	{
		if (empty($id) && !is_numeric($id)) {
			$message = array(
				'status' => FALSE,
				'message' => 'Invalid Contract ID'
			);
			$this->response($message, REST_Controller::HTTP_OK);
		}

		$contract        = $this->contracts_model->get($id);

		try {
            $pdf = contract_pdf($contract);
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

		$pdf_content =  $pdf->Output(slug_it($contract->subject) . '.pdf', $type);

		$message = array(
			'status' => TRUE,
			'pdf' => 'data:application/pdf;base64,' . base64_encode($pdf_content)
		);
		$this->response($message, REST_Controller::HTTP_OK);
	}
}

