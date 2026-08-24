<?php

namespace Corbital\Rightful\Helpers;

use WpOrg\Requests\Exception as Requests_Exception;
use WpOrg\Requests\Requests;

class RequestHelper
{
    // Define constants at the class level
    private const KB = 1024;
    private const MB = 1048576;
    private const GB = 1073741824;

    /**
     * Make an HTTP request using the rmccue/requests library.
     *
     * @param string     $method  HTTP method ('GET', 'POST', 'PUT', 'DELETE', etc.).
     * @param string     $url     full API endpoint or URL
     * @param array|null $data    optional request data
     * @param array      $headers custom headers to attach to the request
     */
    public static function makeRequest(string $method, string $url, ?array $data = null, array $headers = [])
    {
        if (\in_array(strtoupper($method), ['POST', 'PUT', 'DELETE'], true)) {
            $data                    = json_encode($data);
            $headers['Content-Type'] = 'application/json';
        } elseif ('GET' === strtoupper($method) && $data) {
            $url = \sprintf('%s?%s', $url, http_build_query($data));
        }
        $response = Requests::request($url, $headers, $data, $method);

        return $response;
    }

    /**
     * Execute an HTTP request and verify the response status code.
     *
     * @param string     $method  HTTP method
     * @param string     $url     full API endpoint or URL
     * @param array|null $data    optional request data
     * @param array      $headers custom headers to attach to the request
     */
    public static function executeAndVerifyResponse(string $method, string $url, ?array $data = null, array $headers = [])
    {
        $response = self::makeRequest($method, $url, $data, $headers);

        return $response;
    }

    /**
     * Get the remote file size from headers.
     *
     * @param string $url     the URL to check
     * @param array  $headers custom headers to attach to the request
     *
     * @return string formatted file size or 'Unknown size'
     */
    public static function getRemoteFileSize(string $url, array $headers)
    {
        try {
            $response = self::executeAndVerifyResponse('POST', $url);

            $responseBody = json_decode($response->body);

            if (null !== $responseBody) {
                $data     = $responseBody->data;
                $filesize = (isset($data->file_size)) ? $data->file_size : 0;

                return self::formatFileSize($filesize);
            }

            return 'Unknown size';
        } catch (Requests_Exception $e) {
            error_log('Request Error: '.$e->getMessage());

            return 'Unknown size';
        }
    }

    /**
     * Format the file size in human-readable format.
     *
     * @param int $bytes file size in bytes
     *
     * @return string formatted file size
     */
    private static function formatFileSize(int $bytes)
    {
        if ($bytes < 0) {
            return 'Invalid size';
        }

        $sizeUnits = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor    = floor((\strlen($bytes) - 1) / 3);

        return round($bytes / 1024 ** $factor).' '.$sizeUnits[$factor];
    }
}
