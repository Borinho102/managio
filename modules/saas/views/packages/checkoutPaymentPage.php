<div class="panel panel-custom">
    <div class="panel-heading tw-p-4">
        <h4 class="panel-title "><?= _l('payment') . '  ' . _l('summery') . ' - ' ?>
            <span id="package_name"><?= $package_info->name ?></span>
        </h4>
    </div>
    <div class="panel-body form-horizontal">
        <div class=" mb-lg ">
            <div class="col-lg-7 col-md-7 ">
                <div class="">
                    <?php
                    echo $paymentForm;
                    ?>
                </div>
            </div>
            <div class="col-lg-5 col-md-5" id="package_info">

            </div>
        </div>

    </div>
</div>
<script type="text/javascript">
    'use strict';
    // check package_id is empty or not by name
    $(document).ready(function () {
        var package_id = '<?= $package_id ?>';
        // if package_id is not empty then trigger onchange event
        if (package_id != '') {
            get_package_info(package_id, '<?= $frequency?>_price', '<?= $company_id?>');
        }
    });

    function get_package_info(package_id, package_type = 'monthly_price', company_id = '') {
        // check input mark_paid is checked or not
        var is_coupon = $('input[name="is_coupon"]').is(":checked");
        // if company_id is empty then get from input
        if (company_id === '') {
            company_id = $('#company_id').val();
        }

        $.ajax({
            type: 'POST',
            url: '<?= base_url('get_package_info') ?>',
            data: {package_id, package_type, company_id},
            dataType: "json",
            success: function (result) {
                $('#billing_cycle').html(result.package_form_group);
                $('#package_info').html(result.package_details);
                $('#package_name').html(result.package_info.name);
                if (is_coupon) {
                    $('.coupon_code_area').show();
                    $('input[name="is_coupon"]').prop('checked', true);

                    var coupon_code = $('#coupon_code').val();
                    if (coupon_code != '') {
                        var formData = {
                            'coupon_code': $('#coupon_code').val(),
                            'billing_cycle': $('[name="billing_cycle"]').val(),
                            'package_id': $('[name="mark_paid"]').val(),
                            'email': $('#check_email').val(),
                        };
                        $.ajax({
                            type: "post",
                            url: "<?= base_url() ?>saas/gb/check_coupon_code",
                            data: formData, // our data object
                            dataType: 'json', // what type of data do we expect back from the server
                            success: function (data) {
                                if (data.success == true) {
                                    $('#applied_discount').html(data.applied_discount);
                                    $('#sub_total').val(data.sub_total_input);
                                    $('.sub_total_text').html('<?= _l('sub_total') ?>');
                                    $('#final_amount').html(data.total_amount);
                                } else {
                                    $('#discount_error').html(data.message);
                                }
                            }
                        });
                    }
                }
            }
        });
    }
</script>


