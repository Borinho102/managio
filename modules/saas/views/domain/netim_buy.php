<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php if (empty($netim_configured)): ?>
<div class="alert alert-warning">
    <i class="fa fa-exclamation-triangle"></i>
    Domain registration is not available at this time. Please contact support.
</div>
<?php else: ?>

<!-- Domain Search Panel -->
<div class="panel_s" id="search-panel">
    <div class="panel-heading">
        <h4><i class="fa fa-search"></i> Search for a Domain</h4>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="input-group input-group-lg">
                    <input type="text" id="domain-input" class="form-control"
                           placeholder="mybusiness.com"
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <span class="input-group-btn">
                        <button id="search-btn" class="btn btn-primary btn-lg">
                            <i class="fa fa-search"></i> Search
                        </button>
                    </span>
                </div>
                <p class="text-muted text-center" style="margin-top:8px">
                    Enter a full domain name including extension (e.g. <code>mybusiness.com</code>)
                </p>
            </div>
        </div>

        <!-- Search results -->
        <div id="search-results" class="row" style="margin-top:20px; display:none;">
            <div class="col-md-8 col-md-offset-2">
                <div id="result-card" class="panel panel-default">
                    <div class="panel-body" id="result-body">
                        <!-- injected by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WHOIS Contact + Purchase Form (step=contact) -->
<div class="panel_s <?php echo ($step === 'contact') ? '' : 'hide'; ?>" id="contact-panel">
    <div class="panel-heading">
        <h4><i class="fa fa-user"></i> Registrant Information (WHOIS)</h4>
    </div>
    <div class="panel-body">
        <p class="text-muted">
            Domain regulations require valid registrant information. This will be associated with your domain in the public WHOIS database.
        </p>

        <?php
        $c_url = isset($c_url) ? $c_url : 'clients/';
        echo form_open(site_url($c_url . 'buy-domain/contact'), ['id' => 'contact-form']);
        ?>
        <input type="hidden" name="domain_name" id="form-domain-name"
               value="<?php echo $this->input->post('domain_name') ?? ($this->input->get('domain') ?? ''); ?>">
        <input type="hidden" name="price" id="form-price"
               value="<?php echo $this->input->post('price') ?? ''; ?>">
        <input type="hidden" name="currency" id="form-currency" value="USD">

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Legal Type <span class="text-danger">*</span></label>
                    <select name="legal_type" class="form-control" id="legal-type-select">
                        <option value="INDIVIDUAL">Individual</option>
                        <option value="COMPANY">Company</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6 company-name-group" style="display:none;">
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" class="form-control"
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->company_name ?? '') : ''; ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->first_name ?? '') : ''; ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->last_name ?? '') : ''; ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->email ?? '') : ''; ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" required
                           placeholder="+237600000000"
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->phone ?? '') : ''; ?>">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Address <span class="text-danger">*</span></label>
            <input type="text" name="address" class="form-control" required
                   value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->address ?? '') : ''; ?>">
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control" required
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->city ?? '') : ''; ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>State / Province</label>
                    <input type="text" name="state" class="form-control"
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->state ?? '') : ''; ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Zip / Postal Code <span class="text-danger">*</span></label>
                    <input type="text" name="zipcode" class="form-control" required
                           value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->zipcode ?? '') : ''; ?>">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Country (2-letter ISO code) <span class="text-danger">*</span></label>
            <input type="text" name="country" class="form-control" required
                   maxlength="2" placeholder="CM"
                   value="<?php echo isset($existing_contact) ? htmlspecialchars($existing_contact->country ?? '') : ''; ?>">
            <p class="help-block">Examples: CM (Cameroon), SN (Senegal), US (USA), FR (France)</p>
        </div>

        <div class="form-group">
            <label>Domain to Register</label>
            <input type="text" class="form-control" readonly id="domain-display"
                   value="<?php echo htmlspecialchars($this->input->get('domain') ?? ''); ?>">
        </div>

        <div class="btn-toolbar">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa fa-paper-plane"></i> Submit Domain Purchase Request
            </button>
            <a href="<?php echo site_url('clients/buy-domain'); ?>" class="btn btn-default btn-lg">
                <i class="fa fa-arrow-left"></i> Back to Search
            </a>
        </div>

        <?php echo form_close(); ?>
    </div>
</div>

<?php endif; ?>

<script>
$(document).ready(function () {

    // Legal type toggle
    $('#legal-type-select').on('change', function () {
        if ($(this).val() === 'COMPANY') {
            $('.company-name-group').show();
        } else {
            $('.company-name-group').hide();
        }
    });

    // Auto-run search if ?domain= in URL
    <?php if (!empty($_GET['domain'])): ?>
    searchDomain(<?php echo json_encode($_GET['domain']); ?>);
    <?php endif; ?>

    $('#search-btn').on('click', function () {
        var domain = $('#domain-input').val().trim();
        if (!domain) {
            alert_float('warning', 'Please enter a domain name.');
            return;
        }
        searchDomain(domain);
    });

    $('#domain-input').on('keypress', function (e) {
        if (e.which === 13) {
            $('#search-btn').click();
        }
    });

    function searchDomain(domain) {
        var $btn    = $('#search-btn');
        var $panel  = $('#search-results');
        var $body   = $('#result-body');

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Searching...');
        $panel.hide();

        <?php $csrf_name = get_instance()->security->get_csrf_token_name(); ?>
        $.post('<?php echo site_url((isset($c_url) ? $c_url : 'clients/') . 'domain-search'); ?>', {
            domain: domain,
            '<?php echo $csrf_name; ?>': $('input[name="<?php echo $csrf_name; ?>"]').val()
        }, function (data) {
            $panel.show();

            if (!data.success) {
                $body.html('<div class="alert alert-danger"><i class="fa fa-times"></i> ' + (data.error || 'Search failed') + '</div>');
                return;
            }

            var icon    = data.available ? '<i class="fa fa-check-circle text-success fa-2x"></i>' : '<i class="fa fa-times-circle text-danger fa-2x"></i>';
            var badge   = data.available ? '<span class="label label-success" style="font-size:14px">Available</span>' : '<span class="label label-danger" style="font-size:14px">Unavailable</span>';
            var price   = data.price ? '<strong style="font-size:18px">' + data.price + ' USD/year</strong>' : '';
            var buyBtn  = '';

            if (data.available) {
                buyBtn = '<a href="<?php echo site_url((isset($c_url) ? $c_url : 'clients/') . 'buy-domain/contact'); ?>?domain=' + encodeURIComponent(domain) + '&price=' + (data.price || '') + '" class="btn btn-success btn-lg">'
                       + '<i class="fa fa-shopping-cart"></i> Register This Domain</a>';
            }

            $body.html(
                '<div class="text-center" style="padding:15px">' +
                icon + ' &nbsp; ' +
                '<strong style="font-size:22px">' + domain + '</strong> &nbsp; ' +
                badge +
                (price ? '<br><br>' + price + ' &nbsp; per year' : '') +
                (buyBtn ? '<br><br>' + buyBtn : '<br><br><a href="<?php echo site_url((isset($c_url) ? $c_url : 'clients/') . 'buy-domain'); ?>" class="btn btn-default">Try another domain</a>') +
                '</div>'
            );
        }, 'json').always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-search"></i> Search');
        }).fail(function () {
            $panel.show();
            $body.html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
        });
    }

    // Pre-fill contact form domain fields from URL params
    var urlParams = new URLSearchParams(window.location.search);
    var d = urlParams.get('domain');
    var p = urlParams.get('price');
    if (d) {
        $('#form-domain-name').val(d);
        $('#domain-display').val(d);
    }
    if (p) {
        $('#form-price').val(p);
    }
});
</script>
