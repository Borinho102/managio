<script>
	(function($) {
		"use strict";

		 appValidateForm($('#member-form'),{
		    firstname: 'required',
		    lastname: 'required',
		    username: 'required',
		    email: 'required',
		    phone: 'required',
		    <?php if(!isset($member)){ ?>
		    	password: 'required',
			<?php } ?>
		   });
	})(jQuery);

	<?php if(!isset($member)){ ?>
		$("form").on('submit', function() {

		    if($.trim($('input[name="password"]').val()) == ''){
		      $('input[name="password"]').val('').focus();
		      return false;
		    }
		});
	<?php } ?>
</script>