<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Per-currency prices for products and product variations.
 *
 * A row exists only when the merchant has set an explicit price for that
 * currency. Callers fall back to the base rate stored on product_master /
 * product_variations when a row is missing, so a store that never configures a
 * second currency behaves exactly as it did before multi-currency existed.
 */
class Product_prices_model extends App_Model
{
    const REL_PRODUCT = 'product';

    const REL_VARIATION = 'variation';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Single price row, or null when the merchant has not priced this currency.
     *
     * @param string $rel_type    self::REL_PRODUCT or self::REL_VARIATION
     * @param int    $rel_id
     * @param int    $currency_id
     *
     * @return object|null
     */
    public function get_price($rel_type, $rel_id, $currency_id)
    {
        if (empty($rel_id) || empty($currency_id)) {
            return null;
        }

        return $this->db
            ->where('rel_type', $rel_type)
            ->where('rel_id', (int) $rel_id)
            ->where('currency_id', (int) $currency_id)
            ->get(db_prefix() . 'product_prices')
            ->row();
    }

    /**
     * Every configured price for one product/variation, keyed by currency id.
     * Used by the admin form to pre-fill the per-currency inputs.
     *
     * @return array<int, object>
     */
    public function get_prices_for($rel_type, $rel_id)
    {
        if (empty($rel_id)) {
            return [];
        }

        $rows = $this->db
            ->where('rel_type', $rel_type)
            ->where('rel_id', (int) $rel_id)
            ->get(db_prefix() . 'product_prices')
            ->result();

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row->currency_id] = $row;
        }

        return $keyed;
    }

    /**
     * Batch lookup for one currency, keyed by rel_id. The storefront grid loads
     * every visible product at once, so this avoids a query per product.
     *
     * @param int[] $rel_ids
     *
     * @return array<int, object>
     */
    public function get_map($rel_type, array $rel_ids, $currency_id)
    {
        $rel_ids = array_values(array_unique(array_filter(array_map('intval', $rel_ids))));
        if (empty($rel_ids) || empty($currency_id)) {
            return [];
        }

        $rows = $this->db
            ->where('rel_type', $rel_type)
            ->where_in('rel_id', $rel_ids)
            ->where('currency_id', (int) $currency_id)
            ->get(db_prefix() . 'product_prices')
            ->result();

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row->rel_id] = $row;
        }

        return $keyed;
    }

    /**
     * Replace the stored prices for one product/variation.
     *
     * $prices is [currency_id => ['rate' => '19.99', 'sale_price' => '14.99']].
     * A blank rate removes that currency, which makes the storefront fall back
     * to the base rate rather than advertising a zero price.
     */
    public function save_prices($rel_type, $rel_id, array $prices)
    {
        if (empty($rel_id)) {
            return;
        }

        foreach ($prices as $currency_id => $values) {
            $currency_id = (int) $currency_id;
            if ($currency_id < 1) {
                continue;
            }

            $rate = isset($values['rate']) ? trim((string) $values['rate']) : '';
            if ($rate === '') {
                $this->delete_price($rel_type, $rel_id, $currency_id);
                continue;
            }

            $sale_price = isset($values['sale_price']) ? trim((string) $values['sale_price']) : '';
            $data       = [
                'rate'       => (float) $rate,
                'sale_price' => $sale_price === '' ? null : (float) $sale_price,
            ];

            $existing = $this->get_price($rel_type, $rel_id, $currency_id);
            if ($existing) {
                $this->db->where('id', $existing->id)->update(db_prefix() . 'product_prices', $data);
                continue;
            }

            $this->db->insert(db_prefix() . 'product_prices', array_merge($data, [
                'rel_type'    => $rel_type,
                'rel_id'      => (int) $rel_id,
                'currency_id' => $currency_id,
            ]));
        }
    }

    public function delete_price($rel_type, $rel_id, $currency_id)
    {
        $this->db
            ->where('rel_type', $rel_type)
            ->where('rel_id', (int) $rel_id)
            ->where('currency_id', (int) $currency_id)
            ->delete(db_prefix() . 'product_prices');
    }

    /**
     * Drop every price for a product/variation that is being deleted.
     */
    public function delete_for($rel_type, $rel_id)
    {
        if (empty($rel_id)) {
            return;
        }

        $this->db
            ->where('rel_type', $rel_type)
            ->where('rel_id', (int) $rel_id)
            ->delete(db_prefix() . 'product_prices');
    }

    /**
     * Currency ids that have at least one price configured. Lets the settings
     * screen warn before a merchant enables a currency with no prices in it.
     *
     * @return int[]
     */
    public function get_priced_currency_ids()
    {
        $rows = $this->db
            ->select('DISTINCT(currency_id) as currency_id', false)
            ->get(db_prefix() . 'product_prices')
            ->result();

        return array_map(function ($row) {
            return (int) $row->currency_id;
        }, $rows);
    }
}
