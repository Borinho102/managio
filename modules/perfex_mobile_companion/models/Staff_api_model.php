<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Staff_api_model extends App_Model
{
    /**
     * Get staff member/s
     * @param  mixed $id Optional - staff id
     * @param  mixed $where where in query
     * @return mixed if id is passed return object else array
     */
    public function get($id = '', $where = [])
    {
        $select_str = '*,CONCAT(firstname,\' \',lastname) as full_name';

        if ($id != '' && $id == get_staff_user_id()) {
            $select_str .= ',(SELECT COUNT(*) FROM ' . db_prefix() . 'notifications WHERE touserid=' . get_staff_user_id() . ' and isread=0) as total_unread_notifications, (SELECT COUNT(*) FROM ' . db_prefix() . 'todos WHERE finished=0 AND staffid=' . get_staff_user_id() . ') as total_unfinished_todos';
        }

        $this->db->select($select_str);
        $this->db->where($where);

        if (is_numeric($id)) {
            $this->db->where('staffid', $id);
            $staff = $this->db->get(db_prefix() . 'staff')->row();

            if ($staff) {
                $staff->profile_image = staff_profile_image_url($staff->staffid);
                $staff->permissions = $this->get_staff_permissions($id);
            }
            return $staff;
        }
        $this->db->order_by('firstname', 'desc');

        return $this->db->get(db_prefix() . 'staff')->result_array();
    }

    /**
     * Get staff permissions
     * @param  mixed $id staff id
     * @return array
     */
    public function get_staff_permissions($id)
    {
        if (defined('DOING_DATABASE_UPGRADE')) {
            return [];
        }

        $permissions = $this->app_object_cache->get('staff-' . $id . '-permissions');

        if (!$permissions && !is_array($permissions)) {
            $this->db->where('staff_id', $id);
            $permissions = $this->db->get('staff_permissions')->result_array();

            $this->app_object_cache->add('staff-' . $id . '-permissions', $permissions);
        }

        return $permissions;
    }
}
