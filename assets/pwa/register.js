(function () {
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
