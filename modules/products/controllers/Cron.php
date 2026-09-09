<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Cron extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function abandoned_cart_emails()
    {
        if (get_option('product_abandoned_cart_email_enabled') != '1') {
            exit('Disabled');
        }
        if (!$this->db->table_exists(db_prefix() . 'product_abandoned_cart')) {
            exit('Table missing');
        }
        $delay = (int) get_option('product_abandoned_cart_email_delay_hours');
        if ($delay < 1) {
            $delay = 24;
        }
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$delay} hours"));
        $this->db->where('dateadded <=', $cutoff);
        $this->db->where('client_id IS NOT NULL');
        $this->db->where('client_id >', 0);
        if ($this->db->field_exists('emailed', db_prefix() . 'product_abandoned_cart')) {
            $this->db->where('emailed', 0);
        }
        $rows = $this->db->get(db_prefix() . 'product_abandoned_cart')->result_array();
        $sent = 0;
        $this->load->model('currencies_model');
        foreach ($rows as $row) {
            $primary = $this->db->where('userid', $row['client_id'])->where('is_primary', 1)->get(db_prefix() . 'contacts')->row();
            $contact = $primary ?: $this->db->where('userid', $row['client_id'])->get(db_prefix() . 'contacts')->row();
            $client = $this->db->where('userid', $row['client_id'])->get(db_prefix() . 'clients')->row();
            $email = ($contact && !empty($contact->email)) ? $contact->email : null;
            $client_name = $client ? $client->company : ($contact ? trim($contact->firstname . ' ' . $contact->lastname) : '');

            if ($email) {
                $cart_link = site_url('products/client/place_order');
                $base = $this->currencies_model->get_base_currency();
                $cart_total_fmt = $base ? app_format_money((float) $row['cart_total'], $base->name) : number_format((float) $row['cart_total'], 2);
                $merge = [
                    '{cart_total}' => $cart_total_fmt,
                    '{cart_link}' => $cart_link,
                    '{client_name}' => $client_name,
                    '{companyname}' => get_option('companyname'),
                ];
                $tpl = $this->db->where('type', 'products')->where('slug', 'product-abandoned-cart')->get(db_prefix() . 'emailtemplates')->row();
                if ($tpl && $tpl->active == 1 && function_exists('app_send_email')) {
                    $subject = str_replace(array_keys($merge), array_values($merge), $tpl->subject);
                    $message = str_replace(array_keys($merge), array_values($merge), $tpl->message);
                    app_send_email($email, $subject, $message);
                    $sent++;
                }
            }
            if ($contact && !empty($contact->phonenumber)) {
                $client = $this->db->where('userid', $row['client_id'])->get(db_prefix() . 'clients')->row();
                $data = [
                    'cart_total' => $row['cart_total'],
                    'client_name' => $client ? $client->company : ($contact->firstname . ' ' . $contact->lastname),
                    'contact_phonenumber' => $contact->phonenumber,
                ];
                products_send_notification('abandoned_cart', $data);
            }
            if ($this->db->field_exists('emailed', db_prefix() . 'product_abandoned_cart')) {
                $this->db->where('id', $row['id']);
                $this->db->update(db_prefix() . 'product_abandoned_cart', ['emailed' => 1]);
            }
        }
        echo "Sent: {$sent}\n";
    }

    public function back_in_stock_emails()
    {
        if (get_option('product_back_in_stock_enabled') != '1') {
            exit('Disabled');
        }
        if (!$this->db->table_exists(db_prefix() . 'product_stock_notifications')) {
            exit('Table missing');
        }
        $this->load->model('products/product_stock_notifications_model');
        $this->load->model('products/products_model');
        $products = $this->product_stock_notifications_model->get_products_now_in_stock();
        $sent = 0;
        foreach ($products as $p) {
            $product = $this->products_model->get($p['product_id']);
            if (!$product || (int) $product->quantity_number < 1) {
                continue;
            }
            $subs = $this->product_stock_notifications_model->get_pending_for_product($product->id);
            $product_link = !empty($product->slug) ? site_url('products/client/product/' . $product->slug) : site_url('products/client');
            foreach ($subs as $sub) {
                $merge = [
                    '{product_name}' => $product->product_name,
                    '{product_link}' => $product_link,
                    '{companyname}' => get_option('companyname'),
                    '{store_link}' => site_url('products/client'),
                ];
                $tpl = $this->db->where('type', 'products')->where('slug', 'product-back-in-stock')->get(db_prefix() . 'emailtemplates')->row();
                if ($tpl && $tpl->active == 1) {
                    $subject = $tpl->subject;
                    $message = $tpl->message;
                    foreach ($merge as $k => $v) {
                        $subject = str_replace($k, $v, $subject);
                        $message = str_replace($k, $v, $message);
                    }
                    if (function_exists('app_send_email')) {
                        app_send_email($sub['email'], $subject, $message);
                        $sent++;
                    }
                }
                $this->product_stock_notifications_model->mark_notified($sub['id']);
            }
        }
        echo "Back in stock sent: {$sent}\n";
    }

    public function price_drop_emails()
    {
        if (get_option('product_price_drop_enabled') != '1') {
            exit('Disabled');
        }
        if (!$this->db->table_exists(db_prefix() . 'product_price_alerts')) {
            exit('Table missing');
        }
        $this->load->model('products/product_price_alerts_model');
        $this->load->model(['products/products_model', 'currencies_model']);
        $base = $this->currencies_model->get_base_currency();
        $rows = $this->db->where('notified', 0)->get(db_prefix() . 'product_price_alerts')->result_array();
        $sent = 0;
        foreach ($rows as $row) {
            $product = $this->products_model->get($row['product_id']);
            if (!$product) {
                continue;
            }
            $price = isset($product->sale_price) && $product->sale_price !== null && (empty($product->sale_price_end) || strtotime($product->sale_price_end) >= time())
                ? (float) $product->sale_price : (float) $product->rate;
            $target = (float) $row['target_price'];
            if ($target <= 0 || $price >= $target) {
                continue;
            }
            $product_link = !empty($product->slug) ? site_url('products/client/product/' . $product->slug) : site_url('products/client');
            $price_fmt = $base ? app_format_money($price, $base->name) : $price;
            $merge = [
                '{product_name}' => $product->product_name,
                '{product_price}' => $price_fmt,
                '{product_link}' => $product_link,
                '{companyname}' => get_option('companyname'),
                '{store_link}' => site_url('products/client'),
            ];
            $tpl = $this->db->where('type', 'products')->where('slug', 'product-price-drop')->get(db_prefix() . 'emailtemplates')->row();
            if ($tpl && $tpl->active == 1 && function_exists('app_send_email')) {
                $subject = str_replace(array_keys($merge), array_values($merge), $tpl->subject);
                $message = str_replace(array_keys($merge), array_values($merge), $tpl->message);
                app_send_email($row['email'], $subject, $message);
                $sent++;
            }
            $this->product_price_alerts_model->mark_notified($row['id']);
        }
        echo "Price drop sent: {$sent}\n";
    }
}
