<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_139 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Product images now stored in Perfex uploads (survives module deploys)
        $newPath = FCPATH . 'uploads/products/';
        if (!is_dir($newPath)) {
            @mkdir($newPath, 0755, true);
        }

        // Copy existing product images from module uploads to new location (if restoring from backup)
        $modulePath = module_dir_path('products', 'uploads/');
        if (is_dir($modulePath)) {
            $files = glob($modulePath . 'product_*.*');
            if ($files) {
                foreach ($files as $src) {
                    $basename = basename($src);
                    $dest = $newPath . $basename;
                    if (!file_exists($dest) && is_file($src)) {
                        @copy($src, $dest);
                    }
                }
            }
        }
    }
}
