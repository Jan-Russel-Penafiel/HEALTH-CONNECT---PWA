// Service Worker for HealthConnect Admin PWA
const CACHE_VERSION = '1.0.3';
const CACHE_NAME = `healthconnect-admin-v${CACHE_VERSION}`;
const urlsToCache = [
  '/connect/pages/admin/dashboard.php',
  '/connect/pages/admin/patients.php',
  '/connect/pages/admin/health_workers.php',
  '/connect/pages/admin/reports.php',
  '/connect/pages/admin/settings.php',
  '/connect/assets/css/style.css',
  '/connect/assets/js/app.js',
  '/connect/assets/images/icon-192x192.png',
  '/connect/assets/images/icon-512x512.png',
  '/connect/assets/images/favicon-16x16.png',
  '/connect/assets/images/favicon-32x32.png',
  '/connect/assets/images/favicon.ico',
  '/connect/assets/images/apple-touch-icon.png',
  '/connect/offline.html'
];

// Install event - cache essential assets
self.addEventListener('install', event => {
  console.log('[ServiceWorker] Installing version:', CACHE_VERSION);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[ServiceWorker] Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('[ServiceWorker] Install completed');
        // Activate new service worker immediately
        return self.skipWaiting();
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[ServiceWorker] Activating version:', CACHE_VERSION);
  event.waitUntil(
    Promise.all([
      // Take control of all clients immediately
      self.clients.claim(),
      // Remove old caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME && cacheName.startsWith('healthconnect-admin-')) {
              console.log('[ServiceWorker] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
    ]).then(() => {
      console.log('[ServiceWorker] Activate completed');
    })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Cache hit - return response
        if (response) {
          return response;
        }

        return fetch(event.request)
          .then(response => {
            // Check if we received a valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Clone the response
            const responseToCache = response.clone();

            // Cache successful GET requests that aren't API calls
            if (event.request.method === 'GET' && !event.request.url.includes('/api/')) {
              caches.open(CACHE_NAME)
                .then(cache => {
                  cache.put(event.request, responseToCache);
                });
            }

            return response;
          })
          .catch(() => {
            // If both cache and network fail, show offline page for navigation requests
            if (event.request.mode === 'navigate') {
              return caches.match('/connect/offline.html');
            }
            
            // For image requests, return a placeholder
            if (event.request.destination === 'image') {
              return new Response('', {
                status: 200,
                statusText: 'OK'
              });
            }
            
            return new Response('Network error occurred', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({
                'Content-Type': 'text/plain'
              })
            });
          });
      })
  );
});

// Handle skip waiting message
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
}); 