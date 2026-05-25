<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Clients_api_model extends App_Model
{
    private $contact_columns;

    public function __construct()
    {
        parent::__construct();

        $this->contact_columns = hooks()->apply_filters('contact_columns', ['firstname', 'lastname', 'email', 'phonenumber', 'title', 'password', 'send_set_password_email', 'donotsendwelcomeemail', 'permissions', 'direction', 'invoice_emails', 'estimate_emails', 'credit_note_emails', 'contract_emails', 'task_emails', 'project_emails', 'ticket_emails', 'is_primary']);

        $this->load->model(['client_vault_entries_model', 'client_groups_model', 'statement_model', 'authentication_model']);
    }

    /**
     * Get client object based on passed clientid if not passed clientid return array of all clients
     * @param  mixed $id    client id
     * @param  array  $where
     * @return mixed
     */
    public function get($id = '', $where = [], $args = ['limit' => null, 'offset' => null, 'filters' => "", 'sort' => ['sort_by' => 'company', 'order' => 'desc']])
    {
        $this->db->select(implode(',', prefixed_table_fields_array(db_prefix() . 'clients')) . ',
            ' . implode(',', prefixed_table_fields_array(db_prefix() . 'currencies')) . ',
            company, 
            CONCAT(' . db_prefix() . 'contacts.firstname, \' \', ' . db_prefix() . 'contacts.lastname) as name, 
            firstname, 
            ' . db_prefix() . 'contacts.email, 
            ' . db_prefix() . 'clients.phonenumber as phonenumber, 
            ' . db_prefix() . 'currencies.name as currency_name, 
            (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'customer_groups JOIN ' . db_prefix() . 'customers_groups ON ' . db_prefix() . 'customer_groups.groupid = ' . db_prefix() . 'customers_groups.id WHERE customer_id = ' . db_prefix() . 'clients.userid ORDER by name ASC) as customerGroups, 
            ' . db_prefix() . 'clients.datecreated as datecreated');

        $this->db->join(db_prefix() . 'countries', '' . db_prefix() . 'countries.country_id = ' . db_prefix() . 'clients.country', 'left');
        $this->db->join(db_prefix() . 'contacts', '' . db_prefix() . 'contacts.userid = ' . db_prefix() . 'clients.userid AND is_primary = 1', 'left');
        $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'clients.default_currency', 'left');

        if ((is_array($where) && count($where) > 0) || (is_string($where) && $where != '')) {
            $this->db->where($where);
        }

        if (!empty($args['filters'])) {
            $filters = json_decode($args['filters'], true);
            if (!empty($filters['groups_in'])) {
                $this->db->where('(' . db_prefix() . 'clients.userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_groups WHERE groupid IN (' . implode(', ', $filters['groups_in']) . ')))');
            }

            if (!empty($filters['invoice_statuses'])) {
                $this->db->where('(' . db_prefix() . 'clients.userid IN (SELECT clientid FROM ' . db_prefix() . 'invoices WHERE status IN (' . implode(', ', $filters['invoice_statuses']) . ')))');
            }

            if (!empty($filters['estimate_statuses'])) {
                $this->db->where('(' . db_prefix() . 'clients.userid IN (SELECT clientid FROM ' . db_prefix() . 'estimates WHERE status IN (' . implode(', ', $filters['estimate_statuses']) . ')))');
            }

            if (!empty($filters['project_statuses'])) {
                $this->db->where('(' . db_prefix() . 'clients.userid IN (SELECT clientid FROM ' . db_prefix() . 'projects WHERE status IN (' . implode(', ', $filters['project_statuses']) . ')))');
            }

            if (!empty($filters['proposal_statuses'])) {
                $this->db->where('(' . db_prefix() . 'clients.userid IN (SELECT rel_id FROM ' . db_prefix() . 'proposals WHERE status IN (' . implode(', ', $filters['proposal_statuses']) . ') AND rel_type="customer"))');
            }

            if (isset($filters['active'])) {
                $this->db->where(db_prefix() . 'clients.active', $filters['active']);
            }

            // Filter by proposals
            $proposalStatusIds = [];
            $this->load->model('proposals_model');
            foreach ($this->proposals_model->get_statuses() as $status) {
                if ($this->input->post('proposals_' . $status)) {
                    array_push($proposalStatusIds, $status);
                }
            }

            if (count($proposalStatusIds) > 0) {
                array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT rel_id FROM ' . db_prefix() . 'proposals WHERE status IN (' . implode(', ', $proposalStatusIds) . ') AND rel_type="customer")');
            }
        }

        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'clients.userid', $id);
            $client = $this->db->get(db_prefix() . 'clients')->row();

            if ($client && get_option('company_requires_vat_number_field') == 0) {
                $client->vat = null;
            }

            $GLOBALS['client'] = $client;

            return $client;
        }

        $this->db->order_by($args['sort']['sort_by'], $args['sort']['order']);
        if (is_numeric($args['limit']) && is_numeric($args['offset'])) {
            return $this->db->get(db_prefix() . 'clients', $args['limit'], $args['offset'])->result_array();
        }
        return $this->db->get(db_prefix() . 'clients')->result_array();
    }


    /**
     * @param array $_POST data
     * @param withContact
     *
     * @return integer Insert ID
     *
     * Add new client to database
     */
    public function add($data, $withContact = false)
    {
        $contact_data = [];
        foreach ($this->contact_columns as $field) {
            if (isset($data[$field])) {
                $contact_data[$field] = $data[$field];
                // Phonenumber is also used for the company profile
                if ($field != 'phonenumber') {
                    unset($data[$field]);
                }
            }
        }

        // From customer profile register
        if (isset($data['contact_phonenumber'])) {
            $contact_data['phonenumber'] = $data['contact_phonenumber'];
            unset($data['contact_phonenumber']);
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            unset($data['custom_fields']);
        }

        if (isset($data['groups_in'])) {
            $groups_in = $data['groups_in'];
            unset($data['groups_in']);
        }

        $data = $this->check_zero_columns($data);

        $data['datecreated'] = date('Y-m-d H:i:s');

        if (is_staff_logged_in()) {
            $data['addedfrom'] = get_staff_user_id();
        }

        // New filter action
        $data = hooks()->apply_filters('before_client_added', $data);
        $this->db->insert(db_prefix() . 'clients', $data);

        $userid = $this->db->insert_id();
        if ($userid) {
            if (isset($custom_fields)) {
                $_custom_fields = $custom_fields;
                // Possible request from the register area with 2 types of custom fields for contact and for comapny/customer
                if (count($custom_fields) == 2) {
                    unset($custom_fields);
                    $custom_fields['customers']                = $_custom_fields['customers'];
                    $contact_data['custom_fields']['contacts'] = $_custom_fields['contacts'];
                } elseif (count($custom_fields) == 1) {
                    if (isset($_custom_fields['contacts'])) {
                        $contact_data['custom_fields']['contacts'] = $_custom_fields['contacts'];
                        unset($custom_fields);
                    }
                }
                handle_custom_fields_post($userid, $custom_fields);
            }

            /**
             * Used in Import, Lead Convert, Register
             */
            if ($withContact == true) {
                $contact_id = $this->add_contact($contact_data, $userid, $withContact);
            }

            if (isset($groups_in)) {
                foreach ($groups_in as $group) {
                    $this->db->insert(db_prefix() . 'customer_groups', [
                        'customer_id' => $userid,
                        'groupid'     => $group,
                    ]);
                }
            }

            $log = 'ID: ' . $userid;

            if ($log == '' && isset($contact_id)) {
                $log = get_contact_full_name($contact_id);
            }

            $isStaff = null;

            if (!is_client_logged_in() && is_staff_logged_in()) {
                $log .= ', From Staff: ' . get_staff_user_id();
                $isStaff = get_staff_user_id();
            }

            hooks()->do_action('after_client_added', $userid);

            log_activity('New Client Created [' . $log . ']', $isStaff);
        }

        return $userid;
    }

    /**
     * Get all customer groups
     * @param  string $id
     * @return mixed
     */
    public function get_groups($id = '')
    {
        return $this->client_groups_model->get_groups($id);
    }
}
