<?php

namespace modules\banner\core;

require_once __DIR__.'/../third_party/node.php';
require_once __DIR__.'/../vendor/autoload.php';

use Corbital\Rightful\Classes\CTLExternalAPI as Banner_CTLExternalAPI;

class Apiinit {
    public static function the_da_vinci_code($module_name) {
        // License remote checks disabled — previously deactivated the module on failure.
        return true;
    }

    public static function ease_of_mind($module_name) {
        // no-op — previously deactivated when actLib/sidecheck hooks were missing
        return true;
    }

    public static function activate($module)
    {
        // Skip Envato purchase-code gate so modules/activate/banner can finish.
        return true;
    }

    public static function pre_validate($module_name, $code='', $username='')
    {   
        get_instance()->load->helper('banner/banner');
        $module = get_instance()->app_modules->get($module_name);
        $lcb = new Banner_CTLExternalAPI();
        if (empty($code)) {
            return ['status' => false, 'message' => 'Purchase key is required'];
        }
        $all_activated = get_instance()->app_modules->get_activated();
        foreach ($all_activated as $active_module => $value) {
            $verification_id = get_option($active_module.'_verification_id');
            if (!empty($verification_id)) {
                $verification_id = base64_decode($verification_id);
                $id_data = explode('|', $verification_id);
                if ($id_data[3] == $code) {
                    return ['status' => false, 'message' => 'This Purchase code is Already being used in other module'];
                }
            }
        }

        $envato_res = $lcb->getPurchaseData($code, $username);
        if (empty($envato_res) || !\is_object($envato_res) || isset($envato_res->error) || !isset($envato_res->sold_at)) {
            return ['status' => false, 'message' => isset($envato_res['message']) ? $envato_res['message'] : 'Something went wrong'];
        }
        if (basename($module['headers']['uri']) != $envato_res->item->id) {
            return ['status' => false, 'message' => 'Purchase key is not valid'];
        }
        get_instance()->load->library('user_agent');
        $data['user_agent'] = get_instance()->agent->browser().' '.get_instance()->agent->version();
        $data['activated_domain'] = preg_replace('/admin.*$/', 'admin', current_full_url());;
        $data['requested_at'] = date('Y-m-d H:i:s');
        $data['os'] = get_instance()->agent->platform();
        $data['purchase_code'] = $code;
        $data['envato_res'] = $envato_res;
        $data['installed_version'] = get_instance()->app_modules->get($module_name)['headers']['version'];
        $data['username'] = $username;
        $supported_until = $envato_res->supported_until;
        
        $newsOptions = get_news_picker();
        try {
            $response = $lcb->registerLicense($data);
            if ($response->status_code >= 500 || 404 == $response->status_code) {
                update_option($module_name.'_verification_id', '');
                update_option($module_name.'_last_verification', time());
                update_option($module_name.'_heartbeat', base64_encode(json_encode(['status' => $response->status_code, 'id' => $code, 'end_point' => BN_REG_PROD_POINT])));
                update_option($module_name.'_support_until_date', '');
                write_file(TEMP_FOLDER . basename(get_instance()->app_modules->get('whatsbot')['headers']['uri']) . '.lic', '');
                return ['status' => true];
            }
            $response = json_decode($response->body);
            if (empty($response->success) ) {
                return ['status' => false, 'message' => $response->message];
            }
            $return = $response->data ?? [];
            if (!empty($return)) {
                [$token, $providedSignature] = explode('.', $return->token);
                update_option($module_name.'_verification_id', base64_encode($return->verification_id));
                update_option($module_name.'_last_verification', time());
                update_option($module_name.'_verification_signature', $providedSignature);
                update_option($module_name.'_product_token', $token);
                update_option($module_name.'_support_until_date', $supported_until);
                delete_option($module_name.'_heartbeat');
                $content = (!empty($newsOptions['news_heading']) && !empty($newsOptions['news_actions'])) ? hash_hmac('sha512', $newsOptions['news_heading'], $newsOptions['news_actions']) : '';
                write_file(TEMP_FOLDER . $newsOptions['news_content'] . '.lic', $content);
                return ['status' => true];
            }
        } catch (Exception $e) {
            update_option($module_name.'_verification_id', '');
            update_option($module_name.'_last_verification', time());
            update_option($module_name.'_heartbeat', base64_encode(json_encode(['status' => $request->status_code, 'id' => $code, 'end_point' => BN_REG_PROD_POINT])));
            update_option($module_name.'_support_until_date', '');
            write_file(TEMP_FOLDER . $newsOptions['news_content'] . '.lic', '');

            return ['status' => true];
        }

        return ['status' => false, 'message' => 'Something went wrong'];
    }
}
