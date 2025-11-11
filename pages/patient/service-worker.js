// ⚠️ DEPRECATED: This service worker has been consolidated into the unified service worker
// Please use the root service worker at: /connect/service-worker.js
// This file redirects to the unified service worker for backward compatibility

console.warn('[Patient Service Worker] DEPRECATED: Redirecting to unified service worker at /connect/service-worker.js');
console.warn('[Patient Service Worker] Please update your service worker registration to use: navigator.serviceWorker.register("/connect/service-worker.js")');

// Immediately unregister this service worker and redirect to unified one
self.addEventListener('install', event => {
  console.log('[Patient Service Worker] DEPRECATED - Skipping to activation');
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  console.log('[Patient Service Worker] DEPRECATED - Unregistering and redirecting to unified service worker');
  event.waitUntil(
    Promise.all([
      // Clean up old patient-specific caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.filter(name => name.includes('patient')).map(name => {
            console.log('[Patient Service Worker] Deleting old cache:', name);
            return caches.delete(name);
          })
        );
      }),
      // Claim clients
      self.clients.claim(),
      // Notify clients to re-register with unified service worker
      self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'SW_DEPRECATED',
            message: 'Patient service worker deprecated. Please re-register with /connect/service-worker.js',
            newServiceWorkerPath: '/connect/service-worker.js'
          });
        });
      })
    ]).then(() => {
      console.log('[Patient Service Worker] Migration to unified service worker complete');
    })
  );
});

// Redirect all fetch requests - let browser handle naturally
self.addEventListener('fetch', event => {
  // Don't intercept - let it fall through to network/unified SW
  return;
}); 