<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Netim Registrar API Library — session-based REST auth
 * Prod:    https://rest.netim.com/1.0/
 * OTE:     http://oterest.netim.com/1.0/
 *
 * Auth flow:
 *   1. POST session/ with Basic base64(login:secret) → access_token
 *   2. All subsequent calls: Authorization: Bearer {access_token}
 *   3. DELETE session/ to close
 */
class Saas_Netim
{
    const PROD_URL    = 'https://rest.netim.com/1.0/';
    const SANDBOX_URL = 'http://oterest.netim.com/1.0/';

    private $ci;
    private $login;
    private $secret;
    private $sandbox;
    private $baseUrl;

    private $sessionToken  = null;
    private $sessionOpened = false;

    public function __construct()
    {
        $this->ci      = &get_instance();
        $this->login   = get_option('netim_api_login');
        $this->secret  = get_option('netim_api_password'); // stored as netim_api_password option
        $this->sandbox = (bool) get_option('netim_sandbox_mode');
        $this->baseUrl = $this->sandbox ? self::SANDBOX_URL : self::PROD_URL;
    }

    public function __destruct()
    {
        if ($this->sessionOpened && $this->sessionToken) {
            $this->sessionClose();
        }
    }

    public function isConfigured()
    {
        return !empty($this->login) && !empty($this->secret);
    }

    // ------------------------------------------------------------------
    // Session management
    // ------------------------------------------------------------------

    private function sessionOpen()
    {
        $url     = rtrim($this->baseUrl, '/') . '/session/';
        $authStr = base64_encode($this->login . ':' . $this->secret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->sandbox);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $authStr,
            'Content-Type: application/json',
            'Accept-Language: EN',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        log_message('debug', '[NETIM::sessionOpen] HTTP=' . $httpCode . ($curlErr ? ' CURL_ERR=' . $curlErr : ''));

        if ($curlErr) {
            return false;
        }

        $decoded = json_decode($response, true);
        if ($httpCode === 200 && !empty($decoded['access_token'])) {
            $this->sessionToken  = $decoded['access_token'];
            $this->sessionOpened = true;
            return true;
        }

        log_message('error', '[NETIM::sessionOpen] Failed HTTP=' . $httpCode . ' body=' . $response);
        return false;
    }

    private function sessionClose()
    {
        if (!$this->sessionToken) {
            return;
        }

        $url = rtrim($this->baseUrl, '/') . '/session/';
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->sandbox);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->sessionToken,
            'Content-Type: application/json',
        ]);
        curl_exec($ch);
        curl_close($ch);

        $this->sessionToken  = null;
        $this->sessionOpened = false;
    }

    // ------------------------------------------------------------------
    // Core HTTP
    // ------------------------------------------------------------------

    private function request($method, $endpoint, $data = [])
    {
        if (!$this->sessionOpened || !$this->sessionToken) {
            if (!$this->sessionOpen()) {
                return ['success' => false, 'error' => 'Could not open Netim API session. Check login/secret.'];
            }
        }

        $url    = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $method = strtoupper($method);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->sandbox);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->sessionToken,
            'Content-Type: application/json',
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        log_message('debug', '[NETIM::' . $method . '] ' . $endpoint . ' HTTP=' . $httpCode . ($curlErr ? ' CURL_ERR=' . $curlErr : ''));

        if ($curlErr) {
            return ['success' => false, 'error' => 'cURL error: ' . $curlErr];
        }

        // 401 = session expired, try reopen once
        if ($httpCode === 401) {
            $this->sessionToken  = null;
            $this->sessionOpened = false;
            if ($this->sessionOpen()) {
                return $this->request($method, $endpoint, $data);
            }
            return ['success' => false, 'error' => 'Session expired and could not reopen.', 'http_code' => 401];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded, 'http_code' => $httpCode];
        }

        $msg = $decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $httpCode);
        log_message('error', '[NETIM::' . $method . '] ' . $endpoint . ' Error: ' . $msg . ' body=' . $response);

        return ['success' => false, 'error' => $msg, 'http_code' => $httpCode, 'data' => $decoded];
    }

    // ------------------------------------------------------------------
    // Connectivity test
    // ------------------------------------------------------------------

    public function testConnection()
    {
        $result = $this->request('GET', 'hello/');
        log_message('debug', '[NETIM::testConnection] result=' . json_encode($result));
        if ($result['success']) {
            $result['message'] = is_string($result['data']) ? $result['data'] : 'Connected to Netim API.';
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // Domain availability
    // ------------------------------------------------------------------

    /**
     * Check single domain availability
     * @param string $domain  e.g. "example.com"
     * @return array ['success', 'available' (bool), 'price', 'data']
     *
     * Netim response: array of StructDomainCheckResponse
     * StructDomainCheckResponse: {name, result, reasonCode, ...}
     * result: 0 = available, 210 = premium
     */
    public function checkDomain($domain)
    {
        $domain = strtolower($domain);
        $result = $this->request('GET', 'domain/' . $domain . '/check/');
        log_message('debug', '[NETIM::checkDomain] domain=' . $domain . ' result=' . json_encode($result));

        if (!$result['success']) {
            return $result;
        }

        $available = false;
        $price     = null;
        $data      = $result['data'];

        // Response is array of StructDomainCheckResponse
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item) && isset($item['result'])) {
                    // result=0: available; result=210: premium (still available)
                    $available = in_array((int)$item['result'], [0, 210], true);
                    $price     = $item['price'] ?? null;
                    break;
                }
            }
            // Single item returned as object
            if (isset($data['result'])) {
                $available = in_array((int)$data['result'], [0, 210], true);
                $price     = $data['price'] ?? null;
            }
        }

        // Fallback: get price from TLD price list if not in check response
        if ($available && $price === null) {
            $tld   = ltrim(strstr($domain, '.'), '.');
            $priceResult = $this->getTldPrice($tld);
            if ($priceResult['success'] && !empty($priceResult['data'])) {
                $price = $priceResult['data']['pricecreate'] ?? $priceResult['data']['price_create'] ?? null;
            }
        }

        return [
            'success'   => true,
            'available' => $available,
            'price'     => $price,
            'data'      => $data,
        ];
    }

    /**
     * Bulk domain check (semicolon-separated, same TLD only)
     * @param array $domains e.g. ["example.com", "example.net"]
     */
    public function checkDomains(array $domains)
    {
        // Netim bulk: domains separated by semicolons in URL path
        $domainStr = implode(';', array_map('strtolower', $domains));
        return $this->request('GET', 'domain/' . $domainStr . '/check/');
    }

    // ------------------------------------------------------------------
    // TLD pricing
    // ------------------------------------------------------------------

    public function getTldPrice($tld)
    {
        $tld = strtoupper(ltrim($tld, '.'));
        return $this->request('GET', 'tld/' . $tld . '/');
    }

    public function getAllTlds()
    {
        return $this->request('GET', 'tlds/price-list/');
    }

    public function getDomainPrice($domain)
    {
        $domain = strtolower($domain);
        return $this->request('GET', 'domain/' . $domain . '/price/');
    }

    // ------------------------------------------------------------------
    // Contacts (WHOIS registrant info)
    // ------------------------------------------------------------------

    /**
     * Create Netim contact.
     * @param array $data [first_name, last_name, email, phone, address, city, zipcode, country, state, legal_type, company_name?]
     * @return array ['success', 'data' => string idContact]
     *
     * Netim contact fields:
     *   bodyForm: IND (individual) or ORG (organization)
     *   bodyName: company name (required if bodyForm=ORG)
     *   phone: digits only, no + or spaces (e.g. "12025551234")
     */
    public function createContact(array $data)
    {
        $bodyForm = (isset($data['legal_type']) && strtoupper($data['legal_type']) === 'COMPANY') ? 'ORG' : 'IND';
        $phone    = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');

        $contact = [
            'firstName' => $data['first_name'] ?? '',
            'lastName'  => $data['last_name']  ?? '',
            'bodyForm'  => $bodyForm,
            'bodyName'  => ($bodyForm === 'ORG') ? ($data['company_name'] ?? '') : '',
            'address1'  => $data['address']  ?? '',
            'address2'  => '',
            'zipCode'   => $data['zipcode']  ?? '',
            'area'      => $data['state']    ?? '',
            'city'      => $data['city']     ?? '',
            'country'   => strtoupper($data['country'] ?? 'US'),
            'phone'     => $phone,
            'fax'       => '',
            'email'     => $data['email']    ?? '',
            'language'  => 'EN',
            'isOwner'   => 1,
        ];

        log_message('debug', '[NETIM::createContact] email=' . ($data['email'] ?? ''));
        $result = $this->request('POST', 'contact/', ['contact' => $contact]);

        if ($result['success']) {
            // API returns idContact string directly
            $idContact = is_string($result['data']) ? $result['data'] : ($result['data']['idContact'] ?? ($result['data']['ID'] ?? null));
            $result['data'] = ['idContact' => $idContact];
        }

        return $result;
    }

    public function getContact($handle)
    {
        return $this->request('GET', 'contact/' . $handle);
    }

    public function updateContact($handle, array $data)
    {
        return $this->request('PATCH', 'contact/' . $handle, ['contact' => $data]);
    }

    // ------------------------------------------------------------------
    // Domain registration
    // ------------------------------------------------------------------

    /**
     * Register domain via Netim.
     * @param string $domain         e.g. "example.com"
     * @param string $contactHandle  Netim contact ID (used for all roles: owner/admin/tech/billing)
     * @param array  $nameservers    Optional override; falls back to options netim_nameserver_1/2
     * @param int    $duration       Years (default 1)
     *
     * Netim domainCreate params: idOwner, idAdmin, idTech, idBilling, ns1..ns5, duration
     */
    public function registerDomain($domain, $contactHandle, array $nameservers = [], $duration = 1)
    {
        $domain = strtolower($domain);

        $ns1 = '';
        $ns2 = '';
        if (!empty($nameservers)) {
            $ns1 = $nameservers[0] ?? '';
            $ns2 = $nameservers[1] ?? '';
        } else {
            $ns1 = get_option('netim_nameserver_1') ?: 'ns1.netim.com';
            $ns2 = get_option('netim_nameserver_2') ?: 'ns2.netim.com';
        }

        $params = [
            'idOwner'   => $contactHandle,
            'idAdmin'   => $contactHandle,
            'idTech'    => $contactHandle,
            'idBilling' => $contactHandle,
            'ns1'       => $ns1,
            'ns2'       => $ns2,
            'ns3'       => '',
            'ns4'       => '',
            'ns5'       => '',
            'duration'  => (int) $duration,
        ];

        log_message('debug', '[NETIM::registerDomain] domain=' . $domain . ' contact=' . $contactHandle . ' ns1=' . $ns1 . ' ns2=' . $ns2);
        $result = $this->request('POST', 'domain/' . $domain . '/', $params);

        if ($result['success'] && is_array($result['data'])) {
            // StructOperationResponse: {STATUS, ID, toDisplay, date}
            $result['data']['orderId'] = $result['data']['ID'] ?? $result['data']['id'] ?? null;
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Domain info & management
    // ------------------------------------------------------------------

    public function getDomainInfo($domain)
    {
        return $this->request('GET', 'domain/' . strtolower($domain) . '/info/');
    }

    public function listDomains($filter = '')
    {
        return $this->request('GET', 'domains/' . $filter);
    }

    public function renewDomain($domain, $duration = 1)
    {
        return $this->request('PATCH', 'domain/' . strtolower($domain) . '/renew/', ['duration' => (int) $duration]);
    }

    // ------------------------------------------------------------------
    // DNS zone management
    // ------------------------------------------------------------------

    public function getDnsZone($domain)
    {
        return $this->request('GET', 'domain/' . strtolower($domain) . '/zone/');
    }

    /**
     * Add DNS record.
     * @param string $domain  e.g. "example.com"
     * @param string $type    A, AAAA, MX, CNAME, TXT, NS, SRV
     * @param string $host    subdomain (@ for root, "www" for www)
     * @param string $value   IP or target
     * @param int    $ttl     Default 3600
     *
     * Netim API: POST /domain/{domain}/zone/
     * Payload: {subdomain, type, value, options:{service,protocol,ttl,priority,weight,port}}
     */
    public function addDnsRecord($domain, $type, $host, $value, $ttl = 3600)
    {
        $params = [
            'subdomain' => $host,
            'type'      => strtoupper($type),
            'value'     => $value,
            'options'   => [
                'service'  => '',
                'protocol' => '',
                'ttl'      => (string) $ttl,
                'priority' => '',
                'weight'   => '',
                'port'     => '',
            ],
        ];
        log_message('debug', '[NETIM::addDnsRecord] domain=' . $domain . ' ' . $type . ' ' . $host . '→' . $value);
        return $this->request('POST', 'domain/' . strtolower($domain) . '/zone/', $params);
    }

    /**
     * Delete DNS record.
     * @param string $domain
     * @param string $subdomain  subdomain/host of record
     * @param string $type       record type
     * @param string $value      record value
     */
    public function deleteDnsRecord($domain, $subdomain, $type, $value)
    {
        $params = [
            'subdomain' => $subdomain,
            'type'      => strtoupper($type),
            'value'     => $value,
        ];
        return $this->request('DELETE', 'domain/' . strtolower($domain) . '/zone/', $params);
    }

    /**
     * Auto-configure DNS: A records (@ and www) → server IP.
     */
    public function autoConfigureDns($domain)
    {
        $serverIp = get_option('netim_server_ip');
        if (empty($serverIp)) {
            $serverIp = get_option('custom_domain_ip_address');
        }

        if (empty($serverIp)) {
            log_message('error', '[NETIM::autoConfigureDns] No server IP configured (netim_server_ip / custom_domain_ip_address)');
            return false;
        }

        $results            = [];
        $results['a_root']  = $this->addDnsRecord($domain, 'A', '@',   $serverIp);
        $results['a_www']   = $this->addDnsRecord($domain, 'A', 'www', $serverIp);

        log_message('debug', '[NETIM::autoConfigureDns] domain=' . $domain . ' ip=' . $serverIp . ' results=' . json_encode($results));
        return $results;
    }
}
