<?php

defined('BASEPATH') || exit('No direct script access allowed');
require_once __DIR__.'/../vendor/autoload.php';
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;

class Flutex_admin_api
{
    
    public function send_push_notification($notification_id)
    {
        $CI = &get_instance();

        $notification = $CI->db->where('id', $notification_id)->get(db_prefix() . 'notifications')->row();
        if (!empty($notification)) {
            $staff = $CI->db->where('staffid', $notification->touserid)->get(db_prefix() . 'staff')->row();
            if (!empty($staff)) {
                $additional_data = '';
                if (!empty($notification->additional_data)) {
                    $additional_data = unserialize($notification->additional_data);
    
                    $i = 0;
                    foreach ($additional_data as $data) {
                        if (strpos($data, '<lang>') !== false) {
                            $lang = get_string_between($data, '<lang>', '</lang>');
                            $name = _l($lang);
                            if (strpos($name, 'project_status_') !== FALSE) {
                                $status = get_project_status_by_id(strafter($name, 'project_status_'));
                                $name = $status['name'];
                            }
                            $additional_data[$i] = $name;
                        }
                        $i++;
                    }
                }
    
                $description = _l($notification->description, $additional_data);;
                $icon = 'icon.png';
                if (($notification->fromcompany == NULL && $notification->fromuserid != 0) || ($notification->fromcompany == NULL && $notification->fromclientid != 0)) {
                    if ($notification->fromuserid != 0) {
                        $icon = staff_profile_image_url($notification->fromuserid);
                    } else {
                        $icon = contact_profile_image_url($notification->fromclientid);
                    }
                }
                
                $title = $notification->from_fullname;
                $token = $staff->fcm_token;
                $type = $this->flutex_admin_api_notification_type($notification->link);
                $type_id = $this->flutex_admin_api_notification_type_id($notification->link);
                
                
                try{
                    $this->send_notification($token,$title,$description,$icon,$type,$type_id);
                } catch (\Throwable $th) {
                
                }
            }
        }
    }
    
    private function send_notification($fcm_token="", $title = "title", $description = "message", $icon = "icon.png", $type = "type", $type_id = "1")
    {
        $fcm_service_file = json_decode(get_option('flutex_admin_fcm_service_file_content'),true);
        $project_id = $fcm_service_file['project_id'];
        $url = "https://fcm.googleapis.com/v1/projects/".$project_id."/messages:send";
        $access_token = $this->getAccessToken();
        
        $options = [
            'json' => [
                "message" => [
                    "token"=> $fcm_token,
                    "data" => [
                        "title" => $title,
                        "body" => $description,
                        "image" => $icon,
                        "type" => $type,
                        "type_id" => $type_id,
                    ],
                    "notification"=> [
                        "title" => $title,
                        "body"  => $description,
                    ],
                    "webpush"=> [
                        "fcm_options" => [
                          "link"=> "https://google.com"
                    ]
                    ]
               ]
           ],
           'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ];

        $client = new GuzzleHttp\Client();
        $response = $client->post($url, $options);
    }
    
    private function getAccessToken()
    {
        $fcm_service_file = get_option('flutex_admin_fcm_service_file_content');

        $credentials = new ServiceAccountCredentials(
            "https://www.googleapis.com/auth/firebase.messaging",
            json_decode($fcm_service_file, true)
        );

        $accessToken = $credentials->fetchAuthToken(HttpHandlerFactory::build());
        return $accessToken['access_token'];
    }

    private function flutex_admin_api_notification_type($link)
    {
        $type = '';
        
        switch ($link) {
            case (strpos($link, '#taskid=') !== false):
                $type = 'task';
                break;
            case (strpos($link, '#leadid=') !== false):
                $type = 'lead';
                break;
            case (strpos($link, 'invoices/') !== false):
                $type = 'invoice';
                break;
            case (strpos($link, 'proposals/') !== false):
                $type = 'proposal';
                break;
            case (strpos($link, 'projects/') !== false):
                $type = 'project';
                break;
        }
        
        return $type;
    }

    private function flutex_admin_api_notification_type_id($link)
    {
        $type_id = '';
        
        switch ($link) {
            case (strpos($link, '#taskid=') !== false):
                $type_id = 'task';
                break;
            case (strpos($link, '#leadid=') !== false):
                $type_id = 'lead';
                break;
            case (strpos($link, 'invoices/') !== false):
                $type_id = 'invoice';
                break;
            case (strpos($link, 'proposals/') !== false):
                $type_id = 'proposal';
                break;
            case (strpos($link, 'projects/') !== false):
                $type_id = 'project';
                break;
        }
        
        return $type_id;
    }
    
    public static function pre_activate($module_name)
    {
        $module = get_instance()->app_modules->get($module_name);
        if (!option_exists('flutex_admin_api_enabled') || get_option('flutex_admin_api_enabled') == 0) {
            $CI                   = &get_instance();
            $data['submit_url']   = admin_url($module['system_name']).'/VerifyPurchase/activate';
            $data['return_url'] = admin_url('modules/activate/'.$module['system_name']);
            $data['module_name']  = $module['system_name'];
            $data['title']        = 'Flutex Admin API Module Activation';
            echo $CI->load->view($module['system_name'].'/activation', $data, true);
            exit;
        }
    }
    
    public static function activate($module_name, $purchase_code='', $username='')
    {
        if (empty($purchase_code)) {
            return ['status' => false, 'message' => 'Purchase key is required'];
        }
        
        $data =  array(
			"api_key"  => '1bf51608-763d-469e-b60d-cfc588107003',
			"license_key" => $purchase_code,
			"identifier" => base_url()
		);
		
		try {
            $response = get_instance()->flutex_admin_api->call_api(base64_decode('aHR0cHM6Ly9saWNlbnNlLmJyYW5kaXR0YS5uZXQvYXBpL3YxL2FjdGl2YXRl'), json_encode($data));
            $response = json_decode($response);
            
            if (300 == $response->response->code) {
                update_option('flutex_admin_api_enabled', '1');
                update_option($module_name.'_verification_key', base64_encode($purchase_code));
                update_option($module_name.'_last_verification', $response->response->timestamp);
                delete_option($module_name.'_log', $response->response_base64);

                return ['status' => true, 'message' => $response->response->message];
            }else if(301 == $response->response->code){
                try {
                    $verify = get_instance()->flutex_admin_api->call_api(base64_decode('aHR0cHM6Ly9saWNlbnNlLmJyYW5kaXR0YS5uZXQvYXBpL3YxL3ZlcmlmeQ=='), json_encode($data));
                    $verify = json_decode($verify);
                    if (200 == $verify->response->code) {
                        update_option('flutex_admin_api_enabled', '1');
                        update_option($module_name.'_verification_key', base64_encode($purchase_code));
                        update_option($module_name.'_last_verification', $verify->response->timestamp);
                        delete_option($module_name.'_log', $verify->response_base64);
        
                        return ['status' => true, 'message' => $verify->response->message];
                    }else{
                        update_option('flutex_admin_api_enabled', '0');
                        update_option($module_name.'_verification_key', '');
                        update_option($module_name.'_last_verification', $response->response->timestamp);
                        update_option($module_name.'_log', $response->response_base64);
        
                        return ['status' => false, 'message' => $response->response->message];
                    }
                } catch (Exception $e) {
                    update_option($module_name.'_verification_key', '');
                    update_option($module_name.'_last_verification', time());
        
                    return ['status' => false, 'message' => 'Something went wrong'];
                }
            }else{
                update_option('flutex_admin_api_enabled', '0');
                update_option($module_name.'_verification_key', '');
                update_option($module_name.'_last_verification', $response->response->timestamp);
                update_option($module_name.'_log', $response->response_base64);

                return ['status' => false, 'message' => $response->response->message];
            }
        } catch (Exception $e) {
            update_option($module_name.'_verification_key', '');
            update_option($module_name.'_last_verification', time());

            return ['status' => false, 'message' => 'Something went wrong'];
        }

        return ['status' => false, 'message' => 'Something went wrong'];
    }
    
    public static function verify($module_name)
    {
        $purchase_code = get_option($module_name.'_verification_key');
        $last_verification = (int) get_option($module_name.'_last_verification');
        $date_from_last_verification = new DateTime();
        $date_from_last_verification->setTimestamp($last_verification);
        $date_from_last_verification->add(new DateInterval('P30D'));
        $verified = false;
        $data =  array(
			"api_key"  => '1bf51608-763d-469e-b60d-cfc588107003',
			"license_key" => base64_decode($purchase_code),
			"identifier" => base_url()
		);
		
		if (time() > $date_from_last_verification->getTimestamp()) {
		    try {
                $response = get_instance()->flutex_admin_api->call_api(base64_decode('aHR0cHM6Ly9saWNlbnNlLmJyYW5kaXR0YS5uZXQvYXBpL3YxL3ZlcmlmeQ=='), json_encode($data));
                $response = json_decode($response);
                $verified = (200 == $response->response->code);
            } catch (Exception $e) {
                $verified = false;
                get_instance()->app_modules->deactivate($module_name);
            }
    
            update_option($module_name.'_last_verification', time());
            return ['status' => $verified, 'message' => $response->response->message??''];
		}else{
		    return ['status' => true];
		}
    }
	
    private function call_api($url, $data = null)
    {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, 1);
		if($data)
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER,['Content-Type: application/json']);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30); 
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		$result = curl_exec($curl);
		
		if(!$result){
			$rs = array(
				'status' => FALSE, 
				'message' => 'Server is unavailable at the moment, please try again.'
			);
			return json_encode($rs);
		}
		
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if($http_status != 200){
			$rs = array(
				'status' => FALSE, 
				'message' => 'Server returned an invalid response, please contact support.'
			);
			return json_encode($rs);
		}
		
		curl_close($curl);
		return $result;
	}
	
    public static function module_checker()
    {
        if (!\function_exists('flutex_admin_api_init') || !\function_exists('flutex_admin_api_activation') || !\function_exists('flutex_admin_api_deregister')) {
            get_instance()->app_modules->deactivate('flutex_admin_api');
        }
    }
}
