<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fournisseurs_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param  int|string $id
     * @return object|array|null
     */
    public function get($id = '')
    {
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'fournisseurs')->row();
        }

        $this->db->order_by('company', 'asc');

        return $this->db->get(db_prefix() . 'fournisseurs')->result_array();
    }

    /**
     * @param  array $data
     * @return int|false
     */
    public function add($data)
    {
        $data = $this->prepare($data);
        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['addedfrom'] = get_staff_user_id();

        $this->db->insert(db_prefix() . 'fournisseurs', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            log_activity('New Supplier Added [ID:' . $insert_id . ']');

            return $insert_id;
        }

        return false;
    }

    /**
     * @param  array $data
     * @param  int   $id
     * @return bool
     */
    public function update($data, $id)
    {
        $data = $this->prepare($data);

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'fournisseurs', $data);

        if ($this->db->affected_rows() > 0) {
            log_activity('Supplier Updated [ID:' . $id . ']');

            return true;
        }

        return false;
    }

    /**
     * @param  int $id
     * @return bool
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'fournisseurs');

        if ($this->db->affected_rows() > 0) {
            log_activity('Supplier Deleted [ID:' . $id . ']');

            return true;
        }

        return false;
    }

    /**
     * @param  int $id
     * @param  int $status
     * @return bool
     */
    public function change_status($id, $status)
    {
        $status = (int) $status === 1 ? 1 : 0;
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'fournisseurs', ['active' => $status]);

        return $this->db->affected_rows() > 0;
    }

    /**
     * @param  array $data
     * @return array
     */
    private function prepare(array $data): array
    {
        $allowed = [
            'company',
            'vat',
            'phonenumber',
            'email',
            'website',
            'address',
            'city',
            'state',
            'zip',
            'country',
            'contact_fullname',
            'contact_phonenumber',
            'contact_email',
            'category',
            'notes',
            'active',
        ];

        $clean = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $clean[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        $clean['active'] = isset($data['active']) ? 1 : 0;
        $clean['country'] = isset($clean['country']) && is_numeric($clean['country']) ? (int) $clean['country'] : 0;
        $clean['company'] = $clean['company'] ?? '';

        return $clean;
    }
}
