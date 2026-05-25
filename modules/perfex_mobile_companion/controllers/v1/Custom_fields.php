<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Custom_fields extends REST_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function data_get($type = "", $id = "")
    {
        $allowed_type = ["company", "leads", "customers", "contacts", "staff", "contracts", "tasks", "expenses", "invoice", "items", "credit_note", "estimate", "proposal", "projects", "tickets"];
        if (empty($type) || !in_array($type, $allowed_type)) {
            $this->response([
                'status' => FALSE,
                'message' => 'Not valid data'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        $fields = get_custom_fields($type);
        $customfields = [];
        foreach ($fields as $key => $field) {
            $customfields[$key] = new stdclass();
            $customfields[$key]->field_name = 'custom_fields[' . $field['fieldto'] . '][' . $field['id'] . ']';
            $customfields[$key]->custom_field_id = $field['id'];
            $customfields[$key]->label = $field['name'];
            $customfields[$key]->required = $field['required'];
            $customfields[$key]->type = $field['type'];
            $customfields[$key]->value = get_custom_field_value($id, $field['id'], $type, false);
            if (!empty($field['options'])) {
                if ($customfields[$key]->type != 'select') {
                    $customfields[$key]->value = explode(',', $customfields[$key]->value);
                    foreach ($customfields[$key]->value as $index => $value) {
                        $customfields[$key]->value[$index] = trim($value);
                    }
                }

                $customfields[$key]->options = explode(',', $field['options']);
                foreach ($customfields[$key]->options as $index => $option) {
                    $customfields[$key]->options[$index] = trim($option);
                }
            }
        }
        $this->response($customfields, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code  
    }
}
