<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Perfex_mobile_companion extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('api_model');

        $this->load->library('app_modules');
        if (!$this->app_modules->is_active('perfex_mobile_companion')) {
            access_denied("Perfex Mobile Companion");
        }

    }
}