<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tasks_api_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get task by id
     * @param  mixed $id task id
     * @return object
     */
    public function get($where = [], $limit = 10, $offset = 0, $sort = '')
    {
        $this->db->where($where);
        $this->sort_by($sort);
        $this->db->limit($limit, $offset);
        $task = $this->db->get(db_prefix() . 'tasks')->result_array();
        return hooks()->apply_filters('get_task', $task);
    }
    
    function sort_by($sort)
    {
        switch ($sort) {
            case 'id':
                return $this->db->order_by('id', 'ASC');
            case 'date_asc':
                return $this->db->order_by('dateadded', 'ASC');
            case 'date_desc':
                return $this->db->order_by('dateadded', 'DESC');
            case 'name_asc':
                return $this->db->order_by('name', 'ASC');
            case 'name_desc':
                return $this->db->order_by('name', 'DESC');
            default:
                return $this->db->order_by('id', 'ASC');
        }
    }
}