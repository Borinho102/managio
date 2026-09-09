<?php

if (!function_exists('products_record_recent_purchase')) {
    /**
     * Record "X just purchased" for social proof when an order is paid.
     */
    function products_record_recent_purchase($order)
    {
        $CI = &get_instance();
        $order_id = is_object($order) ? $order->id : ($order['id'] ?? null);
        $client_id = is_object($order) ? $order->clientid : ($order['clientid'] ?? null);
        if (!$order_id) {
            return;
        }
        $items = $CI->db->select('product_id')->where('order_id', $order_id)->get(db_prefix() . 'order_items')->result_array();
        $product_ids = array_unique(array_column($items, 'product_id'));
        if (empty($product_ids)) {
            return;
        }
        $display_text = _l('product_social_proof_someone_purchased');
        $anonymous = 1;
        if ($client_id && get_option('product_social_proof_recent_anonymous') != '1') {
            $contact = $CI->db->where('userid', $client_id)->where('is_primary', 1)->get(db_prefix() . 'contacts')->row();
            if (!$contact) {
                $contact = $CI->db->where('userid', $client_id)->get(db_prefix() . 'contacts')->row();
            }
            if ($contact && !empty($contact->firstname)) {
                $name = trim($contact->firstname . ' ' . substr($contact->lastname ?? '', 0, 1));
                if (!empty($name)) {
                    $display_text = sprintf(_l('product_social_proof_x_purchased'), $name);
                    $anonymous = 0;
                }
            }
        }
        foreach ($product_ids as $pid) {
            if (empty($pid)) {
                continue;
            }
            $CI->db->insert(db_prefix() . 'product_social_proof', [
                'product_id' => $pid,
                'message_type' => 'recent_purchase',
                'display_text' => $display_text,
                'anonymous' => $anonymous,
                'active' => 1,
                'datecreated' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}

/**
 * Path for product images (Perfex uploads - survives module deploys).
 */
function products_get_product_image_path()
{
    return defined('PRODUCT_IMAGES_UPLOAD_PATH') ? PRODUCT_IMAGES_UPLOAD_PATH : (FCPATH . 'uploads/products/');
}

/**
 * URL for product image (or empty if none).
 * Checks both Perfex uploads and module uploads for backward compatibility with 1.3.0 and earlier.
 */
function products_get_product_image_url($product_image)
{
    if (empty($product_image)) {
        return '';
    }
    $newPath  = products_get_product_image_path() . $product_image;
    $modulePath = defined('PRODUCT_MODULE_UPLOAD_FOLDER') ? PRODUCT_MODULE_UPLOAD_FOLDER . $product_image : '';
    if (file_exists($newPath) && is_file($newPath)) {
        return base_url('uploads/products/' . $product_image);
    }
    if ($modulePath && file_exists($modulePath) && is_file($modulePath)) {
        return module_dir_url('products', 'uploads') . '/' . $product_image;
    }
    return module_dir_url('products', 'uploads') . '/' . $product_image;
}

/**
 * URL for placeholder when product has no image.
 */
function products_get_no_image_url()
{
    return module_dir_url('products', 'uploads') . '/image-not-available.png';
}

function handle_product_upload($product_id)
{
    $CI = &get_instance();
    if (isset($_FILES['product']['name']) && '' != $_FILES['product']['name']) {
        $path        = products_get_product_image_path();
        $tmpFilePath = $_FILES['product']['tmp_name'];
        if (!empty($tmpFilePath) && '' != $tmpFilePath) {
            $path_parts  = pathinfo($_FILES['product']['name']);
            $extension   = $path_parts['extension'];
            $extension   = strtolower($extension);
            $filename    = 'product_'.$product_id.'.'.$extension;
            $newFilePath = $path.$filename;
            _maybe_create_upload_path($path);
            if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                $CI->products_model->edit_product(['product_image' => $filename], $product_id);

                return true;
            }
        }
    }

    return false;
}

function handle_digital_product_upload($product_id)
{
    $CI = &get_instance();
    if (empty($_FILES['digital_file']['name'])) {
        return false;
    }
    $path = get_upload_path_by_type('products') . 'digital/';
    _maybe_create_upload_path($path);
    $ext = strtolower(pathinfo($_FILES['digital_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'zip', 'mp3', 'mp4', 'epub', 'doc', 'docx'];
    if (!in_array($ext, $allowed)) {
        return false;
    }
    $filename = 'digital_' . $product_id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['digital_file']['tmp_name'], $path . $filename)) {
        $CI->products_model->edit_product(['digital_file_path' => 'digital/' . $filename], $product_id);
        return true;
    }
    return false;
}

function handle_exit_popup_image_upload($popup_id)
{
    if (empty($_FILES['popup_image']['name'])) {
        return false;
    }
    $path = get_upload_path_by_type('products') . 'exit_popups/';
    _maybe_create_upload_path($path);
    $ext = strtolower(pathinfo($_FILES['popup_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        return false;
    }
    $filename = 'popup_' . $popup_id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['popup_image']['tmp_name'], $path . $filename)) {
        return $filename;
    }
    return false;
}

function products_build_notification_merge_fields($trigger, $data)
{
    $CI = &get_instance();
    $fields = [];
    $fields['{companyname}'] = get_option('companyname');
    $fields['{cart_link}'] = site_url('products/client/place_order');
    if ($trigger === 'abandoned_cart') {
        $CI->load->model('currencies_model');
        $cur = $CI->currencies_model->get_base_currency();
        $fields['{cart_total}'] = isset($data['cart_total']) ? (function_exists('app_format_money') && $cur ? app_format_money($data['cart_total'], $cur->name) : number_format((float) $data['cart_total'], 2)) : '';
        $fields['{client_name}'] = isset($data['client_name']) ? $data['client_name'] : '';
        $fields['{contact_phonenumber}'] = isset($data['contact_phonenumber']) ? $data['contact_phonenumber'] : '';
        return $fields;
    }
    $order_id = is_array($data) ? ($data['order_id'] ?? null) : ($data->id ?? null);
    $invoice_id = is_array($data) ? ($data['invoice_id'] ?? null) : ($data->invoice_id ?? null);
    $client_id = is_array($data) ? ($data['client_id'] ?? null) : ($data->clientid ?? null);
    if (!$order_id && $invoice_id) {
        $CI->load->model('products/order_model');
        $ord = $CI->db->where('invoice_id', $invoice_id)->get(db_prefix() . 'order_master')->row();
        if ($ord) {
            $order_id = $ord->id;
            $client_id = $ord->clientid;
        }
    }
    if (!$order_id) {
        return $fields;
    }
    $CI->load->model(['products/order_model', 'currencies_model']);
    $order = is_object($data) && isset($data->id) ? $data : $CI->order_model->get_by_id_order($order_id);
    if (!$order) {
        $order = $CI->order_model->get_by_id_order($order_id);
    }
    if ($order) {
        $base = $CI->currencies_model->get_base_currency();
        $fields['{order_id}'] = $order->id ?? $order_id;
        $fields['{order_date}'] = $order->order_date ?? '';
        $fields['{total}'] = isset($order->total) ? app_format_money($order->total) : '';
        $fields['{currency}'] = $base ? $base->name : '';
        $fields['{status}'] = $order->status ?? '';
        $client_id = $client_id ?? ($order->clientid ?? null);
    }
    if ($invoice_id) {
        $inv = $CI->db->where('id', $invoice_id)->get(db_prefix() . 'invoices')->row();
        if ($inv) {
            $invoice_number = function_exists('format_invoice_number') ? format_invoice_number($invoice_id) : ($inv->number ?? '');
            $invoice_url = site_url('invoice/' . $invoice_id . '/' . ($inv->hash ?? ''));
            $fields['{invoice_number}'] = $invoice_number;
            $fields['{invoice_link}'] = '<a href="' . $invoice_url . '">' . $invoice_number . '</a>';
        }
    }
    if ($client_id) {
        $contact_id = get_primary_contact_user_id($client_id);
        $contact = $CI->db->where('userid', $client_id)->where('id', $contact_id)->get(db_prefix() . 'contacts')->row();
        if (!$contact) {
            $contact = $CI->db->where('userid', $client_id)->get(db_prefix() . 'contacts')->row();
        }
        if ($contact) {
            $fields['{contact_phonenumber}'] = $contact->phonenumber ?? '';
            $fields['{client_name}'] = $contact->firstname . ' ' . $contact->lastname;
        }
        $client = $CI->db->where('userid', $client_id)->get(db_prefix() . 'clients')->row();
        if ($client) {
            $fields['{companyname}'] = $client->company ?? get_option('companyname');
        }
    }
    $admin = $CI->db->where('admin', 1)->where('active', 1)->get(db_prefix() . 'staff')->row();
    if ($admin) {
        $fields['{staff_phonenumber}'] = $admin->phonenumber ?? '';
    }
    return $fields;
}

function products_send_notification($trigger, $data)
{
    if (get_option('product_notifications_enabled') != '1') {
        return;
    }
    $CI = &get_instance();
    $CI->load->model('products/product_notifications_model');
    $templates = $CI->product_notifications_model->get_active_for_trigger($trigger);
    if (empty($templates)) {
        return;
    }
    $merge = products_build_notification_merge_fields($trigger, $data);
    foreach ($templates as $tpl) {
        $url = $tpl->webhook_url;
        $use_global = isset($tpl->use_global_gateway) ? $tpl->use_global_gateway : 1;
        if (empty($url) && $use_global) {
            if ($tpl->channel === 'sms') {
                $url = get_option('product_sms_gateway_url');
            } elseif ($tpl->channel === 'whatsapp') {
                $url = get_option('product_whatsapp_gateway_url');
            }
        }
        if (empty($url)) {
            continue;
        }
        $message = $tpl->message_template;
        foreach ($merge as $k => $v) {
            $message = str_replace($k, $v, $message);
        }
        $to = ($tpl->recipient === 'client') ? ($merge['{contact_phonenumber}'] ?? '') : ($merge['{staff_phonenumber}'] ?? '');
        if (empty($to) && in_array($trigger, ['order_placed', 'order_paid'])) {
            continue;
        }
        $body_tpl = $tpl->webhook_body ?: '{"to":"{contact_phonenumber}","body":{message}}';
        if ($tpl->recipient === 'staff') {
            $body_tpl = $tpl->webhook_body ?: '{"to":"{staff_phonenumber}","body":{message}}';
        }
        foreach ($merge as $k => $v) {
            $body_tpl = str_replace($k, (string) $v, $body_tpl);
        }
        $body_tpl = str_replace('{message}', json_encode($message), $body_tpl);
        $body = json_decode($body_tpl, true);
        if (!is_array($body)) {
            $body = ['to' => $to, 'body' => $message];
        }
        if (strtoupper($tpl->webhook_method) === 'GET') {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($body);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (strtoupper($tpl->webhook_method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        @curl_exec($ch);
        curl_close($ch);
    }
}

function products_create_gift_card_from_purchase($purchase)
{
    $CI = &get_instance();
    if (empty($purchase['invoice_id']) || empty($purchase['amount'])) {
        return;
    }
    $inv = $CI->db->where('id', $purchase['invoice_id'])->get(db_prefix() . 'invoices')->row();
    if (!$inv || (int) $inv->status !== 2) {
        return;
    }
    $CI->load->model('products/product_gift_cards_model');
    $CI->load->model('currencies_model');
    // Runs from the payment hook, where there is no customer session - so the
    // paid invoice's own currency is what the card must be denominated in.
    $base = !empty($inv->currency) ? get_currency($inv->currency) : null;
    if (!$base) {
        $base = $CI->currencies_model->get_base_currency();
    }
    $currency_id = $base ? $base->id : 0;
    $client_id = $inv->clientid;
    $gc_id = $CI->product_gift_cards_model->create([
        'template_id' => $purchase['template_id'] ?? null,
        'amount' => $purchase['amount'],
        'currency' => $currency_id,
        'purchaser_client_id' => $client_id,
        'recipient_email' => $purchase['recipient_email'] ?? null,
        'recipient_name' => $purchase['recipient_name'] ?? null,
        'message' => $purchase['message'] ?? null,
    ]);
    if (!$gc_id) {
        return;
    }
    $gc = $CI->product_gift_cards_model->get($gc_id);
    $CI->db->where('id', $purchase['id'])->update(db_prefix() . 'product_gift_card_purchases', ['gift_card_id' => $gc_id]);
    $email = $purchase['recipient_email'];
    if (empty($email) && $client_id) {
        $contact = $CI->db->where('userid', $client_id)->where('is_primary', 1)->get(db_prefix() . 'contacts')->row();
        if (!$contact) {
            $contact = $CI->db->where('userid', $client_id)->get(db_prefix() . 'contacts')->row();
        }
        $email = $contact ? $contact->email : null;
    }
    if ($email) {
        $merge = [
            '{gift_card_code}' => $gc->code,
            '{gift_card_amount}' => app_format_money($gc->amount, $base->name),
            '{recipient_name}' => $purchase['recipient_name'] ?? '',
            '{sender_name}' => '',
            '{message}' => $purchase['message'] ?? '',
            '{expiry_date}' => $gc->expiry_date ?? '',
            '{companyname}' => get_option('companyname'),
            '{store_link}' => site_url('products/client'),
        ];
        $tpl = $CI->db->where('type', 'products')->where('slug', 'product-gift-card')->get(db_prefix() . 'emailtemplates')->row();
        if ($tpl && $tpl->active == 1) {
            $subject = $tpl->subject;
            $message = $tpl->message;
            foreach ($merge as $k => $v) {
                $subject = str_replace($k, $v, $subject);
                $message = str_replace($k, $v, $message);
            }
            if (function_exists('app_send_email')) {
                app_send_email($email, $subject, $message);
            }
        }
    }
}

function get_coupon_used_times($coupon_id)
{
    $CI = &get_instance();

    $coupon_used_times = $CI->coupons_model->get_used_times($coupon_id);
    return $coupon_used_times;
}

function get_product_variations($product_id)
{
    $CI = &get_instance();

    $product_variations = $CI->products_model->get_by_id_variations($product_id);

    $variations = '';
    foreach ($product_variations as $product_variation)
    {
        $variations .= '<span class="label label-warning">' . $product_variation->variation_name . '</span> ';
    }

    return $variations;
}

function get_product_variation_price($product_id)
{
    $CI = &get_instance();

    $base_currency = products_active_currency();
    $product_variations = $CI->products_model->get_by_id_variations($product_id);
    products_apply_currency_to_variations($product_variations, $base_currency);
    $min_price = 0; $max_price = 0;
    foreach ($product_variations as $product_variation)
    {
        if (!$min_price) $min_price = $product_variation->rate;
        if (!$max_price) $max_price = $product_variation->rate;
        if ($min_price > $product_variation->rate) $min_price = $product_variation->rate;
        if ($max_price < $product_variation->rate) $max_price = $product_variation->rate;
    }

    $variation_price = app_format_money($min_price, $base_currency->name);
    if ($min_price != $max_price) {
        if ($base_currency->placement == 'before') {
            $variation_price .= ' - ' . str_replace($base_currency->symbol, '', str_replace($base_currency->name, '', app_format_money($max_price, $base_currency->name)));
        } else {
            $variation_price = str_replace($base_currency->symbol, '', str_replace($base_currency->name, '', $variation_price));
            $variation_price .= ' - ' . app_format_money($max_price, $base_currency->name);
        }
    }

    return $variation_price;
}

function get_product_variation_values($product_id)
{
    $CI = &get_instance();

    $product_variation_values = $CI->products_model->get_by_id_variation_values($product_id);

    $variation = '';
    $variation_values = '';
    foreach ($product_variation_values as $product_variation_value)
    {
        if ($variation != $product_variation_value->variation_name) {
            $variation = $product_variation_value->variation_name;
            if ($variation_values) {
                $variation_values .= '</div>';
            }
            $variation_values .= '<div>' . $product_variation_value->variation_name . ' - ';
        }
        $variation_values .= '<span class="label label-warning">' . $product_variation_value->variation_value . '</span> ';
    }
    $variation_values .= '</div>';

    return $variation_values;
}

function get_variation_values($variation_id)
{
    $CI = &get_instance();

    $variation_values = $CI->variations_model->get_values($variation_id);

    $values = '';
    foreach ($variation_values as $variation_value)
    {
        $values .= '<span class="label label-warning">' . $variation_value['value'] . '</span> ';
    }

    return $values;
}

if (!function_exists('toPlainArray')) {
    function toPlainArray($arr)
    {
        $output = "['";
        foreach ($arr as $val) {
            $output .= $val."', '";
        }
        $plain_array = substr($output, 0, -3).']';

        return $plain_array;
    }
}

/*
|--------------------------------------------------------------------------
| Multi-currency
|--------------------------------------------------------------------------
| Prices are stored explicitly per currency in tblproduct_prices - there is no
| exchange-rate conversion, because Perfex does not store exchange rates and a
| converted price is never the price a merchant wants to advertise.
|
| When a currency has no configured price the base rate on product_master /
| product_variations is used, so a store that never enables a second currency
| behaves exactly as it did before this feature.
*/

define('PRODUCTS_CURRENCY_SESSION_KEY', 'products_active_currency');

if (!function_exists('products_multicurrency_enabled')) {
    function products_multicurrency_enabled()
    {
        return '1' == get_option('product_multicurrency_enabled');
    }
}

if (!function_exists('products_base_currency')) {
    function products_base_currency()
    {
        $CI = &get_instance();
        $CI->load->model('currencies_model');

        return $CI->currencies_model->get_base_currency();
    }
}

if (!function_exists('products_recurring_suffix')) {
    /**
     * Billing-period suffix for a recurring product, e.g. "/month", "/2 weeks".
     * Empty string for one-off products.
     *
     * recurring is a month count when custom_recurring is 0, otherwise it is a
     * count of recurring_type units.
     *
     * @param object|array $product
     */
    function products_recurring_suffix($product)
    {
        if (empty($product)) {
            return '';
        }

        $is_array         = is_array($product);
        $recurring        = (int) ($is_array ? ($product['recurring'] ?? 0) : ($product->recurring ?? 0));
        $custom_recurring = (int) ($is_array ? ($product['custom_recurring'] ?? 0) : ($product->custom_recurring ?? 0));
        $recurring_type   = (string) ($is_array ? ($product['recurring_type'] ?? '') : ($product->recurring_type ?? ''));

        if ($recurring < 1) {
            return '';
        }

        $unit = 1 === $custom_recurring && $recurring_type !== '' ? $recurring_type : 'month';
        if (!in_array($unit, ['day', 'week', 'month', 'year'], true)) {
            $unit = 'month';
        }

        $label = 1 === $recurring
            ? _l('product_period_' . $unit)
            : $recurring . ' ' . _l('product_period_' . $unit . '_plural');

        return '/' . $label;
    }
}

if (!function_exists('products_has_taxes')) {
    /**
     * Whether a product carries any tax in the given currency. Used to append a
     * "plus tax" note to displayed prices.
     *
     * @param object|array $product
     */
    function products_has_taxes($product, $currency = null)
    {
        if (empty($product)) {
            return false;
        }

        $currency    = $currency ?: products_active_currency();
        $currency_id = $currency ? (int) $currency->id : 0;
        if (!products_currency_charges_tax($currency_id)) {
            return false;
        }

        $is_array = is_array($product);
        $taxes    = $is_array ? ($product['taxes'] ?? '') : ($product->taxes ?? '');
        if (empty($taxes)) {
            return false;
        }
        if (is_string($taxes)) {
            $taxes = @unserialize($taxes);
        }

        return !empty($taxes);
    }
}

if (!function_exists('products_price_display')) {
    /**
     * Full customer-facing price string, e.g. "CAD $7.00/month (plus tax)".
     *
     * app_format_money applies the currency symbol, decimals and separators the
     * merchant configured, which is why the raw rate must never be echoed.
     *
     * @param float        $amount
     * @param object|array $product
     * @param object|null  $currency
     */
    function products_price_display($amount, $product = null, $currency = null)
    {
        $currency = $currency ?: products_active_currency();
        $display  = app_format_money($amount, $currency ? $currency->name : '');

        if (!empty($product)) {
            $display .= products_recurring_suffix($product);
            if (products_has_taxes($product, $currency)) {
                $display .= ' ' . _l('product_plus_tax');
            }
        }

        return $display;
    }
}

if (!function_exists('products_guest_checkout_enabled')) {
    /**
     * Beta: let visitors check out without logging in first.
     *
     * Perfex bills against an invoice and an invoice must belong to a customer,
     * so a literal "pay now, create the account later" is not possible. What
     * this does instead is remove the login and signup screens: the details are
     * collected on the checkout form, the customer record is created quietly,
     * and the visitor goes straight to the invoice payment page.
     *
     * Off by default. The whole guest path is skipped when this returns false,
     * which keeps the logged-in checkout exactly as it was.
     */
    function products_guest_checkout_enabled()
    {
        return '1' == get_option('product_guest_checkout_enabled');
    }
}

if (!function_exists('products_tracks_stock')) {
    /**
     * Whether a product consumes stock when ordered.
     *
     * Only physical goods do. Services and digital products are unlimited, so
     * decrementing them drives quantity to 0 after the first order and then
     * makes the product read as out of stock.
     *
     * @param object|array $product
     */
    function products_tracks_stock($product)
    {
        if (empty($product)) {
            return true;
        }

        $is_array     = is_array($product);
        $is_digital   = (int) ($is_array ? ($product['is_digital'] ?? 0) : ($product->is_digital ?? 0));
        $product_type = (string) ($is_array ? ($product['product_type'] ?? '') : ($product->product_type ?? ''));

        if (1 === $is_digital) {
            return false;
        }

        return !in_array($product_type, ['digital', 'service'], true);
    }
}

if (!function_exists('products_all_currencies')) {
    /**
     * Every currency configured in Perfex, always as objects keyed by id.
     *
     * currencies_model->get() returns result_array() (associative arrays) while
     * get_base_currency() returns an object. Normalising here means the rest of
     * the module can use ->id and ->name everywhere without caring which call it
     * came from.
     *
     * @return array<int, object> keyed by currency id
     */
    function products_all_currencies()
    {
        $CI = &get_instance();
        $CI->load->model('currencies_model');

        $currencies = [];
        foreach ((array) $CI->currencies_model->get() as $currency) {
            $currency = is_array($currency) ? (object) $currency : $currency;
            if (empty($currency->id)) {
                continue;
            }
            $currencies[(int) $currency->id] = $currency;
        }

        return $currencies;
    }
}

if (!function_exists('products_enabled_currencies')) {
    /**
     * Currencies the storefront may sell in. The base currency is always
     * included so the store can never end up with nothing to price in.
     *
     * @return array<int, object> keyed by currency id
     */
    function products_enabled_currencies()
    {
        $base       = products_base_currency();
        $currencies = [];
        if ($base) {
            $currencies[(int) $base->id] = $base;
        }

        if (!products_multicurrency_enabled()) {
            return $currencies;
        }

        $enabled_ids = array_filter(array_map('intval', explode(',', (string) get_option('product_currencies_enabled'))));
        if (empty($enabled_ids)) {
            return $currencies;
        }

        foreach (products_all_currencies() as $currency_id => $currency) {
            if (in_array($currency_id, $enabled_ids, true)) {
                $currencies[$currency_id] = $currency;
            }
        }

        return $currencies;
    }
}

if (!function_exists('products_currency_is_enabled')) {
    function products_currency_is_enabled($currency_id)
    {
        return array_key_exists((int) $currency_id, products_enabled_currencies());
    }
}

if (!function_exists('products_get_currency')) {
    /**
     * Enabled currency by id, falling back to base for unknown or disabled ids.
     * Every lookup goes through here so a stale session value or a hand-edited
     * URL can never price an order in a currency the merchant turned off.
     */
    function products_get_currency($currency_id)
    {
        $currencies = products_enabled_currencies();
        $currency_id = (int) $currency_id;

        if (isset($currencies[$currency_id])) {
            return $currencies[$currency_id];
        }

        return products_base_currency();
    }
}

if (!function_exists('products_active_currency')) {
    /**
     * Currency for the current request, resolved in order of specificity:
     * explicit customer choice, then the customer's Perfex default currency,
     * then the base currency.
     */
    function products_active_currency()
    {
        $CI = &get_instance();

        if (!products_multicurrency_enabled()) {
            return products_base_currency();
        }

        // Admin and staff screens always price in the base currency: that is the
        // currency their "rate" field edits, and staff share a session with the
        // customer area when one person is logged into both.
        if (function_exists('is_staff_logged_in') && is_staff_logged_in()
            && (!function_exists('is_client_logged_in') || !is_client_logged_in())) {
            return products_base_currency();
        }

        $chosen = $CI->session->userdata(PRODUCTS_CURRENCY_SESSION_KEY);
        if (!empty($chosen) && products_currency_is_enabled($chosen)) {
            return products_get_currency($chosen);
        }

        $client_default = products_client_default_currency_id();
        if ($client_default && products_currency_is_enabled($client_default)) {
            return products_get_currency($client_default);
        }

        return products_base_currency();
    }
}

if (!function_exists('products_client_default_currency_id')) {
    /**
     * The logged-in customer's default currency from Perfex core, or 0.
     * Guarded on the column existing so the module keeps working on Perfex
     * builds that do not ship it.
     */
    function products_client_default_currency_id()
    {
        $CI = &get_instance();

        if (!function_exists('is_client_logged_in') || !is_client_logged_in()) {
            return 0;
        }
        if (!$CI->db->field_exists('default_currency', db_prefix() . 'clients')) {
            return 0;
        }

        $client = $CI->db
            ->select('default_currency')
            ->where('userid', get_client_user_id())
            ->get(db_prefix() . 'clients')
            ->row();

        return $client ? (int) $client->default_currency : 0;
    }
}

if (!function_exists('products_set_active_currency')) {
    /**
     * Remember the customer's currency choice for this session.
     *
     * @return bool false when the currency is not enabled for selling
     */
    function products_set_active_currency($currency_id)
    {
        $CI = &get_instance();

        if (!products_multicurrency_enabled() || !products_currency_is_enabled($currency_id)) {
            return false;
        }

        $CI->session->set_userdata(PRODUCTS_CURRENCY_SESSION_KEY, (int) $currency_id);

        return true;
    }
}

if (!function_exists('products_resolve_rate')) {
    /**
     * Price for one product/variation in one currency, or the base rate when
     * that currency has no configured price.
     *
     * @param string     $rel_type       'product' or 'variation'
     * @param int        $rel_id
     * @param int        $currency_id
     * @param float      $fallback_rate  base rate from product_master/product_variations
     * @param float|null $fallback_sale  base sale price, products only
     *
     * @return array{rate: float, sale_price: float|null}
     */
    function products_resolve_rate($rel_type, $rel_id, $currency_id, $fallback_rate, $fallback_sale = null)
    {
        $CI = &get_instance();
        $CI->load->model('products/product_prices_model');

        $price = $CI->product_prices_model->get_price($rel_type, $rel_id, $currency_id);

        return products_price_from_row($price, $fallback_rate, $fallback_sale);
    }
}

if (!function_exists('products_price_from_row')) {
    /**
     * Normalise a product_prices row (or a missing one) into rate/sale_price.
     * Shared by the single and batch lookups so both fall back identically.
     *
     * @param object|null $price
     *
     * @return array{rate: float, sale_price: float|null}
     */
    function products_price_from_row($price, $fallback_rate, $fallback_sale = null)
    {
        if (!$price) {
            return [
                'rate'       => (float) $fallback_rate,
                'sale_price' => ($fallback_sale === null || $fallback_sale === '') ? null : (float) $fallback_sale,
            ];
        }

        return [
            'rate'       => (float) $price->rate,
            'sale_price' => $price->sale_price === null ? null : (float) $price->sale_price,
        ];
    }
}

if (!function_exists('products_apply_currency_to_product')) {
    /**
     * Overwrite rate/sale_price on one product with its price in $currency, and
     * do the same for any nested variations.
     *
     * Rewriting the values in place is deliberate: every existing view, JS
     * template and invoice builder already reads `rate`, so they all become
     * currency-correct without touching their formatting code.
     *
     * @param object|array $product  modified in place
     * @param object       $currency
     */
    function products_apply_currency_to_product(&$product, $currency = null)
    {
        if (empty($product)) {
            return;
        }
        $currency = $currency ?: products_active_currency();
        if (!$currency) {
            return;
        }

        $is_array = is_array($product);
        $id       = (int) ($is_array ? ($product['id'] ?? 0) : ($product->id ?? 0));
        if (!$id) {
            return;
        }

        $resolved = products_resolve_rate(
            'product',
            $id,
            $currency->id,
            $is_array ? ($product['rate'] ?? 0) : ($product->rate ?? 0),
            $is_array ? ($product['sale_price'] ?? null) : ($product->sale_price ?? null)
        );

        if ($is_array) {
            $product['rate']       = $resolved['rate'];
            $product['sale_price'] = $resolved['sale_price'];
            if (!empty($product['variations'])) {
                products_apply_currency_to_variations($product['variations'], $currency);
            }

            return;
        }

        $product->rate       = $resolved['rate'];
        $product->sale_price = $resolved['sale_price'];
        if (!empty($product->variations)) {
            products_apply_currency_to_variations($product->variations, $currency);
        }
    }
}

if (!function_exists('products_apply_currency_to_variations')) {
    /**
     * Overwrite rate on a set of product_variations rows with their price in
     * $currency. Batched into one query - variation lists can be long.
     *
     * @param array $variations modified in place
     */
    function products_apply_currency_to_variations(&$variations, $currency = null)
    {
        if (empty($variations) || !is_array($variations)) {
            return;
        }
        $currency = $currency ?: products_active_currency();
        if (!$currency) {
            return;
        }

        $CI = &get_instance();
        $CI->load->model('products/product_prices_model');

        $ids = [];
        foreach ($variations as $variation) {
            $ids[] = is_array($variation) ? ($variation['id'] ?? 0) : ($variation->id ?? 0);
        }

        $map = $CI->product_prices_model->get_map('variation', $ids, $currency->id);

        foreach ($variations as $key => $variation) {
            $is_array = is_array($variation);
            $id       = (int) ($is_array ? ($variation['id'] ?? 0) : ($variation->id ?? 0));
            $resolved = products_price_from_row(
                $map[$id] ?? null,
                $is_array ? ($variation['rate'] ?? 0) : ($variation->rate ?? 0)
            );

            if ($is_array) {
                $variations[$key]['rate'] = $resolved['rate'];
                continue;
            }
            $variations[$key]->rate = $resolved['rate'];
        }
    }
}

if (!function_exists('products_apply_currency_to_list')) {
    /**
     * Currency-correct a whole product list in two queries rather than two per
     * product. Used by the storefront grid and the admin product table.
     *
     * @param array $products modified in place
     */
    function products_apply_currency_to_list(&$products, $currency = null)
    {
        if (empty($products) || !is_array($products)) {
            return;
        }
        $currency = $currency ?: products_active_currency();
        if (!$currency) {
            return;
        }

        $CI = &get_instance();
        $CI->load->model('products/product_prices_model');

        $ids = [];
        foreach ($products as $product) {
            $ids[] = is_array($product) ? ($product['id'] ?? 0) : ($product->id ?? 0);
        }
        $map = $CI->product_prices_model->get_map('product', $ids, $currency->id);

        $all_variations = [];
        foreach ($products as $key => $product) {
            $is_array = is_array($product);
            $id       = (int) ($is_array ? ($product['id'] ?? 0) : ($product->id ?? 0));
            $resolved = products_price_from_row(
                $map[$id] ?? null,
                $is_array ? ($product['rate'] ?? 0) : ($product->rate ?? 0),
                $is_array ? ($product['sale_price'] ?? null) : ($product->sale_price ?? null)
            );

            if ($is_array) {
                $products[$key]['rate']       = $resolved['rate'];
                $products[$key]['sale_price'] = $resolved['sale_price'];
            } else {
                $products[$key]->rate       = $resolved['rate'];
                $products[$key]->sale_price = $resolved['sale_price'];
            }

            $variations = $is_array ? ($product['variations'] ?? []) : ($product->variations ?? []);
            if (!empty($variations)) {
                $all_variations[$key] = $variations;
            }
        }

        // One query for every variation across the whole list.
        $variation_ids = [];
        foreach ($all_variations as $variations) {
            foreach ($variations as $variation) {
                $variation_ids[] = is_array($variation) ? ($variation['id'] ?? 0) : ($variation->id ?? 0);
            }
        }
        if (empty($variation_ids)) {
            return;
        }
        $variation_map = $CI->product_prices_model->get_map('variation', $variation_ids, $currency->id);

        foreach ($all_variations as $product_key => $variations) {
            foreach ($variations as $variation_key => $variation) {
                $is_array = is_array($variation);
                $id       = (int) ($is_array ? ($variation['id'] ?? 0) : ($variation->id ?? 0));
                $resolved = products_price_from_row(
                    $variation_map[$id] ?? null,
                    $is_array ? ($variation['rate'] ?? 0) : ($variation->rate ?? 0)
                );

                if (is_array($products[$product_key])) {
                    if ($is_array) {
                        $products[$product_key]['variations'][$variation_key]['rate'] = $resolved['rate'];
                    } else {
                        $products[$product_key]['variations'][$variation_key]->rate = $resolved['rate'];
                    }
                    continue;
                }
                if ($is_array) {
                    $products[$product_key]->variations[$variation_key]['rate'] = $resolved['rate'];
                    continue;
                }
                $products[$product_key]->variations[$variation_key]->rate = $resolved['rate'];
            }
        }
    }
}

if (!function_exists('products_currency_charges_tax')) {
    /**
     * Whether orders in this currency are taxed.
     *
     * A merchant selling across borders often charges tax only in their home
     * market, so tax is a per-currency decision. Currencies listed in
     * product_currencies_no_tax are sold tax free: product taxes and shipping
     * tax are both skipped.
     *
     * @param int|null $currency_id defaults to the active currency
     */
    function products_currency_charges_tax($currency_id = null)
    {
        if (!products_multicurrency_enabled()) {
            return true;
        }

        if ($currency_id === null) {
            $active      = products_active_currency();
            $currency_id = $active ? (int) $active->id : 0;
        }
        $currency_id = (int) $currency_id;
        if ($currency_id < 1) {
            return true;
        }

        $tax_free_ids = array_filter(array_map('intval', explode(',', (string) get_option('product_currencies_no_tax'))));

        return !in_array($currency_id, $tax_free_ids, true);
    }
}

if (!function_exists('products_coupon_valid_for_currency')) {
    /**
     * Fixed-amount coupons and minimum-spend thresholds are denominated in one
     * currency, so they only apply to orders in that currency. Percentage
     * coupons, and coupons left at "any currency", apply everywhere.
     *
     * @param object|array $coupon
     */
    function products_coupon_valid_for_currency($coupon, $currency_id = null)
    {
        if (empty($coupon)) {
            return false;
        }

        $coupon_currency = (int) (is_array($coupon) ? ($coupon['currency'] ?? 0) : ($coupon->currency ?? 0));
        if (!$coupon_currency) {
            return true;
        }

        if ($currency_id === null) {
            $active      = products_active_currency();
            $currency_id = $active ? (int) $active->id : 0;
        }

        return $coupon_currency === (int) $currency_id;
    }
}
