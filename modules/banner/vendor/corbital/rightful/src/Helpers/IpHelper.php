<?php

namespace Corbital\Rightful\Helpers;

class IpHelper
{
    public static function getVisitorIP(): string
    {
        // Check for valid IP in headers
        $ipaddress = self::getIpFromHeaders();

        if ($ipaddress) {
            return $ipaddress;
        }

        // Fallback to third-party service if no valid IP found
        return self::getIpFromThirdParty() ?? 'UNKNOWN';
    }

    public static function getIpFromThirdParty(): ?string
    {
        // Using ipify API as a fallback for public IP address
        try {
            $response = file_get_contents('https://api.ipify.org?format=text');

            return $response ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function getIpFromHeaders(): ?string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // Get the first IP from the list of IPs in the header (if there are multiple)
                $ipList = explode(',', $_SERVER[$header]);

                foreach ($ipList as $ip) {
                    $ip = trim($ip);

                    // Return the first valid IP address found
                    if (filter_var($ip, \FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return null;
    }
}
