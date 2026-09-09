<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
class Client extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->_capture_referral();
    }

    private function _capture_referral()
    {
        if (get_option('product_referral_enabled') != '1') {
            return;
        }
        $ref = $this->input->get('ref');
        if (!empty($ref)) {
            $this->load->model('products/product_referrals_model');
            $code = $this->product_referrals_model->get_code_by_code($ref);
            if ($code) {
                $this->session->set_userdata('product_referral_code_id', $code->id);
            }
        }
    }

    public function my_cart()
    {
        $ids = $this->input->get('id');
        if (!empty($ids)) {
            foreach ($ids as $product_id) {
                $cart_data = $newdata['cart_data'] = $this->session->cart_data;
                $qty = 1;
                if (!empty($cart_data)) {
                    foreach ($cart_data as $index => $value) {
                        if ($value['product_id'] == $product_id) {
                            $newdata['cart_data'][$index]['quantity'] = $value['quantity'] + 1;
                        }
                    }
                }
                $this->session->set_userdata($newdata);
                $cart_data = $this->session->cart_data;
            }
        }
        redirect('products/client/place_order');
    }
	
    public function manualorder()
    {
        $this->buy($this->input->get('id'));
    }

    /**
     * Stand-alone buy link, for merchants who build their own sales page.
     *
     *   /products/client/buy/<product_id>
     *   /products/client/buy/<product_id>/<quantity>
     *   /products/client/buy/<product_id>/<quantity>/<product_variation_id>
     *
     * Optional query parameters:
     *   ?currency=<id>  price the order in a specific selling currency
     *   ?cart=1         add to the cart and stay on the store instead of
     *                   going straight to checkout
     *
     * The product is added to the cart and the visitor lands on checkout.
     */
    public function buy($product_id = null, $quantity = 1, $product_variation_id = null)
    {
        if (0 != get_option('product_menu_disabled')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }

        $product_id = (int) $product_id;
        $quantity   = max(1, (int) $quantity);

        $product = $product_id ? $this->products_model->get_by_id_product($product_id) : null;
        if (empty($product)) {
            set_alert('danger', _l('product_buy_link_invalid'));
            redirect(site_url('products/client'));
        }

        // Honour an explicit currency so a sales page can link to the USD or
        // CAD version of the same product. Invalid ids are ignored.
        $requested_currency = $this->input->get('currency');
        if (!empty($requested_currency)) {
            products_set_active_currency($requested_currency);
        }

        $product_variation_id = (int) $product_variation_id;
        if ($product_variation_id) {
            $belongs = $this->db
                ->where('id', $product_variation_id)
                ->where('product_id', $product_id)
                ->get(db_prefix() . 'product_variations')
                ->row();
            if (!$belongs) {
                $product_variation_id = 0;
            }
        }

        if (products_tracks_stock($product) && (int) $product->quantity_number < 1) {
            set_alert('warning', _l('out_of_stock'));
            redirect(site_url('products/client'));
        }

        $cart_data = $this->session->cart_data;
        if (empty($cart_data)) {
            $cart_data = [];
        }

        $found = false;
        foreach ($cart_data as $index => $item) {
            $item_variation = (int) ($item['product_variation_id'] ?? 0);
            if ((int) $item['product_id'] === $product_id && $item_variation === $product_variation_id) {
                $cart_data[$index]['quantity'] = (int) $item['quantity'] + $quantity;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $new_item = [
                'product_id' => $product_id,
                'quantity'   => $quantity,
            ];
            if ($product_variation_id) {
                $new_item['product_variation_id'] = $product_variation_id;
            }
            $cart_data[] = $new_item;
        }

        $this->session->set_userdata('cart_data', $cart_data);

        if ($this->input->get('cart')) {
            set_alert('success', _l('product_added_to_cart_success'));
            redirect(site_url('products/client'));
        }

        redirect(site_url('products/client/place_order'));
    }


    public function get_my_cart()
    {
        echo json_encode($this->session->cart_data);
    }

    private function track_abandoned_cart($cart_data, $cart_products)
    {
        if (!$this->db->table_exists(db_prefix() . 'product_abandoned_cart')) {
            return;
        }
        $total = 0;
        foreach ($cart_products as $p) {
            $total += ($p->quantity ?? 0) * ($p->rate ?? 0);
        }
        $client_id = is_client_logged_in() ? get_client_user_id() : null;
        $session_id = session_id();
        $this->db->where('session_id', $session_id);
        $this->db->delete(db_prefix() . 'product_abandoned_cart');
        $this->db->insert(db_prefix() . 'product_abandoned_cart', [
            'client_id' => $client_id,
            'session_id' => $session_id,
            'cart_data' => json_encode($cart_data),
            'cart_total' => $total,
            'dateadded' => date('Y-m-d H:i:s'),
        ]);
    }

    private function clear_abandoned_cart()
    {
        if (!$this->db->table_exists(db_prefix() . 'product_abandoned_cart')) {
            return;
        }
        $client_id = get_client_user_id();
        $session_id = session_id();
        $this->db->where('session_id', $session_id);
        $this->db->delete(db_prefix() . 'product_abandoned_cart');
        if ($client_id) {
            $this->db->where('client_id', $client_id);
            $this->db->delete(db_prefix() . 'product_abandoned_cart');
        }
    }

    private function get_cart_product($product_id)
    {
        $cart_data     = $this->session->cart_data;
        if (!empty($cart_data)) {
            foreach ($cart_data as $cart_item) {
                if ($cart_item['product_id'] == $product_id) {
                    return $cart_item;
                }
            }
        }

        return [];
    }

    private function get_cart_product_ids()
    {
        $cart_data     = $this->session->cart_data;
        $cart_product_ids = [];
        if (!empty($cart_data)) {
            foreach ($cart_data as $cart_item) {
                $cart_product_ids[] = $cart_item['product_id'];
            }
        }

        return $cart_product_ids;
    }

    public function index()
    {
        if (0 != get_option('product_menu_disabled')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }
        $this->load->model('product_category_model');
        $data['title']              = _l('products');
        $data['products']           = $this->products_model->get_by_id_product();
        products_apply_currency_to_list($data['products']);
        $data['product_categories'] = $this->product_category_model->get();
        $data['selling_currencies'] = products_enabled_currencies();
        $data['active_currency']    = products_active_currency();
        $this->data($data);
        $this->view('clients/products');
        $this->layout();
    }

    public function product($slug = null)
    {
        if (0 != get_option('product_menu_disabled') || get_option('product_detail_pages_enabled') == '0') {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }
        if (empty($slug)) {
            redirect(site_url('products/client'));
        }
        $product = null;
        if (is_numeric($slug) && (int) $slug > 0) {
            $product = $this->products_model->get_by_id_product((int) $slug);
        }
        if (!$product) {
            $product = $this->products_model->get_by_slug($slug);
        }
        if (!$product) {
            show_404();
        }
        $this->load->model('product_category_model');
        products_apply_currency_to_product($product);
        $data['product']            = $product;
        $data['product_categories'] = $this->product_category_model->get();
        $data['base_currency']      = products_active_currency();
        $data['selling_currencies'] = products_enabled_currencies();
        $data['cart_data']          = $this->get_cart_product($product->id);
        $data['product_image_url']  = !empty($product->product_image) ? products_get_product_image_url($product->product_image) : products_get_no_image_url();
        $data['no_image_url']       = products_get_no_image_url();
        $this->load->helper('text');
        $data['title']              = !empty($product->meta_title) ? $product->meta_title : $product->product_name;
        $data['meta_description']   = !empty($product->meta_description) ? $product->meta_description : character_limiter(strip_tags($product->product_description), 160);
        $data['canonical_url']      = site_url('products/client/product/' . $product->slug);
        if (get_option('product_reviews_enabled') == '1') {
            $this->load->model('products/product_reviews_model');
            $data['review_stats'] = $this->product_reviews_model->get_rating_stats($product->id);
            $data['reviews'] = $this->product_reviews_model->get_by_product($product->id);
            $data['can_review'] = is_client_logged_in() && $this->product_reviews_model->can_review($product->id, get_client_user_id()) && $this->product_reviews_model->has_purchased($product->id, get_client_user_id());
        } else {
            $data['review_stats'] = ['avg' => 0, 'count' => 0];
            $data['reviews'] = [];
            $data['can_review'] = false;
        }
        if (get_option('product_social_proof_enabled') == '1') {
            $sold = $this->db->select('SUM(oi.qty) as total')->from(db_prefix() . 'order_items oi')
                ->join(db_prefix() . 'order_master om', 'om.id = oi.order_id')
                ->where('om.status', 2)->where('oi.product_id', $product->id)->get()->row();
            $data['product_sold_count'] = ($sold && isset($sold->total) && $sold->total !== null && $sold->total !== '') ? (int) $sold->total : 0;
            $hours = (int) get_option('product_social_proof_recent_hours');
            if ($hours < 1) {
                $hours = 24;
            }
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
            $recent = $this->db->where('product_id', $product->id)->where('message_type', 'recent_purchase')
                ->where('active', 1)->where('datecreated >=', $cutoff)
                ->order_by('datecreated', 'DESC')->limit(5)->get(db_prefix() . 'product_social_proof')->result_array();
            $data['product_recent_purchases'] = $recent ?: [];
        } else {
            $data['product_sold_count'] = 0;
            $data['product_recent_purchases'] = [];
        }
        if (get_option('product_recommendations_enabled') == '1') {
            $this->load->model('products/product_recommendations_model');
            $recs = $this->product_recommendations_model->get_also_bought($product->id, 6);
            if (empty($recs) && !empty($product->product_category_id)) {
                $recs = $this->product_recommendations_model->get_by_category($product->id, [$product->product_category_id], 6);
            }
            $data['recommended_products'] = $recs;
        } else {
            $data['recommended_products'] = [];
        }
        $this->data($data);
        $this->view('clients/product_detail');
        $this->layout();
    }

    public function submit_review()
    {
        if (get_option('product_reviews_enabled') != '1' || !is_client_logged_in()) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => false, 'message' => _l('access_denied')]);
                return;
            }
            redirect(site_url());
        }
        $this->load->model('products/product_reviews_model');
        $product_id = (int) $this->input->post('product_id');
        $rating = (int) $this->input->post('rating');
        $review_text = $this->input->post('review_text');
        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }
        $client_id = get_client_user_id();
        if (!$this->product_reviews_model->can_review($product_id, $client_id)) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => false, 'message' => _l('product_review_already_submitted')]);
                return;
            }
            set_alert('warning', _l('product_review_already_submitted'));
            $p = $this->products_model->get($product_id);
            redirect($p && !empty($p->slug) ? site_url('products/client/product/' . $p->slug) : site_url('products/client'));
        }
        if (!$this->product_reviews_model->has_purchased($product_id, $client_id)) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => false, 'message' => _l('product_review_purchase_required')]);
                return;
            }
            set_alert('warning', _l('product_review_purchase_required'));
            redirect(site_url('products/client'));
        }
        $contact_id = get_primary_contact_user_id($client_id);
        $this->product_reviews_model->add([
            'product_id' => $product_id,
            'client_id' => $client_id,
            'contact_id' => $contact_id,
            'rating' => $rating,
            'review_text' => $review_text,
            'approved' => 0,
        ]);
        if ($this->input->is_ajax_request()) {
            echo json_encode(['success' => true, 'message' => _l('product_review_submitted')]);
            return;
        }
        set_alert('success', _l('product_review_submitted'));
        $p = $this->products_model->get($product_id);
        redirect($p && !empty($p->slug) ? site_url('products/client/product/' . $p->slug) : site_url('products/client'));
    }

    public function buy_gift_card()
    {
        if (get_option('product_gift_cards_enabled') != '1' || !is_client_logged_in()) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }
        $this->load->model('products/product_gift_cards_model');
        $data['templates'] = $this->product_gift_cards_model->get_templates();
        $data['title'] = _l('product_buy_gift_card');
        $this->data($data);
        $this->view('clients/buy_gift_card');
        $this->layout();
    }

    public function purchase_gift_card()
    {
        if (get_option('product_gift_cards_enabled') != '1' || !is_client_logged_in()) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }
        $amount = (float) $this->input->post('amount');
        $min = (float) (get_option('product_gift_card_min_amount') ?: 1);
        if ($amount < $min) {
            set_alert('danger', _l('product_gift_card_min_error'));
            redirect(site_url('products/client/buy_gift_card'));
        }
        $client_id = get_client_user_id();
        $billing = $this->clients_model->get_customer_billing_and_shipping_details($client_id);
        $billing = reset($billing);
        $this->load->model(['invoices_model', 'currencies_model']);
        // Bill the gift card in the currency the customer is shopping in, so the
        // card's stored currency matches the invoice they actually paid.
        $base = products_active_currency();
        $post = [
            'clientid' => $client_id,
            'date' => _d(date('Y-m-d')),
            'duedate' => _d(date('Y-m-d', strtotime('+30 days'))),
            'currency' => $base->id,
            'show_quantity_as' => 1,
            'number' => get_option('next_invoice_number'),
            'billing_street' => $billing['billing_street'] ?? '',
            'billing_city' => $billing['billing_city'] ?? '',
            'billing_state' => $billing['billing_state'] ?? '',
            'billing_zip' => $billing['billing_zip'] ?? '',
            'billing_country' => $billing['billing_country'] ?? 0,
            'show_shipping_on_invoice' => 'off',
        ];
        $post['newitems'] = [
            [
                'unit' => '',
                'order' => 1,
                'description' => _l('product_gift_card'),
                'long_description' => '',
                'taxname' => '',
                'rate' => $amount,
                'qty' => 1,
                'recurring' => 0,
                'recurring_type' => '',
                'custom_recurring' => '',
                'cycles' => 0,
            ],
        ];
        $invoice_id = $this->invoices_model->add($post);
        if (!$invoice_id) {
            set_alert('danger', _l('order_fail'));
            redirect(site_url('products/client/buy_gift_card'));
        }
        $this->db->insert(db_prefix() . 'product_gift_card_purchases', [
            'invoice_id' => $invoice_id,
            'template_id' => $this->input->post('template_id') ?: null,
            'amount' => $amount,
            'recipient_email' => $this->input->post('recipient_email') ?: null,
            'recipient_name' => $this->input->post('recipient_name') ?: null,
            'message' => $this->input->post('message') ?: null,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        $inv = $this->invoices_model->get($invoice_id);
        redirect(site_url('invoice/' . $invoice_id . '/' . $inv->hash));
    }

    public function apply_gift_card()
    {
        if (get_option('product_gift_cards_enabled') != '1') {
            echo json_encode(['status' => false, 'message' => _l('access_denied')]);
            return;
        }
        $code = trim($this->input->post('gift_card_code'));
        if (empty($code)) {
            echo json_encode(['status' => false, 'message' => _l('product_gift_card_code_required')]);
            return;
        }
        $this->load->model('products/product_gift_cards_model');
        $gc = $this->product_gift_cards_model->get_by_code($code);
        if (!$gc || (float) $gc->balance <= 0) {
            echo json_encode(['status' => false, 'message' => _l('product_gift_card_invalid')]);
            return;
        }
        // A card holds a balance in one currency. Spending it against an order in
        // another currency would silently convert at 1:1, so refuse instead.
        $active_currency = products_active_currency();
        if (!empty($gc->currency) && !empty($active_currency) && (int) $gc->currency !== (int) $active_currency->id) {
            echo json_encode(['status' => false, 'message' => _l('product_gift_card_wrong_currency')]);
            return;
        }
        $this->session->set_userdata('product_gift_card_id', $gc->id);
        $this->session->set_userdata('product_gift_card_balance', (float) $gc->balance);
        echo json_encode(['status' => true, 'balance' => (float) $gc->balance]);
    }

    public function remove_gift_card()
    {
        $this->session->unset_userdata('product_gift_card_id');
        $this->session->unset_userdata('product_gift_card_balance');
        echo json_encode(['status' => true]);
    }

    public function referral()
    {
        if (get_option('product_referral_enabled') != '1' || !is_client_logged_in()) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }
        $this->load->model('products/product_referrals_model');
        $ref = $this->product_referrals_model->get_or_create_for_client(get_client_user_id());
        $data['referral_code'] = $ref;
        $data['base_currency'] = products_active_currency();
        $data['title'] = _l('product_referral_program');
        $this->data($data);
        $this->view('clients/referral');
        $this->layout();
    }

    public function filter()
    {
        $p_category_id = $this->input->post('p_category_id');
        $cart_data     = $this->session->cart_data;
        $products      = $this->products_model->get_category_filter($p_category_id);
        $base_currency = products_active_currency();
        products_apply_currency_to_list($products, $base_currency);
        foreach ($products as $key => $value) {
            $products[$key]['cart_data']          = $this->get_cart_product($value['id']);
            $products[$key]['product_image_url']  = !empty($value['product_image']) ? products_get_product_image_url($value['product_image']) : products_get_no_image_url();
            $products[$key]['no_image_url']       = products_get_no_image_url();
            $products[$key]['base_currency_name'] = $base_currency->name;
            $taxes                                = unserialize($value['taxes']);
            $total_tax                            = 0;
            if (!empty($taxes)) {
                foreach ($taxes as $tax) {
                    if (!is_array($tax)) {
                        $tmp_taxname = $tax;
                        $tax_array   = explode('|', $tax);
                    } else {
                        $tax_array   = explode('|', $tax['taxname']);
                        $tmp_taxname = $tax['taxname'];
                        if ('' == $tmp_taxname) {
                            continue;
                        }
                    }
                    $total_tax += $tax_array[1];
                }
            }
            $products[$key]['total_tax'] = products_currency_charges_tax($base_currency->id ?? null) ? $total_tax : 0;
            $products[$key]['qty'] = _l('qty');
            $products[$key]['add_to_cart'] = _l('add_to_cart');
            $products[$key]['update_cart'] = _l('update_cart');
            $products[$key]['out_of_stock'] = _l('out_of_stock');
            $low_qty = (int) get_option('product_low_quantity');
            // Services and digital products are unlimited, so they never show
            // stock warnings and never read as out of stock.
            $tracks_stock                  = products_tracks_stock($value);
            $products[$key]['tracks_stock'] = $tracks_stock ? 1 : 0;
            $products[$key]['low_stock'] = ($tracks_stock && get_option('product_urgency_enabled') == '1' && (int) $value['quantity_number'] > 0 && (int) $value['quantity_number'] <= $low_qty) ? 1 : 0;
            $products[$key]['sale_price'] = isset($value['sale_price']) ? $value['sale_price'] : null;
            $products[$key]['sale_price_end'] = isset($value['sale_price_end']) ? $value['sale_price_end'] : null;
            $products[$key]['in_wishlist'] = 0;
            if (get_option('product_wishlist_enabled') != '0' && is_client_logged_in()) {
                $this->load->model('products/wishlist_model');
                $products[$key]['in_wishlist'] = $this->wishlist_model->get_item(get_client_user_id(), $value['id'], null) ? 1 : 0;
            }
        }
        echo json_encode($products);
    }

    private function sort_cart($cart_data)
    {
        $cart_data_keys = array_keys($cart_data);
        $first_index = 0;
        while ($first_index < count($cart_data_keys) - 1) {
            $sorted_count = 0;
            for ($second_index = $first_index + 2; $second_index < count($cart_data_keys); $second_index++) {
                if ($cart_data[$cart_data_keys[$first_index]]['product_id'] == $cart_data[$cart_data_keys[$second_index]]['product_id']) {
                    $replace_cart_item = $cart_data[$cart_data_keys[$second_index]];
                    for ($third_index = $second_index; $third_index > $first_index + $sorted_count + 1; $third_index--) {
                        $cart_data[$cart_data_keys[$third_index]] = $cart_data[$cart_data_keys[$third_index - 1]];
                    }
                    $cart_data[$cart_data_keys[$first_index + $sorted_count + 1]] = $replace_cart_item;
                    $sorted_count = $sorted_count + 1;
                }
            }
            $first_index = $first_index + $sorted_count + 1;
        }
        return $cart_data;
    }

    public function add_cart()
    {
        $product_id           = $this->input->post('product_id');
        $product_variation_id = $this->input->post('product_variation_id');
        $quantity             = $this->input->post('quantity');
        $newdata['cart_data'] = $this->session->cart_data;
        if (empty($newdata['cart_data'])) {
            $newdata['cart_data'] = [
                ['product_id' => $product_id, 'product_variation_id' => $product_variation_id, 'quantity' => $quantity]
            ];
            $this->session->set_userdata($newdata);
        } else {
            $cart_item_exist = false;
            foreach ($newdata['cart_data'] as $cart_item_index => $cart_item) {
                if ($cart_item['product_id'] == $product_id && $cart_item['product_variation_id'] == $product_variation_id) {
                    $newdata['cart_data'][$cart_item_index]['quantity'] = $quantity;
                    $cart_item_exist = true;
                }
            }
            if (!$cart_item_exist) {
                $newdata['cart_data'][] = ['product_id' => $product_id, 'product_variation_id' => $product_variation_id, 'quantity' => $quantity];
            }
            $newdata['cart_data'] = $this->sort_cart($newdata['cart_data']);
            $this->session->set_userdata($newdata);
        }
        
        echo json_encode($this->session->cart_data);
    }

    public function popup_track($action, $id)
    {
        $id = (int) $id;
        if (!$id || !in_array($action, ['impression', 'click'])) {
            return;
        }
        $this->load->model('products/exit_popups_model');
        if ($action === 'impression') {
            $this->exit_popups_model->increment_impressions($id);
        } else {
            $this->exit_popups_model->increment_clicks($id);
        }
        if ($this->input->is_ajax_request() || $this->input->post()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => 1]);
        }
    }

    public function remove_cart($product_id = null, $product_variation_id = null, $return = false)
    {
        if (empty($product_id)) {
            $product_id = $this->input->post('product_id');
        }
        if (empty($product_variation_id)) {
            $product_variation_id = $this->input->post('product_variation_id');
        }
        $newdata['cart_data'] = $this->session->cart_data;
        foreach ($newdata['cart_data'] as $key => $value) {
            if ($product_id == $value['product_id'] && $product_variation_id == $value['product_variation_id']) {
                unset($newdata['cart_data'][$key]);
            }
        }
        $cart_data = [];
        foreach ($newdata['cart_data'] as $value) {
            $cart_data[] = $value;
        }
        $newdata['cart_data'] = $cart_data;
        $this->session->set_userdata($newdata);
        if (empty($newdata['cart_data'])) {
            set_alert('danger', _l('Cart is empty'));
            $res['status'] = false;
            if ($return) {
                return json_encode($res);
            }
            echo json_encode($res);

            return;
        }
        $res['status'] = true;
        $res['cart_data'] = $newdata['cart_data'];
        if ($return) {
            return json_encode($res);
        }
        echo json_encode($res);
    }

    public function get_currency($id)
    {
        echo json_encode(get_currency($id));
    }

    /**
     * Switch the currency the customer is shopping in.
     *
     * The cart stores product ids and quantities rather than prices, so it is
     * re-priced from product_prices on the next read and needs no rebuilding.
     * products_set_active_currency() rejects anything not enabled for selling.
     */
    public function set_currency($currency_id = null)
    {
        $accepted = products_set_active_currency($currency_id);

        if ($this->input->is_ajax_request()) {
            echo json_encode(['success' => $accepted]);

            return;
        }

        if (!$accepted) {
            set_alert('warning', _l('product_currency_not_available'));
        }

        // Only ever return to our own site - the referer is client controlled.
        $referer = (string) $this->input->server('HTTP_REFERER');
        $fallback = site_url('products/client');
        redirect(strpos($referer, base_url()) === 0 ? $referer : $fallback);
    }

    public function place_order($product_id = false)
    {
        if (0 != get_option('product_menu_disabled')) {
            $this->session->unset_userdata('cart_data');
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }
        $this->load->model('products/order_model');
        // Guest checkout is beta and off by default. When it is off this branch
        // behaves exactly as it always has.
        $is_guest_checkout = false;
        if (!is_client_logged_in()) {
            if (!products_guest_checkout_enabled()) {
                $this->session->set_userdata('products_redirect_after_login', site_url('products/client/place_order'));
                set_alert('warning', _l('clients_login_heading_no_register'));
                redirect(site_url('authentication/login'));
            }
            $is_guest_checkout = true;
        }
        $data['is_guest_checkout'] = $is_guest_checkout;
        $message          = '';
        $post = $this->input->post();
        unset($post['taxes']);
        unset($post['shipping_cost']);
        $guest_input             = [];
        $guest_created_client_id = 0;
        if (!empty($post) && empty($post['product_items'])) {
            // A submit with nothing to order: fall through and re-render.
            $post = [];
        }
        if (!empty($post)) {
            $post['product_items'] = $this->sort_cart($post['product_items']);

            // Resolve the customer server side. Never from the request: a posted
            // clientid would let anyone bill an order to another customer.
            if ($is_guest_checkout) {
                $guest_input = $this->guest_input_from_post($post);
                $guest       = $this->create_guest_customer($guest_input);
                if (!$guest['status']) {
                    set_alert('danger', $guest['message']);
                    $message .= $guest['message'];
                    $post = [];
                } else {
                    $post['clientid']        = $guest['client_id'];
                    $guest_created_client_id = $guest['client_id'];
                }
            } else {
                $post['clientid'] = get_client_user_id();
            }
        }
        if (!empty($post)) {
            if (get_option('product_gift_cards_enabled') == '1' && $this->session->userdata('product_gift_card_id')) {
                $post['gift_card_id'] = $this->session->userdata('product_gift_card_id');
            }
            if (get_option('product_referral_enabled') == '1' && $this->session->userdata('product_referral_code_id')) {
                $post['referral_code_id'] = $this->session->userdata('product_referral_code_id');
                $this->session->unset_userdata('product_referral_code_id');
            }
            if (get_option('product_newsletter_enabled') == '1' && !empty($post['product_marketing_consent'])) {
                $contact_id = get_primary_contact_user_id($post['clientid']);
                if ($contact_id) {
                    $this->db->where('id', $contact_id);
                    $this->db->update(db_prefix() . 'contacts', ['product_marketing_consent' => 1]);
                }
            }
            $return_data = $this->order_model->add_invoice_order($post);
            if ($return_data['status']) {
                $this->session->unset_userdata('cart_data');
                if (get_option('product_gift_cards_enabled') == '1') {
                    $this->session->unset_userdata('product_gift_card_id');
                    $this->session->unset_userdata('product_gift_card_balance');
                }
                if (get_option('product_abandoned_cart_tracking_enabled') != '0') {
                    $this->clear_abandoned_cart();
                }
                // The order exists, so it is safe to invite the guest to set a
                // password. This never blocks or fails the order.
                if ($is_guest_checkout) {
                    $this->send_guest_password_invite($guest_input['email'] ?? '');
                }
                set_alert('success', _l('order_success'));
                $redirect_url = $return_data['single_invoice']
                    ? site_url('invoice/' . $return_data['invoice_id'] . '/' . $return_data['invoice_hash'])
                    : site_url('clients/invoices');
                if (get_option('product_remarketing_facebook_enabled') == '1' || get_option('product_remarketing_google_enabled') == '1') {
                    $thankyou = site_url('products/client/thankyou?order_id=' . ($return_data['order_id'] ?? '') . '&total=' . ($return_data['total'] ?? 0) . '&redirect=' . urlencode($redirect_url));
                    redirect($thankyou, 'refresh');
                } else {
                    redirect($redirect_url, 'refresh');
                }
            }
            if (!$return_data['status']) {
                // Roll back the customer we just created for this guest, so a
                // failed checkout does not litter the CRM with empty records.
                if ($guest_created_client_id) {
                    $this->delete_guest_customer($guest_created_client_id);
                    $guest_created_client_id = 0;
                }
                set_alert('error', _l('order_fail'));
                $message .= $return_data['message'];
            }
        }
        if (empty($this->session->cart_data)) {
            set_alert('danger', _l('Cart is empty'));
            redirect(site_url('products/client/'));
        }
        $cart_data = $this->sort_cart($this->session->cart_data);
        if (empty($cart_data)) {
            set_alert('danger', _l('Cart is empty'));
            redirect(site_url('products/client/'));
        }
        $data['products'] = $product = $this->products_model->get_by_cart_product($cart_data);
        if (empty($product)) {
            set_alert('danger', _l('Products in Cart not found'));
            redirect(site_url('products/client/'));
        }
        if (get_option('product_abandoned_cart_tracking_enabled') != '0') {
            $this->track_abandoned_cart($cart_data, $product);
        }
        $all_taxes        = [];
        $init_tax         = [];
        $apply_shipping   = false;
        foreach ($product as $value) {
            if (products_tracks_stock($value)) {
                if ((int) $value->quantity_number < 1) {
                    $this->remove_cart($value->id, $value->product_variation_id ?? '', true);
                    $message .= $value->product_name . ' is out of stock so removed from cart <br>';
                    continue;
                }
                if ((int) $value->quantity > (int) $value->quantity_number) {
                    $value->quantity = $value->quantity_number;
                    $message         .= $value->product_name . ' is only ' . $value->quantity_number . ' in stock so quantity reduced to that quantity <br>';
                }
            }
            // Nothing is shipped for a service, a digital download, or a
            // recurring subscription.
            $value->apply_shipping = false;
            if (!$value->recurring && products_tracks_stock($value)) {
                $value->apply_shipping = true;
                $apply_shipping = true;
            }
            $taxes_arr       = [];
            $value->taxname  = $taxes  = unserialize($value->taxes);
            // Currencies flagged tax free are sold without any tax at all.
            if (!products_currency_charges_tax()) {
                $value->taxname = $taxes = [];
            }
            if ($taxes) {
                foreach ($taxes as $tax) {
                    if (!is_array($tax)) {
                        $tmp_taxname = $tax;
                        $tax_array   = explode('|', $tax);
                    } else {
                        $tax_array   = explode('|', $tax['taxname']);
                        $tmp_taxname = $tax['taxname'];
                        if ('' == $tmp_taxname) {
                            continue;
                        }
                    }
                    $init_tax[$tmp_taxname][]  = ($value->rate * $value->quantity) / 100 * $tax_array[1];
                    $all_taxes[$tmp_taxname]   = $taxes_arr[]   = ['name' => $tmp_taxname, 'taxrate' => $tax_array[1], 'taxname' => $tax_array[0]];
                }
            }
            $value->taxes = $taxes_arr;
        }
        $shipping_cost = 0;
        $base_shipping_cost = 0;
        $shipping_tax = 0;
        if ($apply_shipping) {
            $taxname = (products_currency_charges_tax() && !empty((get_option('product_tax_for_shipping_cost')))) ? unserialize(get_option('product_tax_for_shipping_cost')) : '';
            $shipping_cost = $base_shipping_cost = get_option('product_flat_rate_shipping');
            $shipping_tax = 0;
            if ($taxname) {
                foreach ($taxname as $tax) {
                    if (!is_array($tax)) {
                        $tmp_taxname = $tax;
                        $tax_array   = explode('|', $tax);
                    } else {
                        $tax_array   = explode('|', $tax['taxname']);
                        $tmp_taxname = $tax['taxname'];
                        if ('' == $tmp_taxname) {
                            continue;
                        }
                    }
                    $shipping_tax  += $tax_array[1];
                    $shipping_cost += ($base_shipping_cost) / 100 * $tax_array[1];
                }
            }
        }
        $data['shipping_cost']    = $shipping_cost;
        $data['shipping_base']    = $base_shipping_cost;
        $data['shipping_tax']     = $shipping_tax;
        $data['all_taxes']        = $all_taxes;
        $data['init_tax']         = $init_tax;
        $data['message']          = $message;
        $data['title']            = _l('confirm') . ' ' . _l('place_order');
        $data['base_currency'] = products_active_currency();
        // Echo the guest's details back so a validation failure does not make
        // them retype the whole form.
        $data['guest_input']   = $guest_input;
        $this->data($data);
        $this->view('clients/place_order');
        $this->layout();
    }

    /**
     * Pull the guest checkout fields out of the posted form and normalise them.
     * Kept separate so the values can be echoed back into the form when
     * validation fails, without the visitor retyping everything.
     */
    private function guest_input_from_post($post)
    {
        $fields = [
            'firstname', 'lastname', 'email', 'company', 'phonenumber',
            'address', 'city', 'state', 'zip', 'country',
        ];

        $guest = [];
        foreach ($fields as $field) {
            $guest[$field] = isset($post['guest'][$field]) ? trim((string) $post['guest'][$field]) : '';
        }

        return $guest;
    }

    /**
     * Create the customer record behind a guest order.
     *
     * Perfex bills against an invoice and an invoice must belong to a customer,
     * so the record has to exist before payment can be taken. The visitor never
     * sees a signup screen: they fill in the checkout form and go straight to
     * the payment page.
     *
     * Deliberate decisions:
     * - An email that already belongs to a contact is refused rather than
     *   reused. Attaching an order to an existing account from an
     *   unauthenticated form would be an account takeover.
     * - The guest is never logged in. They are sent to the invoice via its
     *   hash URL, which Perfex serves without a session.
     *
     * @return array{status: bool, client_id: int, message: string}
     */
    private function create_guest_customer($guest)
    {
        $fail = function ($message) {
            return ['status' => false, 'client_id' => 0, 'message' => $message];
        };

        if ($guest['firstname'] === '' || $guest['lastname'] === '' || $guest['email'] === '') {
            return $fail(_l('product_guest_required_fields'));
        }
        if (!filter_var($guest['email'], FILTER_VALIDATE_EMAIL)) {
            return $fail(_l('product_guest_invalid_email'));
        }

        // Someone already owns this email. Send them to log in instead, and
        // bring them back to checkout with their cart intact.
        $existing = $this->db
            ->where('email', $guest['email'])
            ->get(db_prefix() . 'contacts')
            ->row();
        if ($existing) {
            $this->session->set_userdata('products_redirect_after_login', site_url('products/client/place_order'));

            return $fail(_l('product_guest_email_exists'));
        }

        $this->load->model('clients_model');
        if (!method_exists($this->clients_model, 'add') || !method_exists($this->clients_model, 'add_contact')) {
            log_message('error', 'Products guest checkout: clients_model is missing add()/add_contact()');

            return $fail(_l('product_guest_create_failed'));
        }

        // A company is required by Perfex for the customer name. Fall back to
        // the person's own name when the visitor is buying as an individual.
        $company = $guest['company'] !== ''
            ? $guest['company']
            : trim($guest['firstname'] . ' ' . $guest['lastname']);

        $client_data = [
            'company'         => $company,
            'phonenumber'     => $guest['phonenumber'],
            'address'         => $guest['address'],
            'city'            => $guest['city'],
            'state'           => $guest['state'],
            'zip'             => $guest['zip'],
            'country'         => (int) $guest['country'],
            'billing_street'  => $guest['address'],
            'billing_city'    => $guest['city'],
            'billing_state'   => $guest['state'],
            'billing_zip'     => $guest['zip'],
            'billing_country' => (int) $guest['country'],
        ];

        try {
            $client_id = $this->clients_model->add($client_data);
        } catch (\Throwable $e) {
            log_message('error', 'Products guest checkout: client create failed - ' . $e->getMessage());

            return $fail(_l('product_guest_create_failed'));
        }

        if (!$client_id) {
            return $fail(_l('product_guest_create_failed'));
        }

        // Flag the record so the merchant can tell guest-created customers
        // apart. Guarded: the column only exists once migration 153 has run.
        if ($this->db->field_exists('product_guest_customer', db_prefix() . 'clients')) {
            $this->db->where('userid', $client_id)->update(db_prefix() . 'clients', ['product_guest_customer' => 1]);
        }

        $contact_data = [
            'firstname'      => $guest['firstname'],
            'lastname'       => $guest['lastname'],
            'email'          => $guest['email'],
            'phonenumber'    => $guest['phonenumber'],
            'is_primary'     => 1,
            // Random and never shown. The visitor sets their own password from
            // the email sent after the order is placed.
            'password'       => function_exists('app_generate_hash')
                                    ? app_generate_hash()
                                    : bin2hex(random_bytes(16)),
            'donotsendwelcomeemail' => true,
        ];

        try {
            $contact_id = $this->clients_model->add_contact($contact_data, $client_id);
        } catch (\Throwable $e) {
            $contact_id = false;
            log_message('error', 'Products guest checkout: contact create failed - ' . $e->getMessage());
        }

        if (!$contact_id) {
            // Do not leave a customer with no contact behind.
            try {
                $this->clients_model->delete($client_id);
            } catch (\Throwable $e) {
                log_message('error', 'Products guest checkout: rollback failed - ' . $e->getMessage());
            }

            return $fail(_l('product_guest_create_failed'));
        }

        // Newsletter consent is handled by the shared block in place_order(),
        // which now resolves the primary contact for guests too.

        return ['status' => true, 'client_id' => (int) $client_id, 'message' => ''];
    }

    /**
     * Remove a customer this request created for a guest whose order then
     * failed. Only ever called with an id we created moments earlier, and only
     * when it is still flagged as a guest record with no invoices attached.
     */
    private function delete_guest_customer($client_id)
    {
        $client_id = (int) $client_id;
        if ($client_id < 1) {
            return;
        }

        // Never delete a customer that already has an invoice.
        $has_invoice = $this->db
            ->where('clientid', $client_id)
            ->count_all_results(db_prefix() . 'invoices');
        if ($has_invoice > 0) {
            return;
        }

        if ($this->db->field_exists('product_guest_customer', db_prefix() . 'clients')) {
            $is_guest_record = $this->db
                ->where('userid', $client_id)
                ->where('product_guest_customer', 1)
                ->count_all_results(db_prefix() . 'clients');
            if ($is_guest_record < 1) {
                return;
            }
        }

        try {
            $this->load->model('clients_model');
            $this->clients_model->delete($client_id);
        } catch (\Throwable $e) {
            log_message('error', 'Products guest checkout: cleanup failed - ' . $e->getMessage());
        }
    }

    /**
     * Invite the guest to set a password once their order exists. Failure here
     * must never fail the order, so everything is guarded and only logged.
     */
    private function send_guest_password_invite($email)
    {
        if (empty($email)) {
            return;
        }

        try {
            $this->load->model('clients_model');
            if (method_exists($this->clients_model, 'forgot_password')) {
                $this->clients_model->forgot_password($email);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Products guest checkout: password invite failed - ' . $e->getMessage());
        }
    }

    public function variation_values()
    {
        $product_id = $this->input->post('product_id');
        $variation_id = $this->input->post('variation_id');
        $variations = $this->products_model->get_by_id_variation_values($product_id, $variation_id);
        
        echo json_encode($variations);
    }

    private function get_tax_shipping()
    {
        $cart_data = $this->session->cart_data;
        if (empty($cart_data)) {
            set_alert('danger', _l('Cart is empty'));
            redirect(site_url('products/client/'));
        }
        $product = $this->products_model->get_by_cart_product($cart_data);
        if (empty($product)) {
            set_alert('danger', _l('Products in Cart not found'));
            redirect(site_url('products/client/'));
        }

        $all_taxes        = [];
        $init_tax         = [];
        $apply_shipping   = false;
        foreach ($product as $value) {
            $value->apply_shipping = false;
            if (!$value->recurring && products_tracks_stock($value)) {
                $value->apply_shipping = true;
                $apply_shipping = true;
            }
            $taxes_arr       = [];
            $value->taxname  = $taxes  = unserialize($value->taxes);
            // Currencies flagged tax free are sold without any tax at all.
            if (!products_currency_charges_tax()) {
                $value->taxname = $taxes = [];
            }
            if ($taxes) {
                foreach ($taxes as $tax) {
                    if (!is_array($tax)) {
                        $tmp_taxname = $tax;
                        $tax_array   = explode('|', $tax);
                    } else {
                        $tax_array   = explode('|', $tax['taxname']);
                        $tmp_taxname = $tax['taxname'];
                        if ('' == $tmp_taxname) {
                            continue;
                        }
                    }
                    $init_tax[$tmp_taxname][]  = ($value->rate * $value->quantity) / 100 * $tax_array[1];
                    $all_taxes[$tmp_taxname]   = $taxes_arr[]   = ['name' => $tmp_taxname, 'taxrate' => $tax_array[1], 'taxname' => $tax_array[0]];
                }
            }
            $value->taxes = $taxes_arr;
        }
        $shipping_cost = 0;
        $base_shipping_cost = 0;
        $shipping_tax = 0;
        if ($apply_shipping) {
            $taxname = (products_currency_charges_tax() && !empty((get_option('product_tax_for_shipping_cost')))) ? unserialize(get_option('product_tax_for_shipping_cost')) : '';
            $shipping_cost = $base_shipping_cost = get_option('product_flat_rate_shipping');
            $shipping_tax = 0;
            if ($taxname) {
                foreach ($taxname as $tax) {
                    if (!is_array($tax)) {
                        $tmp_taxname = $tax;
                        $tax_array   = explode('|', $tax);
                    } else {
                        $tax_array   = explode('|', $tax['taxname']);
                        $tmp_taxname = $tax['taxname'];
                        if ('' == $tmp_taxname) {
                            continue;
                        }
                    }
                    $shipping_tax  += $tax_array[1];
                    $shipping_cost += ($base_shipping_cost) / 100 * $tax_array[1];
                }
            }
        }

        return [
            'product' => $product,
            'all_taxes' => $all_taxes,
            'init_tax' => $init_tax,
            'apply_shipping' => $apply_shipping,
            'shipping_cost' => $shipping_cost,
            'base_shipping_cost' => $base_shipping_cost,
            'shipping_tax' => $shipping_tax,
        ];
    }

    public function apply_coupon($coupon_code = null)
    {
        if (0 != get_option('coupons_disabled')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }

        if (empty($coupon_code)) {
            $coupon_code = $this->input->post('coupon_code');
        }
        
        $this->load->model('products/products_model');
        
        $base_currency = products_active_currency();

        $this->load->model('products/coupons_model');
        $coupon = $this->coupons_model->get_by_code($coupon_code);

        // Tell the customer up front rather than letting checkout drop a coupon
        // that cannot apply to the currency they are shopping in.
        if ($coupon && !products_coupon_valid_for_currency($coupon, $base_currency->id ?? null)) {
            echo json_encode([
                'status'  => false,
                'message' => _l('product_coupon_wrong_currency'),
            ]);

            return;
        }

        if ($coupon) {
            $client_id = is_client_logged_in() ? get_client_user_id() : null;
            $tax_shipping_data = $this->get_tax_shipping();
            $subtotal_before_tax = 0;
            foreach ($tax_shipping_data['product'] as $value) {
                $subtotal_before_tax += $value->quantity * $value->rate;
            }
            $cart_eligible = empty($tax_shipping_data['product']) || $this->coupons_model->is_available_for_cart(
                $coupon->id,
                $client_id,
                $tax_shipping_data['product'],
                $subtotal_before_tax
            );
            if ($this->coupons_model->is_available($coupon->id, $client_id) && $cart_eligible) {
                $total = 0;
                foreach ($tax_shipping_data['product'] as $value) {
                    $total += $value->quantity * $value->rate;
                }
                foreach ($tax_shipping_data['all_taxes'] as $tax) {
                    $total += array_sum($tax_shipping_data['init_tax'][$tax['name']]);
                }
                if (!empty($tax_shipping_data['shipping_cost'])) {
                    $total += $tax_shipping_data['shipping_cost'];
                }
                if ($coupon->type == '%') {
                    $coupon_discount = $total * $coupon->amount / 100;
                } else {
                    $coupon_discount = $coupon->amount;
                }
                $total -= $coupon_discount;
                $res = [
                    'status' => true,
                    'coupon_id' => $coupon->id,
                    'coupon_discount' => app_format_money($coupon_discount, $base_currency->name),
                    'total' => app_format_money($total, $base_currency->name)
                ];
            } else {
                $res = [
                    'status' => false,
                    'message' => _l('coupon_can_not_apply')
                ];
            }
        } else {
            $res = [
                'status' => false,
                'message' => _l('coupon_does_not_exist')
            ];
        }
        echo json_encode($res);
    }

    public function wishlist()
    {
        if (get_option('product_wishlist_enabled') == '0') {
            show_404();
        }
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }
        $this->load->model('products/wishlist_model');
        $data['items'] = $this->wishlist_model->get_by_client(get_client_user_id());
        $data['base_currency'] = products_active_currency();
        $data['title'] = _l('wishlist');
        $data['product_image_base'] = rtrim(base_url('uploads/products'), '/');
        $this->data($data);
        $this->view('clients/wishlist');
        $this->layout();
    }

    public function subscribe_back_in_stock()
    {
        if (get_option('product_back_in_stock_enabled') != '1') {
            echo json_encode(['status' => 'error', 'message' => _l('access_denied')]);
            return;
        }
        $this->load->model('products/product_stock_notifications_model');
        $product_id = (int) $this->input->post('product_id');
        $email = $this->input->post('email');
        if (!$product_id || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => _l('product_notify_email_required')]);
            return;
        }
        $client_id = is_client_logged_in() ? get_client_user_id() : null;
        $res = $this->product_stock_notifications_model->subscribe($product_id, $email, $client_id);
        echo json_encode($res);
    }

    public function subscribe_price_drop()
    {
        if (get_option('product_price_drop_enabled') != '1') {
            echo json_encode(['status' => 'error', 'message' => _l('access_denied')]);
            return;
        }
        $this->load->model('products/product_price_alerts_model');
        $product_id = (int) $this->input->post('product_id');
        $email = $this->input->post('email');
        $target_price = $this->input->post('target_price') ? (float) $this->input->post('target_price') : null;
        if (!$product_id || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => _l('product_notify_email_required')]);
            return;
        }
        $client_id = is_client_logged_in() ? get_client_user_id() : null;
        $res = $this->product_price_alerts_model->subscribe($product_id, $email, $client_id, $target_price);
        echo json_encode($res);
    }

    public function add_wishlist()
    {
        if (!is_client_logged_in()) {
            echo json_encode(['status' => false, 'message' => _l('clients_login_heading_no_register')]);
            return;
        }
        $this->load->model('products/wishlist_model');
        $product_id = $this->input->post('product_id');
        $product_variation_id = $this->input->post('product_variation_id') ?: null;
        $added = $this->wishlist_model->add(get_client_user_id(), $product_id, $product_variation_id);
        echo json_encode(['status' => true, 'added' => $added]);
    }

    public function remove_wishlist()
    {
        if (!is_client_logged_in()) {
            echo json_encode(['status' => false]);
            return;
        }
        $this->load->model('products/wishlist_model');
        $product_id = $this->input->post('product_id');
        $product_variation_id = $this->input->post('product_variation_id') ?: null;
        $this->wishlist_model->remove(get_client_user_id(), $product_id, $product_variation_id);
        echo json_encode(['status' => true]);
    }

    public function toggle_wishlist()
    {
        if (!is_client_logged_in()) {
            echo json_encode(['status' => false, 'in_wishlist' => false]);
            return;
        }
        $this->load->model('products/wishlist_model');
        $product_id = $this->input->post('product_id');
        $product_variation_id = $this->input->post('product_variation_id') ?: null;
        $in_wishlist = $this->wishlist_model->toggle(get_client_user_id(), $product_id, $product_variation_id);
        echo json_encode(['status' => true, 'in_wishlist' => $in_wishlist]);
    }

    public function downloads()
    {
        if (get_option('product_digital_downloads_enabled') == '0') {
            show_404();
        }
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }
        $this->load->model('products/order_model');
        $data['downloads'] = $this->order_model->get_client_digital_purchases(get_client_user_id());
        $data['title'] = _l('my_downloads');
        $this->data($data);
        $this->view('clients/downloads');
        $this->layout();
    }

    public function download($order_item_id)
    {
        if (!is_client_logged_in()) {
            show_404();
        }
        $this->load->model('products/order_model');
        $client_id = get_client_user_id();
        $purchases = $this->order_model->get_client_digital_purchases($client_id);
        $found = null;
        foreach ($purchases as $p) {
            if ((int) $p['order_item_id'] === (int) $order_item_id) {
                $found = $p;
                break;
            }
        }
        if (!$found || empty($found['digital_file_path'])) {
            show_404();
        }
        $path = get_upload_path_by_type('products') . $found['digital_file_path'];
        if (!file_exists($path) || !is_readable($path)) {
            show_404();
        }
        $this->load->helper('download');
        force_download(basename($found['digital_file_path']), file_get_contents($path));
    }

    public function thankyou()
    {
        $order_id = $this->input->get('order_id');
        $total = $this->input->get('total');
        $redirect = $this->input->get('redirect');
        if (empty($redirect)) {
            $redirect = site_url('clients/invoices');
        }
        $data['order_id'] = $order_id;
        $data['total'] = $total;
        $data['redirect'] = $redirect;
        $data['remarketing'] = [
            'facebook' => get_option('product_remarketing_facebook_enabled') == '1' ? get_option('product_remarketing_facebook_pixel_id') : '',
            'google_id' => get_option('product_remarketing_google_enabled') == '1' ? get_option('product_remarketing_google_id') : '',
            'google_label' => get_option('product_remarketing_google_enabled') == '1' ? get_option('product_remarketing_google_label') : '',
        ];
        $data['upsell_products'] = [];
        $data['base_currency'] = products_active_currency();
        if (get_option('product_upsell_enabled') == '1' && $order_id && $this->db->table_exists(db_prefix() . 'product_upsell_rules')) {
            $items = $this->db->where('order_id', $order_id)->get(db_prefix() . 'order_items')->result_array();
            $bought_ids = array_unique(array_column($items, 'product_id'));
            if (!empty($bought_ids)) {
                $rules = $this->db->where('active', 1)->order_by('sort_order')->get(db_prefix() . 'product_upsell_rules')->result_array();
                foreach ($rules as $r) {
                    $trigger = !empty($r['trigger_product_ids']) ? array_map('intval', explode(',', $r['trigger_product_ids'])) : [];
                    if (empty($trigger) || array_intersect($bought_ids, $trigger)) {
                        $upsell_ids = array_map('intval', array_filter(explode(',', $r['upsell_product_ids'])));
                        if (!empty($upsell_ids)) {
                            $data['upsell_products'] = $this->products_model->get_by_id_product($upsell_ids);
                            break;
                        }
                    }
                }
            }
        }
        $this->data($data);
        $this->view('clients/thankyou');
        $this->layout();
    }

    public function remove_coupon()
    {
        if (0 != get_option('coupons_disabled')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }
        
        $this->load->model('products/products_model');
        
        $base_currency = products_active_currency();

        $total = 0;
        $tax_shipping_data = $this->get_tax_shipping();
        foreach ($tax_shipping_data['product'] as $value) {
            $total += $value->quantity * $value->rate;
        }
        foreach ($tax_shipping_data['all_taxes'] as $tax) {
            $total += array_sum($tax_shipping_data['init_tax'][$tax['name']]);
        }
        if (!empty($tax_shipping_data['shipping_cost'])) {
            $total += $tax_shipping_data['shipping_cost'];
        }
        $res = [
            'status' => true,
            'total' => app_format_money($total, $base_currency->name)
        ];
        echo json_encode($res);
    }
}