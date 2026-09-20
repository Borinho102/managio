<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="col-md-12">
	<h3 id="greeting" class="no-mtop"></h3>
		<div class="panel_s">
			<div class="panel-body">
				<div class="row">
      		<div class="col-md-12">
				<h3 class="text-success projects-summary-heading no-mtop mbot15"><?php echo _l('transactions'); ?></h3>
         <hr>
         <?php if (function_exists('get_affiliate_user_code') && function_exists('saas_affiliate_website_referral_url')) { ?>
         <div class="alert alert-info">
            <p class="bold"><?php echo _l('website_referral_link'); ?></p>
            <p class="text-muted"><?php echo _l('website_referral_link_help'); ?></p>
            <div class="input-group">
               <input type="text" class="form-control" id="website_referral_link" readonly value="<?php echo saas_affiliate_website_referral_url(get_affiliate_user_code()); ?>">
               <span class="input-group-btn">
                  <a href="javascript:void(0)" onclick="var el=document.getElementById('website_referral_link'); el.select(); document.execCommand('copy'); return false;" class="btn btn-warning"><?php echo _l('copy'); ?></a>
               </span>
            </div>
         </div>
         <?php } ?>
      </div>
      <div class="col-lg-6 col-xs-12 col-md-12 total-column">
      <div class="panel_s">
         <div class="panel-body">
            <h3 class="text-muted _total">
               <?php
               $this->load->model('currencies_model');
               $currency = $this->currencies_model->get_base_currency(); 
                echo app_format_money(affiliate_sum_transaction(get_affiliate_user_id()), $currency->name); ?>
            </h3>
            <span class="text-warning"><?php echo _l('total'); ?></span>
         </div>
      </div>
   </div>
      <div class="col-lg-6 col-xs-12 col-md-12 total-column">
        <div class="panel_s">
           <div class="panel-body">
              <h3 class="text-muted _total">
                 <?php echo app_format_money(affiliate_sum_transaction(get_affiliate_user_id(), true), $currency->name); ?>
              </h3>
              <span class="text-success"><?php echo _l('this_month'); ?></span>
           </div>
        </div>
      </div>
      <div id="dashboard-commission-chart">
        <div class="row">
          <figure class="highcharts-figure col-md-12">
            <div id="commission_chart"></div>
          </figure>
        </div>
      </div>
     </div>
	</div>
</div>
</div>
