"use strict"

var cart_items = [];

/**
 * Mirrors app_format_money so grid prices render as "CAD $7.00" rather than a
 * bare "7". PRODUCT_CURRENCY_FORMAT is emitted by the storefront view; the
 * fallbacks keep this working if the view is served from cache.
 */
function productsFormatMoney(amount) {
    var cfg = (typeof PRODUCT_CURRENCY_FORMAT !== 'undefined') ? PRODUCT_CURRENCY_FORMAT : {};
    var n = parseFloat(amount);
    if (isNaN(n)) { return ''; }
    var parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousand || ',');
    var num = parts.join(cfg.decimal || '.');
    var money = (cfg.placement === 'after') ? num + (cfg.symbol || '') : (cfg.symbol || '') + num;
    return (cfg.name ? cfg.name + ' ' : '') + money;
}

/**
 * Price cell for one grid row: a range when the product's variations differ in
 * price, otherwise the product's own rate.
 */
function productsGridFormatPrice(val, minPrice, maxPrice) {
    if (val.variations && val.variations.length && minPrice != maxPrice) {
        return productsFormatMoney(minPrice) + ' - ' + productsFormatMoney(maxPrice);
    }
    return productsFormatMoney(val.rate);
}

$(function() {
    filter_data();

    $(document).on('click', '.add_cart', function(event) {
        var button = $(this);
        var quantity = $(this).parents('.input_data').find('input[name="quantity"]').val();
        var max = $(this).parents('.input_data').find('input[name="quantity"]').attr('max');
        var variation_max = $(this).parents('.product-row').find('input.variation_quantity').attr('max');
        if (quantity <= 0 || !$.isNumeric(quantity)) {
            alert_float("danger", "Quantity Must Be Greater Than 0 ");
            return false;
        }
        var product_id = $(this).parents('.input_data').find('input[name="product_id"]').val();
        var product_variation_id = $(this).parents('.input_data').find('input[name="product_variation_id"]').val();
        var $productRow = $(this).parents('.product-row');
        if ($productRow.length && !$productRow.hasClass('without-variations') && !product_variation_id) {
            alert_float("danger", typeof product_please_choose_variation !== 'undefined' ? product_please_choose_variation : "Please choose an option from the variation dropdown.");
            return false;
        }
        if (product_variation_id) {
            if (parseInt(quantity) > parseInt(variation_max)) {
                alert_float("danger", `Only ${variation_max} Items are in stock for this Product`);
                return false;
            }
        } else {
            if (parseInt(quantity) > parseInt(max)) {
                alert_float("danger", `Only ${max} Items are in stock for this Product`);
                return false;
            }
        }
        var row = button.parents('.product-row');
        var prodName = row.find('.title h4').text().trim() || row.find('.title a').text().trim() || '';
        var rateVal = 0;
        if (row.find('input[name="product_variation_id"]').val()) {
            rateVal = parseFloat(row.find('.selectpicker.variation_value_id option:selected').data('price')) || 0;
        } else {
            var m = row.find('.product-price').text().match(/[\d.]+/);
            rateVal = m ? parseFloat(m[0]) : 0;
        }
        var wasUpdate = (button.text().toUpperCase().indexOf('UPDATE') !== -1);
        $.post(site_url+'products/client/add_cart', {quantity: quantity, product_id: product_id, product_variation_id: product_variation_id}, function(data, textStatus, xhr) {
            cart_items = $.parseJSON(data);
            button.text(typeof product_update_cart_label !== 'undefined' ? product_update_cart_label : "UPDATE CART");
            var msg = wasUpdate ? (typeof product_cart_updated_label !== 'undefined' ? product_cart_updated_label : "Cart updated") : (typeof product_added_to_cart_label !== 'undefined' ? product_added_to_cart_label : "Successfully added to cart");
            alert_float("success", msg);
            if (typeof _productsRemarketingAddToCart === 'function') {
                _productsRemarketingAddToCart(product_id, prodName, rateVal, parseInt(quantity) || 1);
            }
        });
    });

    $(document).on('change', '#product_categories', function(event) {
        filter_data({'p_category_id': $(this).val()});
    });
    $(document).on('click', '.btn-wishlist-toggle', function(e){
        e.preventDefault();
        if (typeof PRODUCT_WISHLIST_ENABLED === 'undefined' || !PRODUCT_WISHLIST_ENABLED) return;
        var $btn = $(this);
        var pid = $btn.data('product-id');
        var $row = $btn.closest('.product-row');
        var vid = $row.find('input[name="product_variation_id"]').val() || '';
        $btn.data('variation-id', vid);
        $.post(site_url+'products/client/toggle_wishlist', {product_id: pid, product_variation_id: vid}, function(res){
            if (res && res.status) {
                $btn.find('i').removeClass('fa fa-heart fa-heart-o far').addClass(res.in_wishlist ? 'fa fa-heart' : 'fa fa-heart-o far fa-heart');
                var msg = res.in_wishlist ? (typeof product_wishlist_added !== 'undefined' ? product_wishlist_added : 'Added to wishlist') : (typeof product_wishlist_removed !== 'undefined' ? product_wishlist_removed : 'Removed from wishlist');
                alert_float('success', msg);
            }
        }, 'json');
    });
});

function filter_data(post_data = {}) {
    $(".no_product").addClass('hidden');
    $("#filter_html").html("");
    $(".image_loader").show();
    $.ajax({
        url: site_url+'products/client/filter',
        type: 'POST',
        dataType: 'json',
        data : post_data,
        success : function (data) {
            render_product_data(data);
        }
    })
}

var product_variations = [];
function render_product_data(data) {
    var html = '';
    var total_taxes = "";
    cart_items = [];
    $.each(data, function(index, val) {
        var cart_data_quantity = "";
        var button = "";
        var product_class = "";

        if (val.cart_data) {
            cart_items.push(val.cart_data);
        }

        if (val.total_tax != 0) {
            total_taxes = `<span class='total_taxes text-warning'>(+ ${val.total_tax}% taxes)</span>`;
        } else {
            total_taxes = "";
        }

        // tracks_stock is 0 for services and digital products: they are
        // unlimited, so they are never out of stock and have no quantity cap.
        var tracksStock = (typeof val.tracks_stock !== 'undefined') ? (val.tracks_stock == 1) : (val.is_digital != 1);

        if (tracksStock && parseInt(val.quantity_number) < 1) {
            button = `<button class="btn btn-danger pull-right">${val.out_of_stock}</button>`;
        } else {
            var label  = val.add_to_cart;
            if (val.cart_data && !val.cart_data.product_variation_id && val.cart_data.quantity) {
                label  = val.update_cart;
                cart_data_quantity = val.cart_data.quantity;
            }
            button = `<button class="btn btn-warning add_cart pull-right">${label}</button>`
        }

        var max_attr = "";
        if (tracksStock) {
            max_attr = `max="${val.quantity_number}"`;
        }

        var recurring_type = "";
        var cycles_text = "&nbsp;";
        if (val.recurring != 0) {
            recurring_type = val.recurring_type;
            if (val.recurring_type == "") {
                recurring_type = "month";
            }
            recurring_type = "/ "+ ((val.recurring != 1) ? val.recurring:"") + ' '+ recurring_type;
            if (val.cycles == 0) {
                cycles_text = "Infinite recurring";
            }
            if (val.cycles == 1) {
                cycles_text = "1 time totally";
            }
            if (val.cycles > 1) {
                cycles_text = val.cycles+" times totally";
            }
        }
        var variations_content = "";
        var product_variation_ids = [];
        var product_rate = val.rate;
        var product_min_price = 0;
        var product_max_price = 0;
        if (val.variations && val.variations.length) {
                variations_content += '<div class="row variations">';
                variations_content += '<div class="col-md-6 col-sm-6 col-xs-6">';
                variations_content += '<label style="display:block;margin-bottom:4px;font-weight:500;">' + (typeof product_variation_table_heading !== 'undefined' ? product_variation_table_heading : 'Variation') + '</label>';
                variations_content += '<select class="selectpicker variation_id" data-width="100%">';
                variations_content += '<option value=""></option>';
                for (var variation_index = 0; variation_index < val.variations.length; variation_index++) {
                  if (!product_variation_ids.includes(val.variations[variation_index]['variation_id'])) {
                    product_variation_ids.push(val.variations[variation_index]['variation_id']);
                    variations_content += '<option value="' + val.variations[variation_index]['variation_id'] + '">' + val.variations[variation_index]['variation_name'] + '</option>';
                  }
                  if (!product_min_price) product_min_price = val.variations[variation_index]['rate'];
                  if (!product_max_price) product_max_price = val.variations[variation_index]['rate'];
                  if (parseFloat(product_min_price) > parseFloat(val.variations[variation_index]['rate'])) product_min_price = val.variations[variation_index]['rate'];
                  if (parseFloat(product_max_price) < parseFloat(val.variations[variation_index]['rate'])) product_max_price = val.variations[variation_index]['rate'];
                }
                if (product_min_price != product_max_price) {
                    product_rate = product_min_price + ' - ' + product_max_price;
                }
                variations_content += '</select>';
                variations_content += '</div>';
                variations_content += '<div class="col-md-6 col-sm-6 col-xs-6">';
                variations_content += '<label style="display:block;margin-bottom:4px;font-weight:500;">' + (typeof product_variation_table_value !== 'undefined' ? product_variation_table_value : 'Option') + '</label>';
                variations_content += '<select class="selectpicker variation_value_id" data-width="100%">';
                variations_content += '<option value=""></option>';
                variations_content += '</select>';
                variations_content += '</div>';
                variations_content += '</div>';
        } else {
            product_class = 'without-variations';
        }
        var productIdentifier = (val.slug && val.slug.length) ? val.slug : val.id;
        var productUrl = ((val.slug || val.id) && (typeof PRODUCT_DETAIL_ENABLED === 'undefined' || PRODUCT_DETAIL_ENABLED)) ? (site_url + 'products/client/product/' + productIdentifier) : '#';
        var productLink = (typeof PRODUCT_DETAIL_ENABLED !== 'undefined' && PRODUCT_DETAIL_ENABLED) ? `<a href="${productUrl}">${val.product_name}</a>` : val.product_name;
        var urgencyBadge = '';
        if (val.low_stock == 1) {
            urgencyBadge = '<span class="label label-warning product-urgency-badge">' + (typeof product_low_stock_label !== 'undefined' ? product_low_stock_label : 'Low stock') + '</span>';
        }
        var wishlistBtn = '';
        if (typeof PRODUCT_WISHLIST_ENABLED !== 'undefined' && PRODUCT_WISHLIST_ENABLED) {
            var wlIcon = (val.in_wishlist == 1) ? 'fa fa-heart' : 'fa fa-heart-o far fa-heart';
            var wlTitle = (typeof product_wishlist_label !== 'undefined' ? product_wishlist_label : 'Wishlist');
            wishlistBtn = '<a href="#" class="btn btn-default btn-sm btn-wishlist-toggle mtop5" data-product-id="' + val.id + '" data-variation-id="" title="' + wlTitle + '"><i class="' + wlIcon + '" aria-hidden="true"></i></a>';
        }
        html += `<div class="col-lg-4 col-md-4 col-sm-4 col-xs-12 product-row ${product_class}">
            <div class="thumbnail shadow">
                <div class="product-img-wrap" style="position:relative">
                    ${urgencyBadge}
                    <a href="${productUrl}"><img src="${val.product_image_url}" alt="${val.product_image}" class="img1" onerror="this.src='${val.no_image_url}'"></a>
                    <br>
                    <div class="title text-center text-warning">
                        <h4>${productLink}</h4>
                    </div>
                    <div class="description">
                        <span><center>${val.product_description}</center></span>
                    </div>
                    <br>
                    <div>
                        <div class="text-center">${val.p_category_name}</div>
                    </div>
                    <div class="rates products-pricing">
                        <h4><span class="product-price">${productsGridFormatPrice(val, product_min_price, product_max_price)}</span> ${recurring_type} ${total_taxes}</h4>
                        <h5 class="product-cycles">${cycles_text}</h5>
                    </div>
                    ${variations_content}
                    <div class="row input_data" id="">
                        <div class="col-md-6 col-sm-6 col-xs-6 products-pricing">
                            <input type="number" name="quantity" min="1" ${max_attr} value="${cart_data_quantity}" class="form-control" placeholder="${val.qty}">
                            <input type="hidden" name="product_id" value="${val.id}" class="form-control">
                            <input type="hidden" name="product_variation_id" value="" class="form-control">
                            <input type="hidden" min="1" ${max_attr} class="form-control variation_quantity">
                        </div>
                        <div class="col-md-6 col-sm-6 col-xs-6 products-pricing">
                            ${button}
                            ${wishlistBtn}
                        </div>
                    </div>
                </div>
            </div>   
        </div>`;
    });
    $("#filter_html").hide();
    $(".image_loader").hide();
    $("#filter_html").html(html).fadeIn('slow');
    if (html=="") {
        $(".no_product").removeClass('hidden');
    }
    
    $(document).on('change', '.selectpicker.variation_id' , function () {
        var that = this;
        var $row = $(this).parents('.product-row');
        $row.find('input[name="product_variation_id"]').val('');
        var varId = $row.find('.selectpicker.variation_id').val();
        if (!varId) {
            $row.find('.selectpicker.variation_value_id').html('<option value=""></option>').selectpicker('refresh');
            return;
        }
        $.ajax({
            url: site_url+'products/client/variation_values',
            type: 'POST',
            dataType: 'json',
            data : {
                'product_id': $row.find('input[name="product_id"]').val(),
                'variation_id': varId
            },
            success : function (data) {
                render_product_variation_values_data(that, data);
            }
        });
    });
    
    appSelectPicker();
    
    $('.product-row:not(.without-variations) .selectpicker.variation_id').each(function(){
        var $sel = $(this);
        if ($sel.find('option').length === 2) {
            $sel.val($sel.find('option:eq(1)').val()).trigger('change');
            if ($sel.data('selectpicker')) $sel.selectpicker('refresh');
        }
    });
}

function render_product_variation_values_data(el, data) {
    var variation_values_html = '';
    var variation_min_price = 0;
    var variation_max_price = 0;
    var variation_price = 0;
    if (data) {
        if (data.length) variation_values_html += '<option value=""></option>';
        for (var variation_index = 0; variation_index < data.length; variation_index++) {
            if (!variation_min_price) variation_min_price = data[variation_index]['rate'];
            if (!variation_max_price) variation_max_price = data[variation_index]['rate'];
            if (variation_min_price > data[variation_index]['rate']) variation_min_price = data[variation_index]['rate'];
            if (variation_max_price < data[variation_index]['rate']) variation_max_price = data[variation_index]['rate'];
          if (data[variation_index]['variation_id'] == $(el).parents('.product-row').find('.selectpicker.variation_id').val()) {
            variation_values_html += '<option value="' + data[variation_index]['id'] + '" data-quantity="' + data[variation_index]['quantity_number'] + '" data-price="' + data[variation_index]['rate'] + '">' + data[variation_index]['variation_value'] + '</option>';
          }
        }
    }
    if (variation_min_price != variation_max_price) {
        variation_price = variation_min_price + ' - ' + variation_max_price;
    } else {
        variation_price = variation_min_price;
    }
    $(el).parents('.product-row').find('.product-price').html(variation_price);
    var $valSel = $(el).parents('.product-row').find('.selectpicker.variation_value_id');
    $valSel.html(variation_values_html).selectpicker("refresh");
    var opts = $(el).parents('.product-row').find('.selectpicker.variation_value_id option[value!=""]');
    if (opts.length === 1) {
        $valSel.val(opts.first().val()).selectpicker("refresh");
        $(el).parents('.product-row').find('input[name="product_variation_id"]').val(opts.first().val());
        $(el).parents('.product-row').find('input.variation_quantity').attr('max', opts.first().data('quantity') || '');
        $(el).parents('.product-row').find('.add_cart').text("ADD TO CART");
    }
    
    $(document).on('change', '.selectpicker.variation_value_id' , function () {
        var product_id = $(this).parents('.product-row').find('input[name="product_id"]').val();
        var product_variation_id = $(this).val();
        $(this).parents('.product-row').find('input[name="product_variation_id"]').val(product_variation_id);
        var variation_options = $(this).find('option');
        for (var variation_index = 0; variation_index < variation_options.length; variation_index++) {
            if ($(variation_options[variation_index]).val() == product_variation_id) {
                $(this).parents('.product-row').find('input.variation_quantity').attr('max', $(variation_options[variation_index]).data('quantity'));
                $(this).parents('.product-row').find('.product-price').html($(variation_options[variation_index]).data('price'));
            }
        }
        var variation_added = false;
        for (var cart_item_index = 0; cart_item_index < cart_items.length; cart_item_index++) {
            if (cart_items[cart_item_index].product_id == product_id && 
                cart_items[cart_item_index].product_variation_id == product_variation_id) {
                variation_added = true;
            }
        }
        if (variation_added) {
            $(this).parents('.product-row').find('.add_cart').text("UPDATE CART");
        } else {
            $(this).parents('.product-row').find('.add_cart').text("ADD TO CART");
        }
    });
}