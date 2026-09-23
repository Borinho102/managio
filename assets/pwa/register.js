(function () {
  var DISMISS_KEY = 'pwa-install-dismissed';

  function isStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.matchMedia('(display-mode: window-controls-overlay)').matches ||
      window.matchMedia('(display-mode: tabbed)').matches ||
      window.navigator.standalone === true
    );
  }

  function markStandalone() {
    if (!isStandalone()) {
      return;
    }
    document.documentElement.classList.add('pwa-standalone');
    if (document.body) {
      document.body.classList.add('pwa-standalone');
    }
  }

  function brandColor() {
    var meta = document.querySelector('meta[name="theme-color"]');
    return (meta && meta.getAttribute('content')) || '#2563eb';
  }

  function appName() {
    var meta = document.querySelector('meta[name="application-name"]');
    return (meta && meta.getAttribute('content')) || document.title || 'App';
  }

  function hideBanner() {
    var el = document.getElementById('pwa-install-banner');
    if (el && el.parentNode) {
      el.parentNode.removeChild(el);
    }
  }

  function showInstallBanner(deferredPrompt) {
    if (!deferredPrompt || isStandalone()) {
      return;
    }
    if (localStorage.getItem(DISMISS_KEY)) {
      return;
    }
    if (document.getElementById('pwa-install-banner')) {
      return;
    }

    var color = brandColor();
    var banner = document.createElement('div');
    banner.id = 'pwa-install-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Install app');
    banner.style.cssText =
      'position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));z-index:99999;' +
      'display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;' +
      'background:' +
      color +
      ';color:#fff;font-family:system-ui,-apple-system,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.2);';

    var copy = document.createElement('div');
    copy.style.cssText = 'flex:1;min-width:0;font-size:14px;line-height:1.35;';
    copy.textContent = 'Install ' + appName() + ' for faster access.';

    var later = document.createElement('button');
    later.type = 'button';
    later.textContent = 'Not now';
    later.style.cssText =
      'background:transparent;border:0;color:#fff;opacity:.85;font:inherit;cursor:pointer;padding:8px;';

    var install = document.createElement('button');
    install.type = 'button';
    install.textContent = 'Install';
    install.style.cssText =
      'background:#fff;border:0;color:' +
      color +
      ';font:inherit;font-weight:600;cursor:pointer;padding:8px 12px;border-radius:10px;';

    later.addEventListener('click', function () {
      try {
        localStorage.setItem(DISMISS_KEY, '1');
      } catch (e) {}
      hideBanner();
    });

    install.addEventListener('click', function () {
      hideBanner();
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(function () {});
    });

    banner.appendChild(copy);
    banner.appendChild(later);
    banner.appendChild(install);
    document.body.appendChild(banner);
  }

  markStandalone();
  try {
    window.matchMedia('(display-mode: standalone)').addEventListener('change', markStandalone);
  } catch (e) {}

  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    window.setTimeout(function () {
      showInstallBanner(deferredPrompt);
    }, 4000);
  });

  window.addEventListener('appinstalled', function () {
    hideBanner();
    deferredPrompt = null;
    try {
      localStorage.removeItem(DISMISS_KEY);
    } catch (e) {}
  });

  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', function () {
    navigator.serviceWorker
      .register('/sw.js', { scope: '/' })
      .then(function (reg) {
        if (reg.waiting) {
          reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
        if ('periodicSync' in reg) {
          navigator.permissions
            .query({ name: 'periodic-background-sync' })
            .then(function (status) {
              if (status.state === 'granted') {
                return reg.periodicSync.register('pwa-refresh', { minInterval: 12 * 60 * 60 * 1000 });
              }
            })
            .catch(function () {});
        }
        if ('sync' in reg && navigator.onLine === false) {
          reg.sync.register('pwa-sync').catch(function () {});
        }
      })
      .catch(function () {});

    window.addEventListener('online', function () {
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.ready.then(function (reg) {
          if (reg.sync) {
            reg.sync.register('pwa-sync').catch(function () {});
          }
        });
      }
    });
  });
})();
