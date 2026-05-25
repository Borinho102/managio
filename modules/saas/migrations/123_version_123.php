<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_123 extends App_module_migration
{
    /**
     * @throws Exception
     */
    public function up()
    {
        if (!empty(subdomain())) {
            set_alert('warning', 'Only super admin can update the system.');
            redirect('admin/dashboard');
        }

    }


}
