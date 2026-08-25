<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_129 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!empty(subdomain())) {
            return;
        }

        $credentials_email = [
            'type' => 'saas',
            'slug' => 'saas-credentials-mail',
            'name' => 'SaaS Account Credentials',
            'subject' => 'Your account credentials',
            'message' => 'Dear {name},<br/><br/>
Thank you for registering on the <b>{companyname}</b> platform.<br/><br/>
Here are your account credentials. Please keep them safe:<br/><br/>
<b>Company URL:</b> <a href="{company_url}">{company_url}</a><br/>
<b>Admin URL:</b> <a href="{admin_url}">{admin_url}</a><br/>
<b>Subdomain:</b> {domain}<br/>
<b>Email / Username:</b> {email}<br/>
<b>Password:</b> {password}<br/>
<b>Phone:</b> {mobile}<br/>
<b>Address:</b> {address}<br/>
<b>Package:</b> {package_name}<br/><br/>
You can log in using the Admin URL above with your email and password.<br/><br/>
Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to this.)',
        ];

        $CI->load->model('emails_model');
        $isExist = get_row(db_prefix() . 'emailtemplates', ['slug' => $credentials_email['slug']]);
        if (empty($isExist)) {
            create_email_template(
                $credentials_email['subject'],
                $credentials_email['message'],
                $credentials_email['type'],
                $credentials_email['name'],
                $credentials_email['slug']
            );
        }
    }
}
