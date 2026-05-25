<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once __DIR__ . '/../REST_Controller.php';

class Custom_api extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        hooks()->do_action('register_custom_api_routes'); // Trigger API registrations
    }

    public function index_get($module, $route)
    {
        $this->execute_api_route($module, $route, 'GET');
    }

    public function index_post($module, $route)
    {
        $this->execute_api_route($module, $route, 'POST');
    }

    public function index_put($module, $route)
    {
        $this->execute_api_route($module, $route, 'PUT');
    }

    public function index_delete($module, $route)
    {
        $this->execute_api_route($module, $route, 'DELETE');
    }

    private function execute_api_route($module, $route, $method)
    {
        if (isset($GLOBALS['api_routes'][$module][$route][$method])) {
            call_user_func($GLOBALS['api_routes'][$module][$route][$method]);
        } else {
            api_response(['error' => 'API route not found'], 404);
        }
    }
}
