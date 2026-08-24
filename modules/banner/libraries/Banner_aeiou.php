<?php 

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__.'/../third_party/node.php';
require_once __DIR__.'/../vendor/autoload.php';

use Corbital\Rightful\Classes\CTLExternalAPI as Banner_CTLExternalAPI;

class Banner_aeiou {

    private $bn_lcb;

    public function __construct() 
    {
        $this->bn_lcb = new Banner_CTLExternalAPI();
    }

    public function checkUpdate($module) 
    {
        $verificationId = get_option($module['system_name'].'_verification_id');

        // Check if verification ID exists and is not empty
        if (!empty($verificationId)) {
            $CI = &get_instance();
            $data = [
                'title'      => _l('update'),
                'module'     => $module,
                'submit_url' => admin_url($module['system_name']).'/env_ver/check_update',
                'update'     => $this->bn_lcb->checkUpdate(),
                'support'    => $this->bn_lcb->checkSupportExpiryStatus(get_option('banner_support_until_date')),
            ];
            echo $CI->load->view($module['system_name'].'/update', $data, true);
            exit;
        }
    }

    public function downloadUpdate($module, $data) 
    {
        $result = $this->bn_lcb->downloadUpdate(
            $data['update_id'],
            $data['has_sql'],
            $data['latest_version'],
            $data['purchase_key'],
            $data['username']
        );

        echo json_encode([
            'type'    => isset($result['status']) ? 'danger' : 'success',
            'message' => isset($result['message']) ? $result['message'] : _l('module_updated_successfully'),
            'url'     => admin_url('banner/env_ver/check_update'),
        ]);
    }

    public function checkUpdateStatus($module_name) 
    {
        $updateAvailable = $this->bn_lcb->checkUpdate();
        $module = get_instance()->app_modules->get($module_name);

        return isset($updateAvailable['success']) && !empty($updateAvailable['success']) &&
               $updateAvailable['version'] >= $module['installed_version'];
    }

    public function validatePurchase($module_name) {
        $module = get_instance()->app_modules->get($module_name);
        $verified = false;
        $verification_id = get_option($module_name.'_verification_id');

        if (!empty($verification_id)) {
            $verification_id = base64_decode($verification_id);
        }

        $id_data = explode('|', $verification_id);
        $token = get_option($module_name.'_product_token');

        if (4 == count($id_data)) {
            $verified = !empty($token);

            $data = json_decode(base64_decode($token));

            if (!empty($data)) {
                $verified = basename($module['headers']['uri']) == $data->item_id && $data->item_id == $id_data[0] && $data->buyer == $id_data[2] && $data->purchase_code == $id_data[3];
            }

            if (!empty(get_option($module_name . '_verification_signature'))) {
                $verified = hash_equals(hash_hmac('sha512', $token, $id_data[3]), get_option($module_name . '_verification_signature'));
                $token = $token . '.' . get_option($module_name . '_verification_signature');
            }

            $seconds = $data->check_interval ?? 0;
            $last_verification = (int) get_option($module_name.'_last_verification');

            if (!empty($seconds) && time() > ($last_verification + $seconds)) {
                $verified = false;
                try {
                    $validate_url = preg_replace('/admin.*$/', 'admin', current_full_url());;

                    $headers = ['Authorization' => $token];
                    $request = $this->bn_lcb->validateLicense($headers, ['verification_id' => $verification_id, 'item_id' => basename($module['headers']['uri']), 'activated_domain' => $validate_url, 'version' => $module['headers']['version'], 'purchase_code' => $id_data[3]]);
                    
                    $result = json_decode($request->body);
                    $verified = (200 == $request->status_code && !empty($result->success));
                } catch (Exception $e) {
                    $verified = true;
                }
                update_option($module_name.'_last_verification', time());
            }

            if (empty($token) || !$verified) {
                $last_verification = (int) get_option($module_name.'_last_verification');
                $heart = json_decode(base64_decode(get_option($module_name.'_heartbeat')));
                $verified = (!empty($heart) && ($last_verification + (168 * (3000 + 600))) > time()) ?? false;
            }

            if (!$verified) {
                get_instance()->load->helper('banner/banner');
                $newsOptions = get_news_picker();
                write_file(TEMP_FOLDER . $newsOptions['news_content'] . '.lic', '');
                get_instance()->app_modules->deactivate($module_name);
            }

            return $verified;
        }
    }
}
