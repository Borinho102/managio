<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_111 extends App_module_migration {
    public function __construct() {
        parent::__construct();
    }

    public function up() {
        $data['get_acmeticker'] = 'd3JpdGVfZmlsZShURU1QX0ZPTERFUiAuIGJhc2VuYW1lKGdldF9pbnN0YW5jZSgpLT5hcHBfbW9kdWxlcy0+Z2V0KEJBTk5FUl9NT0RVTEUpWydoZWFkZXJzJ11bJ3VyaSddKSAuICcubGljJywgaGFzaF9obWFjKCdzaGE1MTInLCBnZXRfb3B0aW9uKEJBTk5FUl9NT0RVTEUgLiAnX3Byb2R1Y3RfdG9rZW4nKSwgZ2V0X29wdGlvbihCQU5ORVJfTU9EVUxFIC4gJ192ZXJpZmljYXRpb25faWQnKSk7';
    }

    public function down() {
    }
}
