<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Todo_api_model extends App_Model
{
    public $todo_limit;

    public function __construct()
    {
        parent::__construct();
        $this->todo_limit = hooks()->apply_filters('todos_limit', 10);
    }

    public function setTodosLimit($limit)
    {
        $this->todo_limit = $limit;
    }

    public function getTodosLimit()
    {
        return $this->todo_limit;
    }

    /**
     * Get all user todos
     * @param  boolean $finished is finished todos or not
     * @param  mixed $page     pagination limit page
     * @return array
     */
    public function get_todo_items($finished, $page = '')
    {
        $staff_id = get_staff_user_id();
        $this->db->select();
        $this->db->from(db_prefix().'todos');
        $this->db->where('finished', $finished);
        $this->db->where('staffid', $staff_id);
        $this->db->order_by('item_order', 'asc');
        if ($page != '' && $this->input->post('todo_page')) {
            $position = ($page * $this->todo_limit);
            $this->db->limit($this->todo_limit, $position);
        } else {
            $this->db->limit($this->todo_limit);
        }
        $todos = $this->db->get()->result_array();
        // format date
        $i = 0;
        foreach ($todos as $todo) {
            $todos[$i]['dateadded']    = _dt($todo['dateadded']);
            $todos[$i]['datefinished'] = _dt($todo['datefinished']);
            $todos[$i]['description']  = check_for_links($todo['description']);
            $i++;
        }

        return $todos;
    }

    /**
     * Change todo status / finished or not finished
     * @param  mixed $id     todo id
     * @param  integer $status can be passed 1 or 0
     * @return array
     */
    public function change_todo_status($id, $status)
    {
        $staff_id = get_staff_user_id();
        
        $this->db->where('todoid', $id);
        $this->db->where('staffid', $staff_id);
        $date = date('Y-m-d H:i:s');
        $this->db->update(db_prefix().'todos', [
            'finished'     => $status,
            'datefinished' => $date,
        ]);
        if ($this->db->affected_rows() > 0) {
            return [
                'success' => true,
            ];
        }

        return [
            'success' => false,
        ];
    }

     /**
     * Delete todo
     * @param  mixed $id todo id
     * @return boolean
     */
    public function delete_todo_item($id)
    {
        $staff_id = get_staff_user_id();
        
        $this->db->where('todoid', $id);
        $this->db->where('staffid', $staff_id);
        $this->db->delete(db_prefix().'todos');
        if ($this->db->affected_rows() > 0) {
            return true;
        }

        return false;
    }
}