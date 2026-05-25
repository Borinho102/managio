<?php

use Proxy\Http\Request;
use Proxy\Plugin\ProxifyPlugin;
use Proxy\Proxy;
use Proxy\Plugin\CorsPlugin;

if (!defined('BASEPATH'))
    exit('No direct script access allowed');


class Home extends App_Controller
{

    public $domain;
    public $themeName;
    public $themes;
    public $baseDir;
    public $themePath;
    public $themeUrl;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
        $this->load->model('cms_menuitems_model');
        $this->baseDir = module_dir_path(SaaS_MODULE, 'views/themebuilder/');
//        setBaseURL();
    }


    public function theme($name = null, $page = null, $params = null)
    {
        $data['title'] = _l('themes');

        list($themePath, $themeUrl) = get_theme_path_url($this->domain);
        $this->themePath = $themePath;
        $this->themeUrl = $themeUrl;

        if (empty($name)) {
            $name = get_option('saas_default_theme');
        }
        if (!empty($params)) {
            $page = $page . '/' . $params;
        } else if (empty($page)) {
            $page = 'index.html';
        }

        $landingFile = $this->themePath . '/' . $name . '/' . $page;
        $themeName = dirname(str_ireplace($this->themePath, '', $landingFile));
        $themeUrl = $this->themeUrl . $themeName;

        // check file exists or not
        if (!file_exists($landingFile)) {

            $this->saas_model->force_upload_theme($this->themePath, $this->baseDir, $this->themes);

            // reload the current url
            redirect($this->uri->uri_string());

            // file not found
            $error_file = APPPATH . 'views/errors/html/error_404.php';
            $message = 'File not found: ' . $landingFile;
            $heading = 'Page Not Found';
            $message = "$message 
        <script>
            let tag = document.querySelector('h1');
            if(tag){
                tag.innerHTML = '$heading';
            }
        </script>
    ";
            require_once($error_file);
            exit();

        }


        $html = file_get_contents($landingFile);
        $html = str_ireplace(
            ['"assets/', '\'assets/',],
            ['"' . $themeUrl . '/assets/', "'$themeUrl/assets/"],
            $html
        );
        $html = str_ireplace(['(assets/', '(&quot;assets/'], ["(" . $themeUrl . '/assets/', '(&quot;' . $themeUrl . '/assets/'], $html);
//        // /css and /js with base url
        $html = str_ireplace(
            ['"css/', '\'css/', '"js/', '\'js/', '"img/', '\'img/', '"images/', '\'images/'],
            ['"' . ($themeUrl . '/css/'), "'$themeUrl/css/", '"' . ($themeUrl . '/js/'), "'$themeUrl/js/", '"' . ($themeUrl . '/img/'), "'$themeUrl/img/", '"' . ($themeUrl . '/img/'), "'$themeUrl/images/", '"' . ($themeUrl . '/images/'), "'$themeUrl/images/"],
            $html
        );

        $html = str_ireplace(['[csrf_token_name]', '[csrf_token_hash]'], [$this->security->get_csrf_token_name(), $this->security->get_csrf_hash()], $html);
        $data['landing_page_content'] = $html;

        $this->load->view("themebuilder/index", $data);
    }

    public function index($page = null, $params = null)
    {

        $this->check_restriction();

        $themes = get_theme_list();
        $theme = get_option('saas_default_theme') ?? $themes[0];
        if (!empty($theme) && $theme != 'default') {
            $this->themeName = $theme;
            $this->theme($theme, $page, $params);
        } else {
            $data['active_menu'] = "home";
            $data['page_info'] = get_old_result('tbl_saas_front_pages', array('slug' => 'home'));
            $data['title'] = get_option('saas_companyname') ? get_option('saas_companyname') : 'Home';
            $data['subview'] = $this->load->view('frontcms/frontend/index', $data, true);
            $this->load->view('frontcms/_layout_front', $data);
        }
    }

    private function check_restriction()
    {
        $disable_frontend = get_option('disable_frontend');
        if (!empty($disable_frontend) && $disable_frontend == 1 || $disable_frontend == '1') {
            redirect('login');
        }

        $force_frontend = get_option('saas_force_redirect_to_dashboard');
        if ($force_frontend == "1" || $force_frontend == 1) {
            if (is_client_logged_in()) {
                return redirect('clients');
            }

            if (is_staff_logged_in()) {
                return redirect('admin');
            }
        }
        $url = get_option('saas_landing_page_url');
        $mode = get_option('saas_landing_page_url_mode');
        if (!empty($url) && $url != base_url() && $mode == 'redirection' && empty(subdomain())) {
            redirect($url);
        }

        if (!empty($mode) && $mode == 'proxy') {
            $this->proxy();
        }


    }

    /**
     * Method to serve the proxied landing page.
     * Its essensial the proxied adddress runs on same domain to prevent CORS or whitelabeled for this installation domain.
     *
     * @return void
     */
    public function proxy()
    {
        $url = get_option('saas_landing_page_url');
        require APP_MODULES_PATH . 'saas/vendor/autoload.php';

        $request = Request::createFromGlobals();

        $proxy = new Proxy();

        $proxy->getEventDispatcher()->addListener('request.before_send', function ($event) {

            $event['request']->headers->set('X-Forwarded-For', 'php-proxy');
        });

        $proxy->getEventDispatcher()->addListener('request.sent', function ($event) {
            if ($event['response']->getStatusCode() != 200) {
                show_error("Bad status code!", $event['response']->getStatusCode(), "Landing");
            }
        });

        $proxy->getEventDispatcher()->addListener('request.complete', function ($event) {
            $content = $event['response']->getContent();
            $content .= '<!-- via php-proxy -->';
            $event['response']->setContent($content);
        });

        $dispatcher = $proxy->getEventDispatcher();
        $proxify = new ProxifyPlugin();
        $proxify->subscribe($dispatcher);

        $cors = new CorsPlugin();
        $cors->subscribe($dispatcher);

        if (isset($_GET['q'])) {
            $url = url_decrypt($_GET['q']);
        }

        $response = $proxy->forward($request, $url);

        // send the response back to the client
        $response->send();
    }

    public function preview($dir = null, $page = null, $params = null)
    {
        // check page have .html or not
        if (strpos($page, '.html') !== false) {
            $page = $page;
        } else {
            $this->themeName = $page;
            $theme = $page;
            $page = null;
        }
        if (!empty($theme) && $theme != 'default') {
            $this->themeName = $theme;
            $this->theme($theme, $page, $params);
        }
    }

    public
    function client($page = null, $params = null)
    {
        $this->check_restriction();

        $sub = get_company_subscription();
        $themes = false;
        if (!empty($sub)) {
            $allowed_themes = (!empty($sub->allowed_themes) ? unserialize($sub->allowed_themes) : array());

            if (count($allowed_themes) > 0) {
                $themes = $allowed_themes;
                $this->themes = $themes;
            } else {
                redirect('login');
            }

            $this->domain = $sub->domain;
        }

        $theme = get_option('default_theme') ?? $themes[0];
        if (empty($theme)) {
            $theme = $themes[0];
        }
        $this->themeName = $theme;
        $this->theme($theme, $page, $params);
    }

    public
    function login($id = null)
    {
        // get referer from url and set in session
        $referer = $this->input->get('via');
        if (!empty($referer)) {
            $this->session->set_userdata('referer', $referer);
        }
        $data['title'] = get_option('saas_companyname') ? get_option('saas_companyname') : 'Login';
        $data['affiliate'] = true;
        $data['subview'] = $this->load->view('companies/panel/login', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function forgot_password($id = null)
    {
        $data['title'] = get_option('saas_companyname') ? get_option('saas_companyname') : 'Register';
        $data['affiliate'] = true;
        $data['subview'] = $this->load->view('companies/panel/forgot_password', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function register($id = null)
    {
        // get referer from url and set in session
        $referer = $this->input->get('via');
        if (!empty($referer)) {
            $this->session->set_userdata('referer', $referer);
        }
        $data['title'] = get_option('saas_companyname') ? get_option('saas_companyname') : 'Register';
        $data['active_menu'] = "pricing";
        if (!empty($id)) {
            $data['package'] = $this->saas_model->get_package_info($id);
            $data['package_id'] = $id;
        } else {
            $data['package'] = $this->saas_model->get_package_info();
            $data['package_id'] = $data['package']->id;
        }

        $data['saas_need_to_pay_when_register'] = get_option('saas_need_to_pay_when_register');
        $data['register'] = true;
        $data['subview'] = $this->load->view('frontcms/frontend/register', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function page($slug = null)
    {
        $data['page_info'] = get_old_result('tbl_saas_front_pages', array('slug' => $slug), false);
        $data['active_menu'] = $slug;
        if (empty($data['page_info'])) {
            $data['page_info'] = get_old_result('tbl_saas_front_pages', array('pages_id' => '4'), false);
        }
        $data['title'] = $data['page_info']->pages_id == 4 ? _l($slug) : $data['page_info']->title;
        $data['subview'] = $this->load->view('frontcms/frontend/index', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function affiliate_program($slug = null)
    {
        $data['active_menu'] = 'affiliate';
        $data['title'] = _l('affiliate_program');
        $data['subview'] = $this->load->view('frontcms/frontend/affiliate', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function find_my_company($slug = null)
    {
        $data['active_menu'] = 'home';
        $data['title'] = _l('find_my_company');
        $data['subview'] = $this->load->view('frontcms/frontend/find_my_company', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function become_affiliator($slug = null)
    {
        $data['active_menu'] = 'affiliate';
        $data['title'] = _l('affiliate_program');
        $data['subview'] = $this->load->view('affiliates/user/register', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function tos()
    {
        $data['active_menu'] = 'terms_and_conditions';
        $data['page_info'] = get_old_result('tbl_saas_front_pages', array('slug' => 'terms_and_conditions'), false);
        $data['subview'] = $this->load->view('frontcms/frontend/index', $data, true);
        $this->load->view('frontcms/_layout_front', $data);
    }

    public
    function save_faq()
    {
        $data = $this->saas_model->array_from_post(array('name', 'email', 'phone', 'subject'));
        $data['description'] = $this->input->post('comments');
        $data['phone'] = $data['phone'] ?? '';
        $this->saas_model->_table_name = 'tbl_saas_front_contact_us';
        $this->saas_model->_primary_key = 'id';
        $id = $this->saas_model->save($data);
        if (!empty($id)) {
            $comments = stripslashes($data['description']);
            $name = ($data['name']);
            $email = ($data['email']);
            $address = get_option('smtp_email');

            $e_subject = 'You have been contacted by ' . $name . '.';

            $e_body = "You have been contacted by $name. Their additional message is as follows." . PHP_EOL . PHP_EOL;
            $e_content = "\"$comments\"" . PHP_EOL . PHP_EOL;
            $e_reply = "You can contact $name via email, $email";

            $msg = wordwrap($e_body . $e_content . $e_reply, 70);

            $headers = "From: $email" . PHP_EOL;
            $headers .= "Reply-To: $email" . PHP_EOL;
            $headers .= "MIME-Version: 1.0" . PHP_EOL;
            $headers .= "Content-type: text/plain; charset=utf-8" . PHP_EOL;
            $headers .= "Content-Transfer-Encoding: quoted-printable" . PHP_EOL;

            if (mail($address, $e_subject, $msg, $headers)) {
                set_alert('success', 'Your message sent. Thanks for contacting. We will Contact you Soon.');
            }
        }
        redirect('/');
    }

    public function get_token()
    {
        $saasAuthToken = $this->input->get_request_header('saas-authtoken', TRUE);
        if (!empty($saasAuthToken)) {
            $token = $saasAuthToken;
            $check_token = get_row('tbl_saas_api_token', array('token' => $token, 'status' => 1));
            if (!empty($check_token)) {
                return $token;
            } else {
                return false;
            }
        }
        return false;
    }

    public function saas_data($function = null)
    {
        if (!empty($function) && method_exists($this, $function)) {
            // check header token is valid or not
            $token = $this->get_token();
            if (empty($token)) {
                $this->response_json(['error' => 'Invalid token']);
            }
            $this->$function();
        } else {
            $this->response_json(['error' => 'Invalid function']);
        }
    }

    public function get_package()
    {
        $packages = get_old_result('tbl_saas_packages', array('status' => 'published'));
        $this->response_json($packages);
    }

    public function get_modules()
    {
        $packages = get_any_field('tbl_saas_package_module',
            array('status' => 'published'),
            'tbl_saas_package_module.package_module_id as module_id,
            tbl_saas_package_module.module_title as module_name,
            tbl_saas_package_module.price,            
            tbl_saas_package_module.preview_image as preview_screenshot,            
            tbl_saas_package_module.preview_video_url,            
            tbl_saas_package_module.descriptions,            
            tbl_saas_package_module.module_order',
            true);

        $this->response_json($packages);
    }

    public function get_coupons()
    {
        $packages = get_old_join_data('tbl_saas_coupon',
            'tbl_saas_coupon.id,tbl_saas_coupon.name as coupon_name,tbl_saas_coupon.code,tbl_saas_coupon.amount,tbl_saas_coupon.end_date,tbl_saas_coupon.type,tbl_saas_coupon.package_type,tbl_saas_packages.name as package_name,',
            array('tbl_saas_coupon.status' => 'active'),
            array('tbl_saas_packages' => 'tbl_saas_packages.id = tbl_saas_coupon.package_id'),
            'object');
        $this->response_json($packages);
    }

    public function handlePost()
    {
        $post = file_get_contents('php://input');
        if (!empty($post)) {
            $data = json_decode($post, true);
            return $data;
        } else {
            $post = $this->input->post();
        }
        return $post;
    }

    public function check_domain()
    {
        $post = $this->handlePost();
        if (!empty($post)) {
            $check_domain = get_row('tbl_saas_companies', array('domain' => $post['domain']));
            $reserved = check_reserved_tenant($post['domain']);
            if (!empty($check_domain)) {
                $this->response_json(['status' => 'error', 'message' => 'Domain already exist']);
            } else if ($reserved) {
                $this->response_json(['status' => 'error', 'message' => 'Domain already exist']);
            } else {
                $this->response_json(['status' => 'success', 'message' => 'Domain is available']);
            }
        }
        $this->response_json(['error' => 'Invalid request']);

    }

    public
    function company_singup()
    {
        $_POST = $this->handlePost();
        $domain = $_POST['domain'];
        $data['name'] = $_POST['name'];
        $data['email'] = $_POST['email'];
        $data['package_id'] = $_POST['package_id'];
        $data['mobile'] = $_POST['mobile'];
        $data['address'] = $_POST['address'];
        $data['country'] = $_POST['country'];

        $data['domain'] = domainUrl($domain);
        $this->load->library('form_validation');
        $this->form_validation->set_rules('name', 'name', 'required|trim|min_length[3]|max_length[50]');
        $this->form_validation->set_rules('email', 'email', 'required|valid_email|trim|is_unique[tbl_saas_companies.email]');
        $this->form_validation->set_rules('package_id', 'package', 'required|trim');
        $this->form_validation->set_rules('domain', 'domain', 'required|trim|callback_check_domain');

        $data['timezone'] = ConfigItems('saas_default_timezone');
        $data['language'] = ConfigItems('saas_active_language');
        $data['created_date'] = date('Y-m-d H:i:s');
        $data['created_by'] = NULL;
        $disable_email_verification = ConfigItems('disable_email_verification');
        if (!empty($disable_email_verification) && $disable_email_verification == 1) {
            $data['status'] = 'running';
            $data['password'] = '123456';
        } else {
            $data['status'] = 'pending';
        }
        $company_url = companyUrl($data['domain']);
        $this->load->library('uuid');
        $data['activation_code'] = $this->uuid->v4();

        $check_email = get_row('tbl_saas_companies', array('email' => $data['email']));
        // check email already exist
        $check_domain = get_row('tbl_saas_companies', array('domain' => $data['domain']));
        $reserved = check_reserved_tenant($data['domain']);
        if (!empty($check_email)) {
            $type = 'error';
            $msg = _l('already_exists', _l('email'));
        } else if (!empty($check_domain)) {
            $type = 'error';
            $msg = _l('already_exists', _l('domain'));
        } else if (!empty($reserved)) {
            $type = 'error';
            $msg = _l('already_exists', _l('domain'));
        } else {
            if ($this->form_validation->run() == FALSE) {
                $type = 'warning';
                $msg = $this->form_validation->error_array();
            } else {
                $billing_cycle = $_POST['billing_cycle'];
                $package_info = get_row('tbl_saas_packages', array('id' => $data['package_id']));
                $package_info = apply_coupon($package_info);
                // deduct $billing_cycle from price
                $data['frequency'] = str_replace('_price', '', $billing_cycle);;
                $data['trial_period'] = $package_info->trial_period;
                $data['is_trial'] = 'Yes';
                $data['expired_date'] = $_POST['expired_date'];
                $data['currency'] = get_base_currency()->name;
                $offer_price = $data['frequency'] . '_offer';
                if (!empty($package_info->$offer_price)) {
                    $data['amount'] = $package_info->$offer_price;
                } else {
                    $data['amount'] = $package_info->$billing_cycle;
                }

                // enable_affiliate and get referral code from session
                $is_enabled = ConfigItems('enable_affiliate');
                $referer = $this->session->userdata('referer');
                if ($is_enabled && !empty($referer)) {
                    // get user id from referral
                    $user_info = get_row('tbl_saas_affiliate_users', array('referral_link' => $referer));
                    if (!empty($user_info)) {
                        $data['referral_by'] = $user_info->user_id;
                    }

                }

                $this->saas_model->_table_name = 'tbl_saas_companies';
                $this->saas_model->_primary_key = 'id';
                $id = $this->saas_model->save($data);

                $this->saas_model->save_client($id, $data['password']);

                if (!empty($data['referral_by'])) {
                    $this->saas_model->add_affiliate($id, $data, true);
                    // remove referral from session
                    $this->session->unset_userdata('referer');
                }

                // change active status to 0 for all previous data of this company
                $this->saas_model->_table_name = 'tbl_saas_companies_history';
                $this->saas_model->_primary_key = 'companies_id';
                $this->saas_model->save(array('active' => 0), $id);

                $data['companies_id'] = $id;
                $data['ip'] = $this->input->ip_address();
                $this->saas_model->update_company_history($data);

                // create database for this company
                if ($data['status'] == 'running') {
                    // create database for the company
                    $this->saas_model->create_database($id);
                }

                if (empty($disable_email_verification) && $disable_email_verification !== 1) {
                    $this->saas_model->send_activation_token_email($id);
                }

                $type = "success";
                if ($data['status'] == 'running') {
                    $msg = '';
                    $msg .= '<p>Hi ' . $data['name'] . ',</p>';
                    $msg .= '<p>here is your company URL Admin: <a href="' . $company_url . 'admin" target="_blank">' . $company_url . 'admin</a></p>';
                    $msg .= 'Username: ' . $data['email'] . '<br>';
                    $msg .= 'Password: ' . $data['password'] . '<br>';
                    $msg .= '<p>Thanks</p>';
                } else {
                    $msg = 'Registration Successfully Completed. Please check your email for activation link. if you not received email please check spam folder.if you still not received email please contact with us for activate your account.';
                }
                log_activity('New Company Created [ID:' . $id . ', Name: ' . $data['name'] . ']');

            }
        }
        $message = $msg;
        $this->response_json(['type' => $type, 'message' => $message]);
    }


    public function response_json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }


}
