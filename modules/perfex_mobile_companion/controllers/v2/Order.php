<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../API_Controller.php';

class Order extends API_Controller
{
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('leads_model');
        $this->load->model('Api_model');
    }

    public function purchase_app()
    {
        header("Access-Control-Allow-Origin: *");

        $insert_data = [
            'name' => $this->input->post('name', TRUE),
            'source' => $this->input->post('source', TRUE),
            'status' => $this->input->post('status', TRUE),
            'assigned' => $this->Api_model->value($this->input->post('assigned', TRUE)),

            'tags' => $this->Api_model->value($this->input->post('tags', TRUE)),
            'title' => $this->Api_model->value($this->input->post('title', TRUE)),
            'email' => $this->Api_model->value($this->input->post('email', TRUE)),
            'website' => $this->Api_model->value($this->input->post('website', TRUE)),
            'phonenumber' => $this->Api_model->value($this->input->post('phonenumber', TRUE)),
            'company' => $this->Api_model->value($this->input->post('company', TRUE)),

            'address' => $this->Api_model->value($this->input->post('address', TRUE)),
            'city' => $this->Api_model->value($this->input->post('city', TRUE)),
            'state' => $this->Api_model->value($this->input->post('state', TRUE)),
            'zip' => $this->Api_model->value($this->input->post('zip', TRUE)),
            'country' => $this->Api_model->value($this->input->post('country', TRUE)),

            'lead_value' => $this->Api_model->value($this->input->post('lead_value', TRUE)),
            'default_language' => $this->Api_model->value($this->input->post('default_language', TRUE)),
            'description' => $this->Api_model->value($this->input->post('description', TRUE)),
            'custom_contact_date' => $this->Api_model->value($this->input->post('custom_contact_date', TRUE)),
            'is_public' => $this->Api_model->value($this->input->post('is_public', TRUE)),
            'contacted_today' => $this->Api_model->value($this->input->post('contacted_today', TRUE))
        ];

        if (!empty($this->input->post('custom_fields', TRUE))) {
            $insert_data['custom_fields'] = $this->Api_model->value($this->input->post('custom_fields', TRUE));
        }

        // insert data
        $this->load->model('leads_model');
        $output = $this->leads_model->add($insert_data);

        if ($output > 0 && !empty($output)) {
            $this->api_return(
                [
                    'status' => true,
                    'message' => 'Your information successfully added'
                ],
                200
            );
        } else {
            // error
            $this->api_return(
                [
                    'status' => false,
                    'message' => 'Your information not added, something server issue'
                ],
                200
            );
        }
    }
}
