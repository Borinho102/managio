<?php defined('BASEPATH') or exit('No direct script access allowed');
include_once(__DIR__ . '/traits/SaasMailTemplate.php');

class Saas_company_expiration_email extends Saas_mail_template
{

    use SaasMailTemplate;

    /**
     * Send immediately — do not wait for the mail queue/cron.
     */
    protected $skipQueue = true;

    /**
     * @inheritDoc
     */
    public $rel_type = 'company';

    /**
     * @inheritDoc
     */
    protected $for = 'customer';

    /**
     * @inheritDoc
     */
    public $slug = 'saas-company-expiration-email';
}
