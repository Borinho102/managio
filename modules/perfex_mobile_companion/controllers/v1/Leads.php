<?php

defined('BASEPATH') or exit('No direct script access allowed');
require __DIR__ . '/../REST_Controller.php';

class Leads extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('Api_model');
        $this->load->model('Misc_model');
        $this->load->model('leads_model');
        $this->load->model('clients_api_model');
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->get_table('leads', $id);

        if ($data) {
            if (mb_strpos($data->name, ' ') !== false) {
                $_temp = explode(' ', $data->name);
                $data->firstname = $_temp[0];
                if (isset($_temp[2])) {
                    $data->lastname = $_temp[1] . ' ' . $_temp[2];
                } else {
                    $data->lastname = $_temp[1];
                }
            } else {
                $data->lastname = '';
                $data->firstname = $data->name;
            }

            $data->tags = implode(',', get_tags_in($data->id, 'lead'));

            $client = false;
            if (total_rows(db_prefix() . 'contacts', array('email' => $data->email)) > 0 && total_rows(db_prefix() . 'clients', array('leadid' => $data->id)) == 0) {
            } else if (total_rows(db_prefix() . 'clients', array('leadid' => $data->id))) {
                $client = true;
            }

            $data->lead_locked = false;
            if (total_rows(db_prefix() . 'clients', ['leadid' => $id]) > 0) {
                $data->lead_locked = ((!is_admin() && get_option('lead_lock_after_convert_to_customer') == 1) ? true : false);
            }

            $data->clientid = null;
            if ($client && (has_permission('customers', '', 'view') || is_customer_admin(get_client_id_by_lead_id($data->id)))) {
                $data->clientid = get_client_id_by_lead_id($data->id);
            }

            $data->lead_to_customer = false;
            if (total_rows(db_prefix() . 'clients', array('leadid' => $data->id)) == 0) {
                $data->lead_to_customer = true;
            }

            $data = $this->Api_model->get_api_custom_data($data, "leads", $id);

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

        $this->load->model('leads_model');
        $data = $this->Api_model->search('lead', $key);
        if ($data) {
            foreach ($data as $key => $value) {
                if (mb_strpos($value['name'], ' ') !== false) {
                    $_temp = explode(' ', $value['name']);
                    $data[$key]['firstname'] = $_temp[0];
                    if (isset($_temp[2])) {
                        $data[$key]['lastname'] = $_temp[1] . ' ' . $_temp[2];
                    } else {
                        $data[$key]['lastname'] = $_temp[1];
                    }
                } else {
                    $data[$key]['lastname'] = '';
                    $data[$key]['firstname'] = $value['name'];
                }

                $client = false;
                if (total_rows(db_prefix() . 'contacts', array('email' => $value['email'])) > 0 && total_rows(db_prefix() . 'clients', array('leadid' => $value['id'])) == 0) {
                } else if (total_rows(db_prefix() . 'clients', array('leadid' => $value['id']))) {
                    $client = true;
                }

                $data[$key]['lead_locked'] = false;
                if (total_rows(db_prefix() . 'clients', ['leadid' => $value['id']]) > 0) {
                    $data[$key]['lead_locked'] = ((!is_admin() && get_option('lead_lock_after_convert_to_customer') == 1) ? true : false);
                }

                $data[$key]['clientid'] = null;
                if ($client && (has_permission('customers', '', 'view') || is_customer_admin(get_client_id_by_lead_id($value['id'])))) {
                    $data[$key]['clientid'] = get_client_id_by_lead_id($value['id']);
                }

                $data[$key]['lead_to_customer'] = false;
                if (total_rows(db_prefix() . 'clients', array('leadid' => $value['id'])) == 0) {
                    $data[$key]['lead_to_customer'] = true;
                }

                $data[$key]['attachments'] = $this->leads_model->get_lead_attachments($value['id']);
            }

            $data = $this->Api_model->get_api_custom_data($data, "leads");

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function mark_as_lost_get($lost, $id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Lead ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            if ($lost == 1) {
                $success = $this->leads_model->mark_as_lost($id);
                if ($success) {
                    $message = array(
                        'status' => TRUE,
                        'message' => _l('lead_marked_as_lost')
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            } else {
                $success = $this->leads_model->unmark_as_lost($id);
                if ($success) {
                    $message = array(
                        'status' => TRUE,
                        'message' => _l('lead_unmarked_as_lost')
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            }
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Lead Update Fail.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function mark_as_junk_get($lost, $id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Lead ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            if ($lost == 1) {
                $success = $this->leads_model->mark_as_junk($id);
                if ($success) {
                    $message = array(
                        'status' => TRUE,
                        'message' => _l('lead_marked_as_junk')
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            } else {
                $success = $this->leads_model->unmark_as_junk($id);
                if ($success) {
                    $message = array(
                        'status' => TRUE,
                        'message' => _l('lead_unmarked_as_junk')
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            }
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Lead Update Fail.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_activity_get($id)
    {
        $this->load->model('leads_model');
        $data = $this->leads_model->get_lead_activity_log($id);

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
                'message' => 'No data were found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_post()
    {
        // form validation
        $this->form_validation->set_rules('name', 'Lead Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Lead Name'));
        $this->form_validation->set_rules('source', 'Source', 'trim|required', array('is_unique' => 'This %s already exists please enter another Lead source'));
        $this->form_validation->set_rules('status', 'Status', 'trim|required', array('is_unique' => 'This %s already exists please enter another Status'));
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
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
                // success
                $this->handle_lead_attachments_array($output);
                $message = array(
                    'status' => TRUE,
                    'message' => 'Lead add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Lead add fail.'
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
                'message' => 'Invalid Lead ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('leads_model');
            $output = $this->leads_model->delete($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Lead Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Lead Delete Fail.'
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
                'message' => 'Invalid Lead ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('leads_model');
            $output = $this->leads_model->update($update_data, $id);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Lead Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Lead Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function convert_to_customer_post()
    {

        $default_country  = get_option('customer_default_country');
        $data             = $this->input->post();
        $data['password'] = $this->input->post('password', false);

        $original_lead_email = $data['original_lead_email'];
        unset($data['original_lead_email']);

        if (isset($data['transfer_notes'])) {
            $notes = $this->misc_model->get_notes($data['leadid'], 'lead');
            unset($data['transfer_notes']);
        }

        if (isset($data['transfer_consent'])) {
            $this->load->model('gdpr_model');
            $consents = $this->gdpr_model->get_consents(['lead_id' => $data['leadid']]);
            unset($data['transfer_consent']);
        }

        if (isset($data['merge_db_fields'])) {
            $merge_db_fields = $data['merge_db_fields'];
            unset($data['merge_db_fields']);
        }

        if (isset($data['merge_db_contact_fields'])) {
            $merge_db_contact_fields = $data['merge_db_contact_fields'];
            unset($data['merge_db_contact_fields']);
        }

        if (isset($data['include_leads_custom_fields'])) {
            $include_leads_custom_fields = $data['include_leads_custom_fields'];
            unset($data['include_leads_custom_fields']);
        }

        if ($data['country'] == '' && $default_country != '') {
            $data['country'] = $default_country;
        }

        $data['billing_street']  = $data['address'];
        $data['billing_city']    = $data['city'];
        $data['billing_state']   = $data['state'];
        $data['billing_zip']     = $data['zip'];
        $data['billing_country'] = $data['country'];

        $data['is_primary'] = 1;
        $id                 = $this->clients_api_model->add($data, true);
        if ($id) {
            $primary_contact_id = get_primary_contact_user_id($id);

            if (isset($notes)) {
                foreach ($notes as $note) {
                    $this->db->insert(db_prefix() . 'notes', [
                        'rel_id'         => $id,
                        'rel_type'       => 'customer',
                        'dateadded'      => $note['dateadded'],
                        'addedfrom'      => $note['addedfrom'],
                        'description'    => $note['description'],
                        'date_contacted' => $note['date_contacted'],
                    ]);
                }
            }

            if (isset($consents)) {
                foreach ($consents as $consent) {
                    unset($consent['id']);
                    unset($consent['purpose_name']);
                    $consent['lead_id']    = 0;
                    $consent['contact_id'] = $primary_contact_id;
                    $this->gdpr_model->add_consent($consent);
                }
            }

            if (!has_permission('customers', '', 'view') && get_option('auto_assign_customer_admin_after_lead_convert') == 1) {
                $this->db->insert(db_prefix() . 'customer_admins', [
                    'date_assigned' => date('Y-m-d H:i:s'),
                    'customer_id'   => $id,
                    'staff_id'      => get_staff_user_id(),
                ]);
            }

            $this->leads_model->log_lead_activity($data['leadid'], 'not_lead_activity_converted', false, serialize([
                get_staff_full_name(),
            ]));

            $default_status = $this->leads_model->get_status('', [
                'isdefault' => 1,
            ]);

            $this->db->where('id', $data['leadid']);
            $this->db->update(db_prefix() . 'leads', [
                'date_converted' => date('Y-m-d H:i:s'),
                'status'         => $default_status[0]['id'],
                'junk'           => 0,
                'lost'           => 0,
            ]);
            $contact = $this->clients_model->get_contact(get_primary_contact_user_id($id));
            if ($contact->email != $original_lead_email) {
                if ($original_lead_email != '') {
                    $this->leads_model->log_lead_activity($data['leadid'], 'not_lead_activity_converted_email', false, serialize([
                        $original_lead_email,
                        $contact->email,
                    ]));
                }
            }

            if (isset($include_leads_custom_fields)) {
                foreach ($include_leads_custom_fields as $fieldid => $value) {
                    // checked don't merge
                    if ($value == 5) {
                        continue;
                    }
                    // get the value of this leads custom fiel
                    $this->db->where('relid', $data['leadid']);
                    $this->db->where('fieldto', 'leads');
                    $this->db->where('fieldid', $fieldid);
                    $lead_custom_field_value = $this->db->get(db_prefix() . 'customfieldsvalues')->row()->value;
                    // Is custom field for contact ot customer
                    if ($value == 1 || $value == 4) {
                        if ($value == 4) {
                            $field_to = 'contacts';
                        } else {
                            $field_to = 'customers';
                        }
                        $this->db->where('id', $fieldid);
                        $field = $this->db->get(db_prefix() . 'customfields')->row();
                        // check if this field exists for custom fields
                        $this->db->where('fieldto', $field_to);
                        $this->db->where('name', $field->name);
                        $exists               = $this->db->get(db_prefix() . 'customfields')->row();
                        $copy_custom_field_id = null;
                        if ($exists) {
                            $copy_custom_field_id = $exists->id;
                        } else {
                            // there is no name with the same custom field for leads at the custom side create the custom field now
                            $this->db->insert(db_prefix() . 'customfields', [
                                'fieldto'        => $field_to,
                                'name'           => $field->name,
                                'required'       => $field->required,
                                'type'           => $field->type,
                                'options'        => $field->options,
                                'display_inline' => $field->display_inline,
                                'field_order'    => $field->field_order,
                                'slug'           => slug_it($field_to . '_' . $field->name, [
                                    'separator' => '_',
                                ]),
                                'active'        => $field->active,
                                'only_admin'    => $field->only_admin,
                                'show_on_table' => $field->show_on_table,
                                'bs_column'     => $field->bs_column,
                            ]);
                            $new_customer_field_id = $this->db->insert_id();
                            if ($new_customer_field_id) {
                                $copy_custom_field_id = $new_customer_field_id;
                            }
                        }
                        if ($copy_custom_field_id != null) {
                            $insert_to_custom_field_id = $id;
                            if ($value == 4) {
                                $insert_to_custom_field_id = get_primary_contact_user_id($id);
                            }
                            $this->db->insert(db_prefix() . 'customfieldsvalues', [
                                'relid'   => $insert_to_custom_field_id,
                                'fieldid' => $copy_custom_field_id,
                                'fieldto' => $field_to,
                                'value'   => $lead_custom_field_value,
                            ]);
                        }
                    } elseif ($value == 2) {
                        if (isset($merge_db_fields)) {
                            $db_field = $merge_db_fields[$fieldid];
                            // in case user don't select anything from the db fields
                            if ($db_field == '') {
                                continue;
                            }
                            if ($db_field == 'country' || $db_field == 'shipping_country' || $db_field == 'billing_country') {
                                $this->db->where('iso2', $lead_custom_field_value);
                                $this->db->or_where('short_name', $lead_custom_field_value);
                                $this->db->or_like('long_name', $lead_custom_field_value);
                                $country = $this->db->get(db_prefix() . 'countries')->row();
                                if ($country) {
                                    $lead_custom_field_value = $country->country_id;
                                } else {
                                    $lead_custom_field_value = 0;
                                }
                            }
                            $this->db->where('userid', $id);
                            $this->db->update(db_prefix() . 'clients', [
                                $db_field => $lead_custom_field_value,
                            ]);
                        }
                    } elseif ($value == 3) {
                        if (isset($merge_db_contact_fields)) {
                            $db_field = $merge_db_contact_fields[$fieldid];
                            if ($db_field == '') {
                                continue;
                            }
                            $this->db->where('id', $primary_contact_id);
                            $this->db->update(db_prefix() . 'contacts', [
                                $db_field => $lead_custom_field_value,
                            ]);
                        }
                    }
                }
            }
            // set the lead to status client in case is not status client
            $this->db->where('isdefault', 1);
            $status_client_id = $this->db->get(db_prefix() . 'leads_status')->row()->id;
            $this->db->where('id', $data['leadid']);
            $this->db->update(db_prefix() . 'leads', [
                'status' => $status_client_id,
            ]);

            if (is_gdpr() && get_option('gdpr_after_lead_converted_delete') == '1') {
                // move all proposals to the actual customer record
                $this->db->where('rel_id', $data['leadid']);
                $this->db->where('rel_type', 'lead');
                $this->db->update('proposals', [
                    'rel_id'   => $id,
                    'rel_type' => 'customer',
                ]);

                $this->leads_model->delete($data['leadid']);

                $this->db->where('userid', $id);
                $this->db->update(db_prefix() . 'clients', ['leadid' => null]);
            }

            log_activity('Created Lead Client Profile [LeadID: ' . $data['leadid'] . ', ClientID: ' . $id . ']');
            hooks()->do_action('lead_converted_to_customer', ['lead_id' => $data['leadid'], 'customer_id' => $id]);

            $message = array(
                'status' => TRUE,
                'message' =>  _l('lead_to_client_base_converted_success'),
                'insert_id' => $id
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_attachments_post($id = '')
    {
        $uploaded = $this->handle_lead_attachments_array($id);
        if ($uploaded === TRUE) {
            // success
            $message = array(
                'status' => TRUE,
                'message' => 'Lead Attachments Uploade Successful.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => 'Lead attachments Fail to upload.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_attachments_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Lead Attachment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('leads_model');
            $output = $this->leads_model->delete_lead_attachment($id);
            if ($output === true) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Lead Attachment Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Lead Attachment Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    function handle_lead_attachments_array($leadid, $index_name = 'file')
    {
        $path     = get_upload_path_by_type('lead') . $leadid . '/';
        $CI       = &get_instance();
        $uploaded = false;

        if (isset($_FILES[$index_name]['name']) && ($_FILES[$index_name]['name'] != '' || is_array($_FILES[$index_name]['name']) && count($_FILES[$index_name]['name']) > 0)) {
            if (!is_array($_FILES[$index_name]['name'])) {
                $_FILES[$index_name]['name']     = [$_FILES[$index_name]['name']];
                $_FILES[$index_name]['type']     = [$_FILES[$index_name]['type']];
                $_FILES[$index_name]['tmp_name'] = [$_FILES[$index_name]['tmp_name']];
                $_FILES[$index_name]['error']    = [$_FILES[$index_name]['error']];
                $_FILES[$index_name]['size']     = [$_FILES[$index_name]['size']];
            }

            _file_attachments_index_fix($index_name);
            for ($i = 0; $i < count($_FILES[$index_name]['name']); $i++) {
                $tmpFilePath = $_FILES[$index_name]['tmp_name'][$i];

                // Make sure we have a filepath
                if (!empty($tmpFilePath) && $tmpFilePath != '') {
                    if (
                        _perfex_upload_error($_FILES[$index_name]['error'][$i])
                        || !_upload_extension_allowed($_FILES[$index_name]['name'][$i])
                    ) {
                        continue;
                    }

                    _maybe_create_upload_path($path);
                    $filename    = unique_filename($path, $_FILES[$index_name]['name'][$i]);
                    $newFilePath = $path . $filename;

                    // Upload the file into the temp dir
                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                        $CI = &get_instance();
                        $CI->load->model('leads_model');
                        $data   = [];
                        $data[] = [
                            'file_name' => $filename,
                            'filetype'  => $_FILES[$index_name]['type'][$i],
                        ];
                        $CI->leads_model->add_attachment_to_database($leadid, $data, false);
                        $uploaded = true;
                    }
                }
            }
        }
        return $uploaded;
    }

    public function data_pdf_get($id)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Payment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $payment        = $this->payments_model->get($id);
        $this->load->model('invoices_model');
        $payment->invoice_data = $this->invoices_model->get($payment->invoiceid);

        try {
            $pdf = payment_pdf($payment);
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

        $pdf_content = $pdf->Output(mb_strtoupper(slug_it(_l('payment') . '-' . $payment->paymentid)) . '.pdf', $type);

        $message = array(
            'status' => TRUE,
            'pdf' => 'data:application/pdf;base64,' . base64_encode($pdf_content)
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function add_note_post()
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

            if ($data['contacted_indicator'] == 'yes') {
                $contacted_date         = to_sql_date($data['custom_contact_date'], true);
                $data['date_contacted'] = $contacted_date;
            }

            unset($data['contacted_indicator']);
            unset($data['custom_contact_date']);

            $data['description'] = isset($data['lead_note_description']) ? $data['lead_note_description'] : $data['description'];

            if (isset($data['lead_note_description'])) {
                unset($data['lead_note_description']);
            }

            $id = $this->misc_model->add_note($data, $data['rel_type'], $data['rel_id']);
            if ($id > 0 && !empty($id)) {
                if (isset($contacted_date)) {
                    $this->db->where('id', $data['rel_id']);
                    $this->db->update(db_prefix() . 'leads', [
                        'lastcontact' => $contacted_date,
                    ]);
                    if ($this->db->affected_rows() > 0) {
                        $this->leads_model->log_lead_activity($data['rel_id'], 'not_lead_activity_contacted', false, serialize([
                            get_staff_full_name(get_staff_user_id()),
                            _dt($contacted_date),
                        ]));
                    }
                }

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

    public function add_activity_post()
    {
        $leadid = $this->input->post('leadid');

        $this->form_validation->set_rules('activity', 'Activity', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('leadid', 'Lead ID', 'trim|required|greater_than[0]');

        if ($this->form_validation->run() == FALSE) {
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = $this->input->post('activity');
            $aId     = $this->leads_model->log_lead_activity($leadid, $message);

            if ($aId) {
                $this->db->where('id', $aId);
                $this->db->update(db_prefix() . 'lead_activity_log', ['custom_activity' => 1]);

                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Activity Added Successfully',
                    'insert_id' => $aId
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Activity Add Fail'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
