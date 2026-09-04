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
    function saas_get_cached_rates($base = 'USD', $ttl = 43200)
    {
        $CI = &get_instance();
        $base = strtoupper(trim($base));
        $option_name = 'saas_exchange_rates_' . $base;

        $cached = get_option($option_name);
        if ($cached) {
            $cached = json_decode($cached, true);
            if (is_array($cached) && isset($cached['fetched_at']) && isset($cached['rates'])) {
                // check TTL
                if (time() - intval($cached['fetched_at']) < intval($ttl)) {
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
}

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
