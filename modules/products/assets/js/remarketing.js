(function() {
    if (typeof _productsRemarketing === 'undefined') return;
    var cfg = _productsRemarketing;
    var fb = cfg.facebook;
    var gid = cfg.google_id;
    var currency = (cfg.currency || 'USD').toUpperCase();

    function fireAddToCart(productId, productName, value, qty) {
        if (fb && typeof fbq !== 'undefined') {
            fbq('track', 'AddToCart', {content_ids: [String(productId)], content_name: productName, value: parseFloat(value) || 0, currency: currency, num_items: parseInt(qty, 10) || 1});
        }
        if (gid && typeof gtag !== 'undefined') {
            gtag('event', 'add_to_cart', {currency: currency, value: parseFloat(value) || 0, items: [{item_id: String(productId), item_name: productName, price: parseFloat(value) || 0, quantity: parseInt(qty, 10) || 1}]});
        }
    }

    function fireViewContent(productId, productName, value) {
        if (fb && typeof fbq !== 'undefined') {
            fbq('track', 'ViewContent', {content_ids: [String(productId)], content_name: productName, content_type: 'product', value: parseFloat(value) || 0, currency: currency});
        }
        if (gid && typeof gtag !== 'undefined') {
            gtag('event', 'view_item', {currency: currency, value: parseFloat(value) || 0, items: [{item_id: String(productId), item_name: productName, price: parseFloat(value) || 0}]});
        }
    }

    function fireInitiateCheckout(value) {
        if (fb && typeof fbq !== 'undefined') {
            fbq('track', 'InitiateCheckout', {value: parseFloat(value) || 0, currency: currency});
        }
        if (gid && typeof gtag !== 'undefined') {
            gtag('event', 'begin_checkout', {currency: currency, value: parseFloat(value) || 0});
        }
    }

    function firePurchase(value, orderId) {
        if (fb && typeof fbq !== 'undefined') {
            fbq('track', 'Purchase', {value: parseFloat(value) || 0, currency: currency, order_id: String(orderId || '')});
        }
        if (gid && typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {currency: currency, value: parseFloat(value) || 0, transaction_id: String(orderId || '')});
        }
    }

    window._productsRemarketingAddToCart = fireAddToCart;
    window._productsRemarketingPurchase = firePurchase;

    var path = window.location.pathname || '';
    if (path.indexOf('/products/client/product/') !== -1 && typeof _productsRemarketingViewContent !== 'undefined') {
        var v = _productsRemarketingViewContent;
        fireViewContent(v.id, v.name, v.value);
    }
    if (path.indexOf('/products/client/place_order') !== -1 && typeof _productsRemarketingCheckout !== 'undefined') {
        fireInitiateCheckout(_productsRemarketingCheckout);
    }
    if (path.indexOf('/products/client/thankyou') !== -1 && typeof _productsRemarketingPurchaseData !== 'undefined') {
        var p = _productsRemarketingPurchaseData;
        firePurchase(p.value || 0, p.order_id);
    }
})();
