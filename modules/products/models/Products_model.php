<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Products_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function add_product($data)
    {
        $variations = $data['variations'];
        unset($data['variations']);
        $currency_prices = $data['currency_prices'] ?? [];
        unset($data['currency_prices']);
        $data['slug'] = null;

        $this->db->insert(db_prefix() . 'product_master', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            $this->db->where('id', $insert_id);
            $this->db->update(db_prefix() . 'product_master', [
                'slug' => $this->generate_product_slug($data['product_name'], $insert_id)
            ]);
        }
        if ($insert_id) {
            log_activity('New Product Added [ ID:' . $insert_id . ', '. $data['product_name'].', Staff id ' . get_staff_user_id() . ' ]');

            $this->save_currency_prices('product', $insert_id, $currency_prices, $data['rate'] ?? 0, $data['sale_price'] ?? null);

            if (isset($variations['variation'])) {
                $variation_count = count($variations['variation']);
                for ($variation_index = 0; $variation_index < $variation_count; $variation_index++) {
                    $this->db->where('name', $variations['variation'][$variation_index]);
                    $variation_row = $this->db->get(db_prefix() . 'variations')->row();
                    if ($variation_row) {
                        $this->db->where('variation_id', $variation_row->id);
                        $this->db->where('value', $variations['variation_value'][$variation_index]);
                        $variation_value_row = $this->db->get(db_prefix() . 'variation_values')->row();
                        if ($variation_value_row) {
                            $product_variation_data = [
                                'product_id' => $insert_id,
                                'variation_id' => $variation_row->id,
                                'variation_value_id' => $variation_value_row->id,
                                'rate' => $variations['rate'][$variation_index],
                                'quantity_number' => $variations['quantity_number'][$variation_index],
                            ];
                            $this->db->insert(db_prefix() . 'product_variations', $product_variation_data);
                            $this->save_variation_currency_prices($this->db->insert_id(), $variations, $variation_index);
                        }
                    }
                }
            }

            return $insert_id;
        }

        return false;
    }

    /**
     * Persist the per-currency prices for a product or variation.
     *
     * The base currency row is always written from the product's own rate, so
     * product_master.rate and product_prices can never disagree about the base
     * price, whichever screen was used to edit it.
     *
     * @param string     $rel_type        'product' or 'variation'
     * @param int        $rel_id
     * @param array      $currency_prices [currency_id => ['rate' => ..., 'sale_price' => ...]]
     * @param float      $base_rate
     * @param float|null $base_sale_price
     */
    private function save_currency_prices($rel_type, $rel_id, $currency_prices, $base_rate, $base_sale_price = null)
    {
        $this->load->model('products/product_prices_model');

        $base = products_base_currency();
        if ($base) {
            $currency_prices[(int) $base->id] = [
                'rate'       => $base_rate,
                'sale_price' => $base_sale_price,
            ];
        }

        // Never let a disabled or unknown currency be priced from a posted form.
        $allowed = products_enabled_currencies();
        foreach (array_keys($currency_prices) as $currency_id) {
            if (!isset($allowed[(int) $currency_id])) {
                unset($currency_prices[$currency_id]);
            }
        }

        $this->product_prices_model->save_prices($rel_type, $rel_id, $currency_prices);
    }

    /**
     * Pull one variation row's per-currency rates out of the posted parallel
     * arrays: variations[currency_rate][<currency_id>][<row index>].
     */
    private function save_variation_currency_prices($product_variation_id, $variations, $variation_index)
    {
        if (empty($product_variation_id)) {
            return;
        }

        $currency_prices = [];
        foreach ($variations['currency_rate'] ?? [] as $currency_id => $rates) {
            if (!isset($rates[$variation_index])) {
                continue;
            }
            $currency_prices[(int) $currency_id] = ['rate' => $rates[$variation_index]];
        }

        $this->save_currency_prices(
            'variation',
            $product_variation_id,
            $currency_prices,
            $variations['rate'][$variation_index] ?? 0
        );
    }

    public function get_by_slug($slug)
    {
        if (empty($slug)) {
            return null;
        }
        $this->db->where('slug', $slug);
        $row = $this->db->get(db_prefix() . 'product_master')->row();
        return $row ? $this->get_by_id_product($row->id) : null;
    }

    public function get_by_id_product($id = false)
    {
        $this->db->select(db_prefix() . 'product_master.*, ' . db_prefix() . 'product_categories.p_category_name, ' . db_prefix() . 'product_categories.p_category_description, ' . db_prefix() . 'product_categories.parent_id');
        $this->db->join('product_categories', db_prefix() . 'product_categories.p_category_id='.db_prefix() . 'product_master.product_category_id', 'LEFT');
        if ($id) {
            $this->db->where_in('id', $id);
            if (is_array($id)) {
                $product = $this->db->get(db_prefix() . 'product_master')->result();
                foreach ($product as $product_row) {
                    if ($product_row->is_variation) {
                        $this->db->select(db_prefix() . 'product_variations.*, ' . db_prefix() . 'variations.name as variation_name, ' . db_prefix() . 'variation_values.value as variation_value');
                        $this->db->join('variations', db_prefix() . 'variations.id=' . db_prefix() . 'product_variations.variation_id', 'LEFT');
                        $this->db->join('variation_values', db_prefix() . 'variation_values.id=' . db_prefix() . 'product_variations.variation_value_id', 'LEFT');
                        $this->db->where('product_id', $product_row->id);
                        $this->db->order_by('variation_id');
                        $product_row->variations = $this->db->get(db_prefix() . 'product_variations')->result();
                    }
                }
            } else {
                $product = $this->db->get(db_prefix() . 'product_master')->row();
                if ($product->is_variation) {
                    $this->db->select(db_prefix() . 'product_variations.*, ' . db_prefix() . 'variations.name as variation_name, ' . db_prefix() . 'variation_values.value as variation_value');
                    $this->db->join('variations', db_prefix() . 'variations.id=' . db_prefix() . 'product_variations.variation_id', 'LEFT');
                    $this->db->join('variation_values', db_prefix() . 'variation_values.id=' . db_prefix() . 'product_variations.variation_value_id', 'LEFT');
                    $this->db->where('product_id', $product->id);
                    $this->db->order_by('variation_id');
                    $product->variations = $this->db->get(db_prefix() . 'product_variations')->result();
                }
            }

            return $product;
        }
        $products = $this->db->get(db_prefix() . 'product_master')->result_array();
        foreach ($products as $product_index => $product) {
            if ($product['is_variation']) {
                $this->db->select(db_prefix() . 'product_variations.*, ' . db_prefix() . 'variations.name as variation_name, ' . db_prefix() . 'variation_values.value as variation_value');
                $this->db->join('variations', db_prefix() . 'variations.id=' . db_prefix() . 'product_variations.variation_id', 'LEFT');
                $this->db->join('variation_values', db_prefix() . 'variation_values.id=' . db_prefix() . 'product_variations.variation_value_id', 'LEFT');
                $this->db->where('product_id', $product['id']);
                $this->db->order_by('variation_id');
                $products[$product_index]['variations'] = $this->db->get(db_prefix() . 'product_variations')->result();
            }
        }

        return $products;
    }

	public function get_by_cart_product($cart_data)
	{
		$products = [];
		foreach ($cart_data as $cart_item) {
			$this->db->select('*');
			$this->db->from(db_prefix() . 'product_master');
			$this->db->join('product_categories', db_prefix() . 'product_categories.p_category_id=' . db_prefix() . 'product_master.product_category_id', 'LEFT');
			$this->db->where('id', $cart_item['product_id']);
			$product = $this->db->get()->row();

			if ($product) {
				$product->quantity = $cart_item['quantity'];
				
				// Check if 'product_variation_id' key exists in $cart_item array
				if (isset($cart_item['product_variation_id'])) {
					$this->db->select(db_prefix() . 'product_variations.*, ' . db_prefix() . 'variations.name as variation_name, ' . db_prefix() . 'variation_values.value as variation_value');
					$this->db->from(db_prefix() . 'product_variations');
					$this->db->join('variations', db_prefix() . 'variations.id=' . db_prefix() . 'product_variations.variation_id', 'LEFT');
					$this->db->join('variation_values', db_prefix() . 'variation_values.id=' . db_prefix() . 'product_variations.variation_value_id', 'LEFT');
					$this->db->where(db_prefix() . 'product_variations.id', $cart_item['product_variation_id']);
					$product_variation = $this->db->get()->row();

					if ($product_variation) {
						$product->product_variation_id = $cart_item['product_variation_id'];
						$product->variation_name = $product_variation->variation_name;
						$product->variation_rate = $this->currency_rate('variation', $product_variation->id, $product_variation->rate);
						$product->variation_value = $product_variation->variation_value;
						$product->quantity_number = $product_variation->quantity_number;
					}
				}

				// Cart and checkout must price in the currency the customer is
				// shopping in, not the base currency.
				products_apply_currency_to_product($product);

				$products[] = $product;
			} else {
				// Handle case where product is not found or null
				// You might log an error or handle it based on your application's requirements
				log_message('error', 'Product with ID ' . $cart_item['product_id'] . ' not found');
			}
		}

		return $products;
	}

    public function get_by_id_product_afflect_variation($items)
    {
        $products = [];
        foreach ($items as $item) {
            $this->db->join('product_categories', db_prefix() . 'product_categories.p_category_id='.db_prefix() . 'product_master.product_category_id', 'LEFT');
            $this->db->where_in('id', $item['product_id']);
            $product = $this->db->get(db_prefix() . 'product_master')->row();
            // Resolve the product's own currency price first, so a product
            // without variations is priced correctly too.
            products_apply_currency_to_product($product);
            if ($item['product_variation_id']) {
                $this->db->select(db_prefix() . 'product_variations.*, ' . db_prefix() . 'variations.name as variation_name, ' . db_prefix() . 'variation_values.value as variation_value');
                $this->db->join('variations', db_prefix() . 'variations.id=' . db_prefix() . 'product_variations.variation_id', 'LEFT');
                $this->db->join('variation_values', db_prefix() . 'variation_values.id=' . db_prefix() . 'product_variations.variation_value_id', 'LEFT');
                $this->db->where('product_variations.id', $item['product_variation_id']);
                $product_variation = $this->db->get(db_prefix() . 'product_variations')->row();
                $product->product_name = $product->product_name . ' (' . $product_variation->variation_name . ' ' . $product_variation->variation_value . ' )';
                // A chosen variation overrides the product price, in the same currency.
                $product->rate = $this->currency_rate('variation', $product_variation->id, $product_variation->rate);
                $product->quantity_number = $product_variation->quantity_number;
            }
            $products[] = $product;
        }
        return $products;
    }

    /**
     * Price of one product/variation in the currency the customer is shopping
     * in, falling back to the stored base rate when it is not priced there.
     *
     * @param string $rel_type 'product' or 'variation'
     * @param int    $rel_id
     * @param float  $base_rate
     *
     * @return float
     */
    private function currency_rate($rel_type, $rel_id, $base_rate)
    {
        $currency = products_active_currency();
        if (!$currency) {
            return (float) $base_rate;
        }

        $resolved = products_resolve_rate($rel_type, $rel_id, $currency->id, $base_rate);

        return $resolved['rate'];
    }

    public function get_by_id_variations($id)
    {
        if ($id) {
            $this->db->where('id', $id);
            $product = $this->db->get(db_prefix() . 'product_master')->row();
            $this->db->select(db_prefix() . 'product_variations.*, ' . db_prefix() . 'variations.name as variation_name, ' . db_prefix() . 'variation_values.value as variation_value');
            $this->db->join('variations', db_prefix() . 'variations.id=' . db_prefix() . 'product_variations.variation_id', 'LEFT');
            $this->db->join('variation_values', db_prefix() . 'variation_values.id=' . db_prefix() . 'product_variations.variation_value_id', 'LEFT');
            $this->db->where('product_id', $product->id);
            $this->db->order_by('variation_id');
            $product_variations = $this->db->get(db_prefix() . 'product_variations')->result();
            return $product_variations;
        }

        return [];
    }

    public function get_by_id_variation_values($id, $variation_id = false)
    {
        if ($id) {
            $this->db->where('id', $id);
            $product = $this->db->get(db_prefix() . 'product_master')->row();
            if ($product) {
                $this->db->select(db_prefix() . 'product_variations.*, ' . db_prefix() . 'variations.name as variation_name, ' . db_prefix() . 'variation_values.value as variation_value');
                $this->db->join('variations', db_prefix() . 'variations.id=' . db_prefix() . 'product_variations.variation_id', 'LEFT');
                $this->db->join('variation_values', db_prefix() . 'variation_values.id=' . db_prefix() . 'product_variations.variation_value_id', 'LEFT');
                $this->db->where(db_prefix() . 'product_variations.product_id', $product->id);
                if ($variation_id) {
                    $this->db->where(db_prefix() . 'product_variations.variation_id', $variation_id);
                } else {
                    $this->db->order_by('variation_id');
                }
                $product_variations = $this->db->get(db_prefix() . 'product_variations')->result();
                return $product_variations;
            }
        }

        return [];
    }

    public function get_category_filter($p_category_id)
    {
        if (!empty($p_category_id) && is_array($p_category_id)) {
            $this->db->where_in(db_prefix() . 'product_master.product_category_id', $p_category_id);
        }
        $this->db->order_by(db_prefix() . 'product_master.product_category_id', 'ASC');

        return $this->get_by_id_product();
    }

    public function edit_product($data, $id)
    {
        $variations = [];
        if (isset($data['variations'])) {
            $variations = $data['variations'];
            unset($data['variations']);
        }
        $currency_prices = $data['currency_prices'] ?? [];
        unset($data['currency_prices']);

        $product = $this->get_by_id_product($id);
        if (empty($data['slug']) && !empty($data['product_name'])) {
            $data['slug'] = $this->generate_product_slug($data['product_name'], $id);
        }
        $this->db->where('id', $id);
        $res = $this->db->update(db_prefix() . 'product_master', $data);
        if ($this->db->affected_rows() > 0) {
            if (!empty($data['quantity_number']) && $product->quantity_number != $data['quantity_number']) {
                log_activity('Product Quantity updated[ ID: '.$id.', From: '.$product->quantity_number.' To: '.$data['quantity_number'].' Staff id '.get_staff_user_id().']');
            }
            log_activity('Product Details updated[ ID: '.$id.', '.$product->product_name.', Staff id '.get_staff_user_id().' ]');
        }

        // Outside the affected_rows() check: a merchant may change only a
        // foreign-currency price, which leaves product_master untouched.
        $this->save_currency_prices('product', $id, $currency_prices, $data['rate'] ?? $product->rate, $data['sale_price'] ?? ($product->sale_price ?? null));

        if (isset($variations['variation'])) {
            $variation_count = count($variations['variation']);
            for ($variation_index = 0; $variation_index < $variation_count; $variation_index++) {
                $this->db->where('name', $variations['variation'][$variation_index]);
                $variation_row = $this->db->get(db_prefix() . 'variations')->row();
                if ($variation_row) {
                    $this->db->where('variation_id', $variation_row->id);
                    $this->db->where('value', $variations['variation_value'][$variation_index]);
                    $variation_value_row = $this->db->get(db_prefix() . 'variation_values')->row();
                    if ($variation_value_row) {
                        $this->db->where('product_id', $id);
                        $this->db->where('variation_id', $variation_row->id);
                        $this->db->where('variation_value_id', $variation_value_row->id);
                        $product_variation_row = $this->db->get(db_prefix() . 'product_variations')->row();
                        if ($product_variation_row) {
                            $product_variation_data = [
                                'rate' => $variations['rate'][$variation_index],
                                'quantity_number' => $variations['quantity_number'][$variation_index],
                            ];
                            $this->db->where('id', $product_variation_row->id);
                            $this->db->update(db_prefix() . 'product_variations', $product_variation_data);
                            if ($this->db->affected_rows() > 0) {
                                log_activity('Product Variation Details Updated [ ID: ' . $product_variation_row->id . ', ' . $variation_row->name . ', ' . $variation_value_row->value . ' ]');
                            }
                            $this->save_variation_currency_prices($product_variation_row->id, $variations, $variation_index);
                        } else {
                            $product_variation_data = [
                                'product_id' => $id,
                                'variation_id' => $variation_row->id,
                                'variation_value_id' => $variation_value_row->id,
                                'rate' => $variations['rate'][$variation_index],
                                'quantity_number' => $variations['quantity_number'][$variation_index],
                            ];
                            $this->db->insert(db_prefix() . 'product_variations', $product_variation_data);
                            $insert_id = $this->db->insert_id();
                            log_activity('Product Variation Details Added [ ID: ' . $insert_id . ', ' . $variation_row->name . ', ' . $variation_value_row->value . ' ]');
                            $this->save_variation_currency_prices($insert_id, $variations, $variation_index);
                        }
                    }
                }
            }
            
            $this->db->where('product_id', $id);
            $product_variations = $this->db->get(db_prefix() . 'product_variations')->result_array();
            foreach ($product_variations as $product_variation) {
                $product_variation_exist = false;
                $this->db->where('id', $product_variation['variation_id']);
                $variation_row = $this->db->get(db_prefix() . 'variations')->row();
                $this->db->where('id', $product_variation['variation_value_id']);
                $variation_value_row = $this->db->get(db_prefix() . 'variation_values')->row();
                if ($variation_row && $variation_value_row) {
                    $variation_count = count($variations['variation']);
                    for ($variation_index = 0; $variation_index < $variation_count; $variation_index++) {
                        if ($variation_row->name == $variations['variation'][$variation_index] && $variation_value_row->value == $variations['variation_value'][$variation_index]) {
                            $product_variation_exist = true;
                            break;
                        }
                    }
                }
                if (!$product_variation_exist) {
                    $this->db->where('id', $product_variation['id']);
                    $this->db->delete(db_prefix() . 'product_variations');
                    $this->load->model('products/product_prices_model');
                    $this->product_prices_model->delete_for('variation', $product_variation['id']);
                    log_activity('Product Variation Details Deleted [ ID: ' . $product_variation['id'] . ' ]');
                }
            }
        }

        if ($res) {
            return true;
        }

        return false;
    }

    public function delete_by_id_product($id)
    {
        $product  = $this->get_by_id_product($id);
        $relPath  = function_exists('products_get_product_image_path') ? products_get_product_image_path() : (get_upload_path_by_type('products') . '/');
        $fullPath = $relPath . ($product->product_image ?? '');
        if (!empty($product->product_image) && file_exists($fullPath)) {
            @unlink($fullPath);
        }
        // Read the variation ids before deleting the product: product_variations
        // has ON DELETE CASCADE, so they are gone by the time we clean up prices.
        $this->db->where('product_id', $id);
        $product_variations = $this->db->get(db_prefix() . 'product_variations')->result_array();

        if (!empty($id)) {
            $this->db->where('id', $id);
        }
        $result = $this->db->delete(db_prefix() . 'product_master');
        log_activity('Product Deleted[ ID: '.$id.', '.$product->product_name.', Staff id '.get_staff_user_id().' ]');

        $this->load->model('products/product_prices_model');
        $this->product_prices_model->delete_for('product', $id);

        foreach ($product_variations as $product_variation) {
            $this->db->where('id', $product_variation['id']);
            $this->db->delete(db_prefix() . 'product_variations');
            $this->product_prices_model->delete_for('variation', $product_variation['id']);
            log_activity('Product Variation Details Deleted [ ID: ' . $product_variation['id'] . ' ]');
        }

        return $result;
    }

    public function generate_product_slug($name, $product_id = 0)
    {
        $base = preg_replace('/[^a-z0-9\-]/', '-', strtolower($name));
        $base = preg_replace('/-+/', '-', trim($base, '-'));
        if (empty($base)) {
            $base = 'product';
        }
        return $base . '-' . $product_id;
    }
}
