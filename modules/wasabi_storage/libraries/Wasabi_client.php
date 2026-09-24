<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lightweight S3-compatible client for Wasabi (SigV4).
 * Uses aws/aws-sdk-php when vendor is present; otherwise falls back to cURL + Signature V4.
 */
class Wasabi_client
{
    /** @var string */
    private $accessKey;
    /** @var string */
    private $secretKey;
    /** @var string */
    private $bucket;
    /** @var string */
    private $region;
    /** @var string */
    private $endpoint;
    /** @var string|null */
    private $lastError;

    public function __construct($config = [])
    {
        // MX Loader passes null when no config is given; array access on null is a PHP 8+ TypeError/warning → HTTP 500.
        if (!is_array($config)) {
            $config = [];
        }
        $this->accessKey = (string) ($config['access_key'] ?? get_option('wasabi_access_key'));
        $this->secretKey = (string) ($config['secret_key'] ?? get_option('wasabi_secret_key'));
        $this->bucket    = (string) ($config['bucket'] ?? get_option('wasabi_bucket'));
        $this->region    = (string) ($config['region'] ?? get_option('wasabi_region') ?: 'eu-central-1');
        $endpoint        = (string) ($config['endpoint'] ?? get_option('wasabi_endpoint') ?: 'https://s3.eu-central-1.wasabisys.com');
        $this->endpoint  = rtrim($endpoint, '/');
    }

    public function get_last_error()
    {
        return $this->lastError;
    }

    public function is_configured()
    {
        return $this->accessKey !== '' && $this->secretKey !== '' && $this->bucket !== '' && $this->endpoint !== '';
    }

    public function test_connection()
    {
        // 1) Bucket reachable
        if (!$this->head_bucket()) {
            if ($this->lastError) {
                update_option('wasabi_last_error', 'HEAD bucket failed: ' . $this->lastError);
            }

            return false;
        }

        // 2) Real PUT probe — HEAD can succeed while PutObject is denied / signature broken.
        $prefix = function_exists('wasabi_storage_prefix') ? wasabi_storage_prefix() : '';
        $key = $prefix . '_wasabi_probe/' . date('YmdHis') . '.txt';
        $body = 'wasabi-probe ' . date('c');
        if (!$this->put_object($key, $body, 'text/plain')) {
            $err = 'PUT probe failed (credentials may lack s3:PutObject, or signature mismatch): ' . $this->lastError;
            update_option('wasabi_last_error', $err);

            return false;
        }

        // 3) Cleanup probe object (ignore delete failure)
        $this->delete_object($key);
        update_option('wasabi_last_error', '');

        return true;
    }

    public function head_bucket()
    {
        return $this->request('HEAD', '/' . rawurlencode($this->bucket), '', []);
    }

    public function put_object($key, $bodyOrPath, $contentType = 'application/octet-stream')
    {
        $key = ltrim((string) $key, '/');
        $body = '';
        if (is_string($bodyOrPath) && is_file($bodyOrPath)) {
            $body = file_get_contents($bodyOrPath);
            if ($body === false) {
                $this->lastError = 'Unable to read file for upload: ' . $bodyOrPath;
                update_option('wasabi_last_error', $this->lastError);

                return false;
            }
        } else {
            $body = (string) $bodyOrPath;
        }

        // Let cURL send Content-Length from POSTFIELDS; do not include it in SigV4.
        $headers = [
            'Content-Type' => $contentType ?: 'application/octet-stream',
        ];

        $ok = $this->request('PUT', '/' . rawurlencode($this->bucket) . '/' . $this->encode_key($key), $body, $headers);
        if (!$ok && $this->lastError) {
            update_option('wasabi_last_error', $this->lastError);
        }

        return $ok;
    }

    public function delete_object($key)
    {
        $key = ltrim((string) $key, '/');

        return $this->request('DELETE', '/' . rawurlencode($this->bucket) . '/' . $this->encode_key($key), '', []);
    }

    public function get_object($key)
    {
        $key = ltrim((string) $key, '/');
        $result = $this->request('GET', '/' . rawurlencode($this->bucket) . '/' . $this->encode_key($key), '', [], true);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function signed_url($key, $ttl = null)
    {
        $key = ltrim((string) $key, '/');
        $ttl = (int) ($ttl ?: get_option('wasabi_signed_url_ttl') ?: 3600);
        $ttl = max(60, min($ttl, 604800));

        $host = parse_url($this->endpoint, PHP_URL_HOST);
        if (!$host) {
            return '';
        }
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $credential = $this->accessKey . '/' . $credentialScope;
        $expires = $ttl;

        $canonicalUri = '/' . rawurlencode($this->bucket) . '/' . $this->encode_key($key);
        $query = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $credential,
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = [];
        foreach ($query as $k => $v) {
            $canonicalQuery[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQueryString = implode('&', $canonicalQuery);

        $canonicalHeaders = 'host:' . strtolower($host) . "\n";
        $signedHeaders = 'host';
        $payloadHash = 'UNSIGNED-PAYLOAD';
        $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signing_key($dateStamp));

        return $this->endpoint . $canonicalUri . '?' . $canonicalQueryString . '&X-Amz-Signature=' . $signature;
    }

    /**
     * @param bool $returnBody
     * @return bool|string
     */
    private function request($method, $uriPath, $body, array $headers, $returnBody = false)
    {
        $this->lastError = null;
        if (!$this->is_configured()) {
            $this->lastError = 'Wasabi credentials are incomplete';

            return false;
        }

        if (class_exists('\Aws\S3\S3Client')) {
            return $this->request_sdk($method, $uriPath, $body, $headers, $returnBody);
        }

        return $this->request_curl($method, $uriPath, $body, $headers, $returnBody);
    }

    private function request_sdk($method, $uriPath, $body, array $headers, $returnBody)
    {
        try {
            $client = new \Aws\S3\S3Client([
                'version'                 => 'latest',
                'region'                  => $this->region,
                'endpoint'                => $this->endpoint,
                'use_path_style_endpoint' => true,
                'credentials'             => [
                    'key'    => $this->accessKey,
                    'secret' => $this->secretKey,
                ],
            ]);

            $key = $this->key_from_uri($uriPath);

            if ($method === 'HEAD' && $key === '') {
                $client->headBucket(['Bucket' => $this->bucket]);

                return true;
            }
            if ($method === 'PUT') {
                $client->putObject([
                    'Bucket'      => $this->bucket,
                    'Key'         => $key,
                    'Body'        => $body,
                    'ContentType' => $headers['Content-Type'] ?? 'application/octet-stream',
                ]);

                return true;
            }
            if ($method === 'DELETE') {
                $client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);

                return true;
            }
            if ($method === 'GET') {
                $result = $client->getObject(['Bucket' => $this->bucket, 'Key' => $key]);
                $data = (string) $result['Body'];

                return $returnBody ? $data : true;
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        $this->lastError = 'Unsupported method: ' . $method;

        return false;
    }

    private function request_curl($method, $uriPath, $body, array $headers, $returnBody)
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        if (!$host) {
            $this->lastError = 'Invalid Wasabi endpoint URL';

            return false;
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $body = ($body === null) ? '' : $body;
        $payloadHash = hash('sha256', $body);

        // Only sign headers we also send explicitly. Do NOT sign content-length:
        // cURL sets it from POSTFIELDS and signing it often breaks Wasabi PUTs.
        $headerMap = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);
            if ($lower === 'content-length') {
                continue;
            }
            $headerMap[$lower] = trim(preg_replace('/\s+/', ' ', (string) $value));
        }
        $headerMap['host'] = strtolower($host);
        $headerMap['x-amz-content-sha256'] = $payloadHash;
        $headerMap['x-amz-date'] = $amzDate;
        if ($method === 'PUT' && empty($headerMap['content-type'])) {
            $headerMap['content-type'] = 'application/octet-stream';
        }

        ksort($headerMap);

        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headerMap as $lower => $value) {
            $canonicalHeaders .= $lower . ':' . $value . "\n";
            $signedHeadersList[] = $lower;
        }
        $signedHeaders = implode(';', $signedHeadersList);

        $canonicalRequest = $method . "\n" . $uriPath . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signing_key($dateStamp));
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $curlHeaders = ['Authorization: ' . $authorization];
        foreach ($headerMap as $lower => $value) {
            if ($lower === 'host') {
                continue;
            }
            $display = $lower;
            if ($lower === 'content-type') {
                $display = 'Content-Type';
            } elseif ($lower === 'x-amz-content-sha256' || $lower === 'x-amz-date') {
                $display = $lower; // AWS expects these lowercase commonly; either works
            }
            $curlHeaders[] = $display . ': ' . $value;
        }
        if ($method === 'PUT' || $method === 'POST') {
            $curlHeaders[] = 'Content-Length: ' . strlen($body);
        }

        $url = $this->endpoint . $uriPath;
        $ch = curl_init($url);
        if ($ch === false) {
            $this->lastError = 'Unable to initialize cURL';

            return false;
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        }
        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        // Do not attach a body to DELETE/HEAD — that can break SigV4.

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false && $method !== 'HEAD') {
            $this->lastError = $error ?: ('cURL request failed for ' . $method . ' ' . $url);

            return false;
        }
        // HEAD may return empty body with falsey response string while status is OK
        if ($method === 'HEAD' && $status >= 200 && $status < 300) {
            return true;
        }
        if ($status < 200 || $status >= 300) {
            $snippet = is_string($response) ? substr(strip_tags($response), 0, 400) : '';
            $this->lastError = $method . ' ' . $url . ' → HTTP ' . $status
                . ($snippet !== '' ? ': ' . $snippet : ($error ? ': ' . $error : ''));

            return false;
        }

        return $returnBody ? $response : true;
    }

    private function signing_key($dateStamp)
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function encode_key($key)
    {
        $parts = explode('/', $key);
        $parts = array_map('rawurlencode', $parts);

        return implode('/', $parts);
    }

    private function key_from_uri($uriPath)
    {
        $prefix = '/' . rawurlencode($this->bucket) . '/';
        if (strpos($uriPath, $prefix) === 0) {
            return rawurldecode(substr($uriPath, strlen($prefix)));
        }

        return '';
    }
}
