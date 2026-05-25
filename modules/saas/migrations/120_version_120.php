<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_120 extends App_module_migration
{
    /**
     * @throws Exception
     */
    public function up()
    {
        $CI = &get_instance();

        if (!empty(subdomain())) {
            set_alert('warning', 'Only super admin can update the system.');
            redirect('admin/dashboard');
        }

        $token_activate_account = [
            'type' => 'saas',
            'slug' => 'saas-token-activate-account',
            'name' => 'SaaS Token Activate Account',
            'subject' => 'Activate your account',
            'message' => 'Dear {name},<br/><br/>   
    Thank you for registering on the  <b>{companyname}</b> platform. We are happy to have you on board.<br/><br/>
    To verify your Your activation token please copy the activation code: {activation_token} and paste it into the activation form.<br/><br/>
    
    Please click on the link below to activate your account.<br/><br/>
    <big><strong><a href="{activation_url}">Start your registration</a></strong></big><br/><br/>
    link does not work? copy and paste this link into your browser:<br/>
    <big><strong><a href="{activation_url}">{activation_url}</a></strong></big><br/><br/>
    Please activate your account within 48 hours. Otherwise, your registration will be canceled.<br/><br/>
    Best regards,<br/>
    {email_signature}<br/>
    (This is an automated email, so please do not reply to this.)',
        ];

        $company_database_reset = [
            'type' => 'saas',
            'slug' => 'saas-company-database-reset',
            'name' => 'Company Database Reset',
            'subject' => 'Company Database Reset',
            'message' => 'Dear {name},<br/><br/>
your company database has been reset.<br/><br/>
you can login to your company by clicking on the link below.<br/><br/>
<big><strong><a href="{company_url}">Login to your company</a></strong></big><br/><br/>
link does not work? copy and paste this link into your browser:<br/>
<big><strong><a href="{company_url}">{company_url}</a></strong></big><br/><br/>

Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)',
        ];

        $CI->load->model('emails_model');
        $templates = [
            $token_activate_account,
            $company_database_reset,
        ];
        foreach ($templates as $t) {
            // check if email template exist by slug
            $isExist = get_row(db_prefix() . 'emailtemplates', ['slug' => $t['slug']]);
            if ($isExist) {
                continue;
            }

            //this helper check buy slug and create if not exist by slug
            create_email_template($t['subject'], $t['message'], $t['type'], $t['name'], $t['slug']);
        }
    }

}
