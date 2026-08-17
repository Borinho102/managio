<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-12">
        <?= render_yes_no_option('flutex_admin_login_allowed', _l('settings_allow_login_api'), 'settings_allow_login_api_help'); ?>
        <?= render_textarea('settings[flutex_admin_fcm_service_file_content]', _l('settings_fcm_service_file_content'), get_option('flutex_admin_fcm_service_file_content'), ['rows' => 8, 'style' => 'line-height:20px;']); ?>
    </div>
</div>