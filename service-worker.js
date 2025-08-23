// Service Worker for HealthConnect PWA
const CACHE_VERSION = '1.0.5'; // Updated to clear installation prompts
const CACHE_NAME = `healthconnect-v${CACHE_VERSION}`;
const urlsToCache = [
  'https://aphid-major-dolphin.ngrok-free.app/connect/index.php',
  'https://aphid-major-dolphin.ngrok-free.app/connect/manifest.json',
  'https://aphid-major-dolphin.ngrok-free.app/connect/offline.html',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/css/style.css',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/js/app.js',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/icon-192x192.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/icon-512x512.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/favicon-16x16.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/favicon-32x32.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/favicon.ico',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/apple-touch-icon.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/health-center.jpg'
];

// Auto-update check interval - more frequent for faster updates
const CHECK_INTERVAL = 60 * 1000; // 1 minute in milliseconds

// Install event - cache essential assets and skip waiting for automatic activation
self.addEventListener('install', event => {
  console.log('[ServiceWorker] Installing version:', CACHE_VERSION);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[ServiceWorker] Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('[ServiceWorker] Install completed - Auto activating');
        // Automatically skip waiting for immediate activation
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('[ServiceWorker] Install failed:', error);
      })
  );
});

// Activate event - clean up old caches and take control immediately
self.addEventListener('activate', event => {
  console.log('[ServiceWorker] Activating version:', CACHE_VERSION);
  event.waitUntil(
    Promise.all([
      // Take control of all clients immediately for auto-update
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
      }),
      // Notify all clients about the automatic update (silently)
      self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'SW_UPDATED',
            version: CACHE_VERSION,
            message: 'App updated automatically to the latest version'
          });
        });
      })
    ]).then(() => {
      console.log('[ServiceWorker] Activate completed - Auto update successful');
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
              return response || caches.match('https://aphid-major-dolphin.ngrok-free.app/connect/offline.html');
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
              return caches.match('https://aphid-major-dolphin.ngrok-free.app/connect/offline.html');
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

// Enhanced auto-update mechanism with automatic activation
self.addEventListener('message', event => {
  if (event.data) {
    // Automatically handle skip waiting for seamless updates
    if (event.data.type === 'SKIP_WAITING') {
      console.log('[ServiceWorker] Auto skip waiting for seamless update');
      self.skipWaiting();
    }
    
    // Handle manual update check (kept for debugging)
    if (event.data.type === 'CHECK_UPDATE') {
      console.log('[ServiceWorker] Manual update check requested');
      self.registration.update()
        .then(() => {
          console.log('[ServiceWorker] Manual update check completed');
          event.source.postMessage({
            type: 'UPDATE_CHECK_COMPLETED'
          });
        })
        .catch(error => {
          console.error('[ServiceWorker] Manual update check failed:', error);
        });
    }
  }
});

// Automatic update function with error handling
const performAutoUpdate = async () => {
  try {
    console.log('[ServiceWorker] Performing automatic update check...');
    await self.registration.update();
    console.log('[ServiceWorker] Auto update check completed successfully');
  } catch (error) {
    console.error('[ServiceWorker] Auto update check failed:', error);
    // Retry after 5 minutes on failure
    setTimeout(performAutoUpdate, 5 * 60 * 1000);
  }
};

// Start automatic updates immediately after service worker is ready
setTimeout(performAutoUpdate, 3000); // Initial check after 3 seconds

// Set up continuous automatic update checks
setInterval(performAutoUpdate, CHECK_INTERVAL); 