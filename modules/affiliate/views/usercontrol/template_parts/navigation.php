<?php defined('BASEPATH') or exit('No direct script access allowed');
ob_start();
?>
<li id="top_search" class="dropdown" data-toggle="tooltip" data-placement="bottom" data-title="<?php echo _l('search_by_tags'); ?>">
   <input type="search" id="search_input" class="form-control" placeholder="<?php echo _l('top_search_placeholder'); ?>">
   <div id="search_results">
   </div>
   <ul class="dropdown-menu search-results animated fadeIn no-mtop search-history" id="search-history">
   </ul>
</li>
<li id="top_search_button">
   <button class="btn"><i class="fa fa-search"></i></button>
</li>
<?php
$top_search_area = ob_get_contents();
ob_end_clean();
?>
<div id="header">
   <?php if(is_affiliate_logged_in()){ ?>
   <button type="button" class="hide-menu tw-inline-flex tw-bg-transparent tw-border-0 tw-p-1 tw-mt-4 hover:tw-bg-neutral-600/10 tw-text-neutral-600 hover:tw-text-neutral-800 focus:tw-text-neutral-800 focus:tw-outline-none tw-rounded-md tw-mx-4 ltr:md:tw-ml-4 rtl:md:tw-mr-4 ltr:tw-float-left  rtl:tw-float-right">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="tw-h-4 tw-w-4 tw-text-current">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.003h19.5m-19.5-6h19.5m-19.5-6h19.5"></path>
        </svg>
    </button>
<?php } ?>
   

   <?php if(is_affiliate_logged_in()){ ?>
   <button type="button"
        class="navbar-toggle visible-md visible-sm visible-xs mobile-menu-toggle collapsed tw-ml-1.5"
        data-toggle="collapse" data-target="#mobile-collapse" aria-expanded="false" >
        <i class="fa fa-chevron-down fa-lg"></i>
    </button>
    <div class="mobile-navbar collapse" id="mobile-collapse" aria-expanded="false" role="navigation">
        <ul class="nav navbar-nav">
            <li class="header-my-profile"><a href="<?php echo site_url('affiliate/usercontrol/profile'); ?>"><?php echo _l('nav_my_profile'); ?></a></li>
             <li class="header-logout">
                <a href="<?php echo site_url('affiliate/authentication_affiliate/logout'); ?>"><?php echo _l('nav_logout'); ?></a>
             </li>
        </ul>
    </div>
   <ul class="nav navbar-nav navbar-right">
    <li class="icon header-user-profile" data-toggle="tooltip" title="<?php echo get_affiliate_full_name(); ?>" data-placement="bottom">
      <a href="#" class="dropdown-toggle profile" data-toggle="dropdown" aria-expanded="false"><img src="<?php echo affiliate_member_profile_image_url($affiliate->id,'thumb'); ?>" data-toggle="tooltip" data-placement="bottom" class="client-profile-image-small mright5">
      </a>
      <ul class="dropdown-menu animated fadeIn">
         <li class="header-my-profile"><a href="<?php echo site_url('affiliate/usercontrol/profile'); ?>"><?php echo _l('nav_my_profile'); ?></a></li>
         <li class="header-logout">
            <a href="<?php echo site_url('affiliate/authentication_affiliate/logout'); ?>"><?php echo _l('nav_logout'); ?></a>
         </li>
      </ul>
   </li>
</ul>
<?php } ?>
   

</div>