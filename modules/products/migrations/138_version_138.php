<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_138 extends App_module_migration
{
    public function up()
    {
        add_option('product_social_proof_recent_hours', 24);
        add_option('product_social_proof_recent_anonymous', 1);
    }
}
