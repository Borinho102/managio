<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lightweight currency conversion helper with simple caching.
 * Caches exchange rates in the options table using get_option/update_option.
 * Uses exchangerate.host free API as fallback provider.
 *
 * Functions:
 * - saas_get_cached_rates($base = 'USD', $ttl = 43200)
 * - saas_convert_amount($amount, $from, $to, $precision = 2, $ttl = 43200)
 */

if (!function_exists('saas_get_cached_rates')) {
    function saas_get_cached_rates($base = 'USD', $ttl = null)
    {
        $CI = &get_instance();
        $base = strtoupper(trim($base));
        $option_name = 'saas_exchange_rates_' . $base;

        // Normalize TTL: if not provided, read option (interpreted as hours), default 12 hours
        if ($ttl === null) {
            $opt = get_option('saas_exchange_rate_ttl');
            $hours = ($opt !== null && $opt !== '') ? intval($opt) : 12; // hours
            $ttl = max(0, intval($hours) * 3600);
        }

        $cached = get_option($option_name);
        if ($cached) {
            $cached = json_decode($cached, true);
            if (is_array($cached) && isset($cached['fetched_at']) && isset($cached['rates'])) {
                // check TTL (if TTL==0 treat as always expired -> still return cached on fetch failure later)
                if ($ttl > 0 && (time() - intval($cached['fetched_at']) < intval($ttl))) {
                    return $cached['rates'];
                }
            }
        }

        // fetch from exchangerate.host
        $url = "https://api.exchangerate.host/latest?base={$base}";
        $rates = null;
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $resp = @file_get_contents($url, false, $ctx);
            if ($resp !== false) {
                $json = json_decode($resp, true);
                if (isset($json['rates']) && is_array($json['rates'])) {
                    $rates = $json['rates'];
                }
            }
        } catch (Throwable $e) {
            // ignore and fallback
            $rates = null;
        }

        // If we couldn't fetch, but have old cached value (even expired), return it to avoid failing
        if ($rates === null && $cached && isset($cached['rates'])) {
            return $cached['rates'];
        }

        if ($rates !== null) {
            $store = [
                'fetched_at' => time(),
                'rates' => $rates,
            ];
            // store as JSON string
            update_option($option_name, json_encode($store));
            return $rates;
        }

        return [];
    }

    /**
     * Clear cached exchange rate options for known currencies (and USD).
     */
    function saas_clear_cached_rates()
    {
        $CI = &get_instance();
        // Try to load currencies model; fall back to a small known set
        $bases = ['USD'];
        if (method_exists($CI, 'load')) {
            try {
                $CI->load->model('currencies_model');
                $cur = $CI->currencies_model->get();
                if (is_array($cur)) {
                    foreach ($cur as $c) {
                        $code = is_array($c) ? ($c['name'] ?? null) : ($c->name ?? null);
                        if ($code) $bases[] = strtoupper(trim($code));
                    }
                }
            } catch (Throwable $e) {
                // ignore and continue with USD only
            }
        }
        // Deduplicate
        $bases = array_unique(array_map('strtoupper', $bases));
        foreach ($bases as $b) {
            $opt = 'saas_exchange_rates_' . $b;
            update_option($opt, null);
        }
        return true;
    }}

if (!function_exists('saas_convert_amount')) {
    /**
     * Convert amount from one currency to another using cached rates.
     * Returns float rounded to $precision.
     */
    function saas_convert_amount($amount, $from, $to, $precision = 2, $ttl = 43200)
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        if ($from === $to) {
            return round((float)$amount, $precision);
        }

        // Prefer getting rates with base = $from so we can directly multiply
        $rates = saas_get_cached_rates($from, $ttl);
        if (!empty($rates) && isset($rates[$to])) {
            $rate = floatval($rates[$to]);
            return round(floatval($amount) * $rate, $precision);
        }

        // If we couldn't get direct rate, try base = USD and compute via USD
        $base = 'USD';
        $ratesBase = saas_get_cached_rates($base, $ttl);
        if (!empty($ratesBase) && isset($ratesBase[$from]) && isset($ratesBase[$to])) {
            // amount_in_usd = amount / rate_from
            $rate_from = floatval($ratesBase[$from]);
            $rate_to = floatval($ratesBase[$to]);
            if ($rate_from > 0) {
                $amount_in_usd = floatval($amount) / $rate_from;
                $converted = $amount_in_usd * $rate_to;
                return round($converted, $precision);
            }
        }

        // As last resort, return original amount rounded
        return round(floatval($amount), $precision);
    }
}
