<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Leads_api_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get lead
     * @param  string $id Optional - leadid
     * @return mixed
     */
    public function get($id = '', $where = [], $limit = 10, $offset = 0, $sort = '')
    {
        $this->db->select('*,' . db_prefix() . 'leads.name, ' . db_prefix() . 'leads.id,' . db_prefix() . 'leads_status.name as status_name,' . db_prefix() . 'leads_sources.name as source_name');
        $this->db->join(db_prefix() . 'leads_status', db_prefix() . 'leads_status.id=' . db_prefix() . 'leads.status', 'left');
        $this->db->join(db_prefix() . 'leads_sources', db_prefix() . 'leads_sources.id=' . db_prefix() . 'leads.source', 'left');

        $this->db->where($where);
        $this->sort_by($sort);
        $this->db->limit($limit, $offset);
        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'leads.id', $id);
            $lead = $this->db->get(db_prefix() . 'leads')->row();
            if ($lead) {
                if ($lead->from_form_id != 0) {
                    $lead->form_data = $this->get_form([
                        'id' => $lead->from_form_id,
                    ]);
                }
                $lead->attachments = $this->get_lead_attachments($id);
                $lead->public_url  = leads_public_url($id);
            }

            return $lead;
        }

        return $this->db->get(db_prefix() . 'leads')->result_array();
    }
    
    function sort_by($sort)
    {
        switch ($sort) {
            case 'id':
                return $this->db->order_by('leads.id', 'ASC');
            case 'date_asc':
                return $this->db->order_by('leads.dateadded', 'ASC');
            case 'date_desc':
                return $this->db->order_by('leads.dateadded', 'DESC');
            case 'name_asc':
                return $this->db->order_by('leads.name', 'ASC');
            case 'name_desc':
                return $this->db->order_by('leads.name', 'DESC');
            default:
                return $this->db->order_by('leads.id', 'ASC');
        }
    }
    
    /**
     * Get lead attachments
     * @since Version 1.0.4
     * @param  mixed $id lead id
     * @return array
     */
    public function get_lead_attachments($id = '', $attachment_id = '', $where = [])
    {
        $this->db->where($where);
        $idIsHash = !is_numeric($attachment_id) && strlen($attachment_id) == 32;
        if (is_numeric($attachment_id) || $idIsHash) {
            $this->db->where($idIsHash ? 'attachment_key' : 'id', $attachment_id);

            return $this->db->get(db_prefix() . 'files')->row();
        }
        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', 'lead');
        $this->db->order_by('dateadded', 'DESC');

        return $this->db->get(db_prefix() . 'files')->result_array();
    }
}