<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Force-push working master SMTP/ZeptoMail settings to all tenant DBs.
 * Fixes tenants (e.g. invest-logistic) that kept broken local credentials
 * while the parent Managio site already authenticates successfully.
 */
class Migration_Version_135 extends App_module_migration
{
    public function up()
    {
        if (!function_exists('saas_sync_master_email_settings_to_all_tenants')) {
            return;
        }

        saas_sync_master_email_settings_to_all_tenants(true);
    }
}
