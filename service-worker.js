// Service Worker for HealthConnect PWA
const CACHE_VERSION = '1.0.2';
const CACHE_NAME = `healthconnect-v${CACHE_VERSION}`;
const urlsToCache = [
  './index.php',
  './manifest.json',
  './offline.html',
  './assets/css/style.css',
  './assets/js/app.js',
  './assets/images/icon-192x192.png',
  './assets/images/icon-512x512.png',
  './assets/images/favicon-16x16.png',
  './assets/images/favicon-32x32.png',
  './assets/images/apple-touch-icon.png',
  './assets/images/health-center.jpg'
];

// Check for service worker updates more frequently
const CHECK_INTERVAL = 15 * 60 * 1000; // 15 minutes in milliseconds

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
            if (cacheName !== CACHE_NAME && cacheName.startsWith('healthconnect-')) {
              console.log('[ServiceWorker] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
    ]).then(() => {
      console.log('[ServiceWorker] Activate completed');
      // Notify all clients about the update
      self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'UPDATE_AVAILABLE',
            version: CACHE_VERSION
          });
        });
      });
    })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // For HTML requests, try network first to ensure fresh content
        if (event.request.mode === 'navigate' || 
            (event.request.method === 'GET' && 
             event.request.headers.get('accept').includes('text/html'))) {
          return fetch(event.request)
            .then(networkResponse => {
              // Cache the new version
              const responseToCache = networkResponse.clone();
              caches.open(CACHE_NAME).then(cache => {
                cache.put(event.request, responseToCache);
              });
              return networkResponse;
            })
            .catch(() => {
              // Fallback to cache if network fails
              return response || caches.match('./offline.html');
            });
        }

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
              return caches.match('./offline.html');
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

// Enhanced auto-update mechanism
self.addEventListener('message', event => {
  if (event.data) {
    // Handle manual update check
    if (event.data.type === 'CHECK_UPDATE') {
      console.log('[ServiceWorker] Manually checking for updates...');
      self.registration.update()
        .then(() => {
          console.log('[ServiceWorker] Update check completed');
          // Notify the client that the check was performed
          event.source.postMessage({
            type: 'UPDATE_CHECK_COMPLETED'
          });
        })
        .catch(error => {
          console.error('[ServiceWorker] Update check failed:', error);
        });
    }
    
    // Handle reload request
    if (event.data.type === 'SKIP_WAITING') {
      console.log('[ServiceWorker] Skip waiting requested');
      self.skipWaiting();
    }
  }
});

// Function to perform the update check
const checkForUpdates = async () => {
  try {
    console.log('[ServiceWorker] Checking for updates...');
    await self.registration.update();
    console.log('[ServiceWorker] Update check completed');
  } catch (error) {
    console.error('[ServiceWorker] Update check failed:', error);
  }
};

// Initial update check
setTimeout(checkForUpdates, 5000);

// Set up periodic update checks
setInterval(checkForUpdates, CHECK_INTERVAL); 