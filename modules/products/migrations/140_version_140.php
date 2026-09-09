<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_140 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Fix email templates where {invoice_number}{invoice_link} are adjacent,
        // causing the invoice number to be prepended to the URL in notifications.
        $slugs = ['order-to-admin', 'order-to-client'];
        foreach ($slugs as $slug) {
            $templates = $CI->db->where('slug', $slug)->get(db_prefix() . 'emailtemplates')->result();
            foreach ($templates as $template) {
                if (!empty($template->message) && strpos($template->message, '{invoice_number}{invoice_link}') !== false) {
                    $fixed = str_replace('{invoice_number}{invoice_link}', '{invoice_link}', $template->message);
                    $CI->db->where('slug', $slug);
                    if (!empty($template->language)) {
                        $CI->db->where('language', $template->language);
                    }
                    $CI->db->update(db_prefix() . 'emailtemplates', ['message' => $fixed]);
                }
            }
        }
    }
}
