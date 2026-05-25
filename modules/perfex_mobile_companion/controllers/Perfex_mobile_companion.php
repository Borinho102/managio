<?php

use Detection\MobileDetect;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class Perfex_mobile_companion extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('string');
    }
    public function index()
    {
        show_404();
    }

    public function get_qr_data()
    {
        $random_string = random_string('alnum', 16);
        $admin_url = admin_url('perfex_mobile_companion/store_open?qr_code_otp=' . $random_string);

        $result = Builder::create()
            ->writer(new PngWriter()) // Output format (PNG)
            ->writerOptions([])
            ->data($admin_url) // The QR Code data
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High) // High correction (better with logos)
            ->size(400) // QR Code size
            ->margin(20) // White border around QR code
            ->logoPath(site_url('modules/perfex_mobile_companion/assets/app-icon.png')) // Path to your logo
            ->logoResizeToWidth(80) // Resize logo width
            ->logoResizeToHeight(80) // Resize logo height
            ->build(); // Generate QR code
        
        $this->db->where('staffid', get_staff_user_id())->update(db_prefix() . 'staff', ['qr_code_otp' => $random_string, 'otp_valid_until' => strtotime('+10 minutes', time())]);

        $this->load->view(PERFEX_MOBILE_COMPANION . '/modals/mobileapp', ['QR' => base64_encode($result->getString())]);
    }

    public function store_open()
    {
        $detect = new MobileDetect();

        if ($detect->isMobile()) {
            if ($detect->isAndroidOS()) {
                // Redirect Android users to a specific URL
                header('Location: https://play.google.com/store/apps/details?id=com.myperfexcrm.app&crm=' . admin_url(), true, 302);
                exit;
            } elseif ($detect->isiOS()) {
                // Redirect iOS users to a specific URL
                header('Location: https://apps.apple.com/us/app/syndeopro-crm/id1625111197', true, 302);
                exit;
            } else {
                // Redirect other mobile users to a generic mobile URL
                header('Location: ' . admin_url(), true, 302);
                exit;
            }
        } else {
            // Redirect desktop users to a specific URL
            header('Location: ' . admin_url(), true, 302);
            exit;
        }
    }
}
