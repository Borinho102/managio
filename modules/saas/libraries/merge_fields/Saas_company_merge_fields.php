<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Saas_company_merge_fields extends App_merge_fields
{
    /**
     * This function builds an array of custom email templates keys.
     * The provided keys will be available in perfex email template editor for the supported templates.
     * @return array
     */
    public function build()
    {
        // List of email templates used by the plugin
        $templates = [
            'saas-welcome-mail',
            'saas-credentials-mail',
            'saas-token-activate-account',
            'saas-faq-request-email',
            'saas-assign-new-package',
            'saas-company-expiration-email',
            'saas-inactive-company-email',
            'saas-company-url',
        ];
        $credentials_templates = [
            'saas-welcome-mail',
            'saas-credentials-mail',
        ];
        $available = ['saas'];
        return [
            [
                'name' => 'Company name',
                'key' => '{name}', // Key for instance name
                'available' => $available,
                'templates' => $templates,
            ],
            [
                'name' => 'Company URL',
                'key' => '{company_url}', // Key for instance slug
                'available' => $available,
                'templates' => $templates,
            ],
            [
                'name' => 'Admin URL',
                'key' => '{admin_url}',
                'available' => $available,
                'templates' => $templates,
            ],
            [
                'name' => 'Package name',
                'key' => '{package_name}', // Key for instance status
                'available' => $available,
                'templates' => $templates,
            ],
            [
                'name' => 'Expiration date',
                'key' => '{expiration_date}', // Key for instance URL
                'available' => $available,
                'templates' => $templates,
            ],
            [
                'name' => 'Activation link',
                'key' => '{activation_url}', // Key for instance admin URL
                'available' => $available,
                'templates' => $templates,
            ],
            [
                'name' => 'Activation token',
                'key' => '{activation_token}', // Key for instance admin URL
                'available' => $available,
                'templates' => $templates,
            ],
            [
                'name' => 'Login email',
                'key' => '{email}',
                'available' => $available,
                'templates' => $credentials_templates,
            ],
            [
                'name' => 'Password',
                'key' => '{password}',
                'available' => $available,
                'templates' => $credentials_templates,
            ],
            [
                'name' => 'Phone number',
                'key' => '{mobile}',
                'available' => $available,
                'templates' => $credentials_templates,
            ],
            [
                'name' => 'Domain / Subdomain',
                'key' => '{domain}',
                'available' => $available,
                'templates' => $credentials_templates,
            ],
            [
                'name' => 'Address',
                'key' => '{address}',
                'available' => $available,
                'templates' => $credentials_templates,
            ],
        ];
    }

    /**
     * Format merge fields for company instance
     * @param object $company
     * @return array
     */
    public function format($company)
    {
        return $this->instance($company);
    }

    /**
     * Company Instance merge fields
     * @param object $company
     * @return array
     * @throws Exception
     */
    public function instance($company)
    {

        $activation_code = $company->activation_code;
        $wildcard = ConfigItems('saas_server_wildcard');
        $companyUrl = base_url();
        $domain = '&d=' . url_encode($company->domain);
        if (!empty($wildcard)) {
            $domain = '';
            $links = all_company_url($company->domain);
            if (is_array($links) && !empty($links)) {
                $first_key = array_key_first($links);
                if ($first_key !== null && isset($links[$first_key])) {
                    $companyUrl = $links[$first_key];
                }
            }
        }
        $sub_domain = $companyUrl . 'setup?c=' . url_encode($activation_code) . $domain;

        $company_url = companyUrl($company->domain);
        $fields = [];
        $fields['{name}'] = $company->name ?? '';
        $fields['{company_url}'] = $company_url;
        $fields['{admin_url}'] = rtrim($company_url, '/') . '/admin';
        $fields['{package_name}'] = $company->package_name ?? '';
        $fields['{expiration_date}'] = $company->expired_date ?? '';
        $fields['{activation_url}'] = $sub_domain;
        $fields['{activation_token}'] = $company->activation_code ?? '';
        $fields['{email}'] = $company->email ?? '';
        $fields['{password}'] = $company->password ?? '';
        $fields['{mobile}'] = $company->mobile ?? '';
        $fields['{domain}'] = $company->domain ?? '';
        $fields['{address}'] = $company->address ?? '';
        return $fields;
    }
}
