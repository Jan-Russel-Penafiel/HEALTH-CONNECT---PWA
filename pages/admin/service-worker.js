// Admin Service Worker - Simplified version
// This redirects to the unified service worker for better compatibility

const CACHE_NAME = 'healthconnect-admin-redirect-v1';

console.log('[Admin Service Worker] Starting redirection to unified service worker');

// Install event - immediately skip to activation
self.addEventListener('install', event => {
  console.log('[Admin Service Worker] Installing - will redirect to unified SW');
  event.waitUntil(self.skipWaiting());
});

// Activate event - clean up and redirect
self.addEventListener('activate', event => {
  console.log('[Admin Service Worker] Activating - cleaning up and redirecting');
  event.waitUntil(
    Promise.all([
      // Clean up old admin-specific caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.filter(name => name.includes('admin')).map(name => {
            console.log('[Admin Service Worker] Deleting old admin cache:', name);
            return caches.delete(name);
          })
        );
      }),
      // Claim clients
      self.clients.claim()
    ]).then(() => {
      // Notify clients to re-register with unified service worker
      return self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'SW_REDIRECT',
            message: 'Redirecting to unified service worker',
            newServiceWorkerPath: '/connect/service-worker.js'
          });
        });
      });
    }).then(() => {
      console.log('[Admin Service Worker] Migration to unified service worker complete');
    }).catch(error => {
      console.error('[Admin Service Worker] Migration failed:', error);
    })
  );
});

// Fetch event - don't intercept, let requests pass through
self.addEventListener('fetch', event => {
  // Don't intercept - let requests go to network/unified SW
  return;
});

// Message handling
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('[Admin Service Worker] Skip waiting requested');
    self.skipWaiting();
  }
}); 