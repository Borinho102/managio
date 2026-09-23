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
        return $this->head_bucket();
    }

    public function head_bucket()
    {
        return $this->request('HEAD', '/' . rawurlencode($this->bucket), '', []);
    }

    public function put_object($key, $bodyOrPath, $contentType = 'application/octet-stream')
    {
        $key = ltrim($key, '/');
        if (is_string($bodyOrPath) && is_file($bodyOrPath)) {
            $body = file_get_contents($bodyOrPath);
            if ($body === false) {
                $this->lastError = 'Unable to read file for upload';

                return false;
            }
        } else {
            $body = (string) $bodyOrPath;
        }

        // Do not send x-amz-acl: many Wasabi buckets enforce "Bucket owner enforced" and reject ACL headers.
        $headers = [
            'Content-Type' => $contentType ?: 'application/octet-stream',
        ];

        return $this->request('PUT', '/' . rawurlencode($this->bucket) . '/' . $this->encode_key($key), $body, $headers);
    }

    public function delete_object($key)
    {
        $key = ltrim($key, '/');

        return $this->request('DELETE', '/' . rawurlencode($this->bucket) . '/' . $this->encode_key($key), '', []);
    }

    public function get_object($key)
    {
        $key = ltrim($key, '/');
        $result = $this->request('GET', '/' . rawurlencode($this->bucket) . '/' . $this->encode_key($key), '', [], true);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function signed_url($key, $ttl = null)
    {
        $key = ltrim($key, '/');
        $ttl = (int) ($ttl ?: get_option('wasabi_signed_url_ttl') ?: 3600);
        $ttl = max(60, min($ttl, 604800));

        $host = parse_url($this->endpoint, PHP_URL_HOST);
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

        $canonicalHeaders = 'host:' . $host . "\n";
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

        $this->lastError = 'Unsupported method';

        return false;
    }

    private function request_curl($method, $uriPath, $body, array $headers, $returnBody)
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);
        $headers['Host'] = $host;
        $headers['x-amz-content-sha256'] = $payloadHash;
        $headers['x-amz-date'] = $amzDate;
        if ($method === 'PUT' && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/octet-stream';
        }

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            $canonicalHeaders .= $lower . ':' . trim(preg_replace('/\s+/', ' ', $value)) . "\n";
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
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'host') {
                continue;
            }
            $curlHeaders[] = $name . ': ' . $value;
        }

        $url = $this->endpoint . $uriPath;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_HEADER, false);
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false && $method !== 'HEAD') {
            $this->lastError = $error ?: 'cURL request failed';

            return false;
        }
        if ($status < 200 || $status >= 300) {
            $this->lastError = 'HTTP ' . $status . ($response ? ': ' . substr(strip_tags($response), 0, 300) : '');

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
