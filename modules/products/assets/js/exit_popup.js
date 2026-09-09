(function() {
    if (typeof _productsExitPopups === 'undefined' || !_productsExitPopups || _productsExitPopups.length === 0) return;
    var popups = _productsExitPopups;
    var dismissDays = _productsExitPopupDismissDays || 7;
    var testMode = (typeof _productsExitPopupTest !== 'undefined' && _productsExitPopupTest == 1);
    var cookiePrefix = 'products_exit_popup_';
    var shownIds = {};
    var trackBase = (typeof _productsExitPopupTrackUrl !== 'undefined') ? _productsExitPopupTrackUrl : '/';

    function getCookie(name) {
        var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? v[2] : null;
    }
    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = name + '=' + value + ';path=/;expires=' + d.toUTCString();
    }
    function wasDismissed(id) {
        if (testMode || dismissDays <= 0) return false;
        return getCookie(cookiePrefix + id) === '1';
    }
    function markDismissed(id) {
        if (dismissDays > 0) setCookie(cookiePrefix + id, '1', dismissDays);
    }
    function trackImpression(id) {
        if (shownIds[id]) return;
        shownIds[id] = true;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', trackBase + 'impression/' + id, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('csrf_token=' + encodeURIComponent(typeof csrfData !== 'undefined' ? (csrfData.token || '') : ''));
    }
    function trackClick(id) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', trackBase + 'click/' + id, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('csrf_token=' + encodeURIComponent(typeof csrfData !== 'undefined' ? (csrfData.token || '') : ''));
    }
    function showPopup(p) {
        if (wasDismissed(p.id)) return;
        var wrap = document.createElement('div');
        wrap.className = 'products-exit-popup-overlay';
        wrap.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';
        var box = document.createElement('div');
        box.className = 'products-exit-popup-box';
        box.style.cssText = 'background:#fff;border-radius:8px;max-width:480px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,0.3);position:relative;';
        var closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'position:absolute;top:10px;right:15px;background:none;border:none;font-size:28px;cursor:pointer;color:#999;line-height:1;';
        closeBtn.onclick = function() { markDismissed(p.id); document.body.removeChild(wrap); };
        box.appendChild(closeBtn);
        var inner = document.createElement('div');
        inner.style.cssText = 'padding:24px;';
        if (p.image_url) {
            var img = document.createElement('img');
            img.src = p.image_url;
            img.style.cssText = 'max-width:100%;height:auto;border-radius:6px;margin-bottom:16px;';
            inner.appendChild(img);
        }
        var title = document.createElement('h3');
        title.style.cssText = 'margin:0 0 12px 0;font-size:20px;';
        title.textContent = p.title;
        inner.appendChild(title);
        var body = document.createElement('div');
        body.innerHTML = p.body;
        body.style.cssText = 'margin-bottom:16px;line-height:1.5;color:#444;';
        inner.appendChild(body);
        if (p.coupon_code) {
            var coup = document.createElement('div');
            coup.style.cssText = 'background:#f5f5f5;padding:10px 14px;border-radius:4px;margin-bottom:16px;font-family:monospace;font-weight:bold;';
            coup.textContent = p.coupon_code;
            inner.appendChild(coup);
        }
        if (p.cta_text && p.cta_url) {
            var btn = document.createElement('a');
            btn.href = p.cta_url;
            btn.textContent = p.cta_text;
            btn.className = 'btn btn-success';
            btn.style.marginRight = '8px';
            btn.onclick = function() { trackClick(p.id); };
            inner.appendChild(btn);
        }
        var stayBtn = document.createElement('button');
        stayBtn.textContent = 'Stay';
        stayBtn.className = 'btn btn-default';
        stayBtn.onclick = function() { markDismissed(p.id); document.body.removeChild(wrap); };
        inner.appendChild(stayBtn);
        box.appendChild(inner);
        wrap.appendChild(box);
        wrap.onclick = function(e) { if (e.target === wrap) { markDismissed(p.id); document.body.removeChild(wrap); } };
        document.body.appendChild(wrap);
        trackImpression(p.id);
    }
    var exitIntentShown = false;
    function firstByTrigger(type) {
        for (var i = 0; i < popups.length; i++) {
            if (popups[i].trigger_type === type && !wasDismissed(popups[i].id)) return popups[i];
        }
        return null;
    }
    function firstAny() {
        for (var i = 0; i < popups.length; i++) {
            if (!wasDismissed(popups[i].id)) return popups[i];
        }
        return null;
    }
    function tryExitIntent() {
        if (exitIntentShown) return;
        var p = firstByTrigger('exit_intent');
        if (p) { exitIntentShown = true; showPopup(p); }
    }
    document.addEventListener('mouseout', function(e) {
        var atTop = e.clientY <= 10;
        var leftDoc = !e.relatedTarget;
        if (atTop || leftDoc) tryExitIntent();
    });
    document.documentElement.addEventListener('mouseleave', tryExitIntent);
    if (testMode) {
        setTimeout(function() {
            var p = firstAny();
            if (p) showPopup(p);
        }, 500);
    } else {
        for (var j = 0; j < popups.length; j++) {
            var pp = popups[j];
            if (pp.trigger_type === 'time_delay') {
                var delayMs = (pp.trigger_value > 0 ? pp.trigger_value : 1) * 1000;
                setTimeout(function() {
                    var p = firstByTrigger('time_delay');
                    if (p) showPopup(p);
                }, delayMs);
                break;
            }
        }
    }
    (function setupScroll() {
        for (var k = 0; k < popups.length; k++) {
            if (popups[k].trigger_type === 'scroll' && popups[k].trigger_value > 0) {
                var targetPct = popups[k].trigger_value;
                var handler = function() {
                    var h = document.documentElement.scrollHeight - window.innerHeight;
                    if (h <= 0) return;
                    var scrolled = (window.scrollY || document.documentElement.scrollTop) / h * 100;
                    if (scrolled >= targetPct) {
                        var p = firstByTrigger('scroll');
                        if (p) showPopup(p);
                        window.removeEventListener('scroll', handler);
                    }
                };
                window.addEventListener('scroll', handler);
                break;
            }
        }
    })();
})();
