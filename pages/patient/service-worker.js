// Service Worker for HealthConnect Patient PWA
const CACHE_VERSION = '1.0.5'; // Updated to clear installation prompts
const CACHE_NAME = `healthconnect-patient-v${CACHE_VERSION}`;
const urlsToCache = [
  '/connect/pages/patient/dashboard.php',
  '/connect/pages/patient/appointments.php',
  '/connect/pages/patient/medical_history.php',
  '/connect/pages/patient/immunization.php',
  '/connect/pages/patient/profile.php',
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

// Auto-update check interval
const CHECK_INTERVAL = 60 * 1000; // 1 minute

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
            if (cacheName !== CACHE_NAME && cacheName.startsWith('healthconnect-patient-')) {
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
            message: 'Patient app updated automatically to the latest version'
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

// Handle automatic updates and skip waiting
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('[ServiceWorker] Auto skip waiting for seamless update');
    self.skipWaiting();
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