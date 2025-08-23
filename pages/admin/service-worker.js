// Service Worker for HealthConnect Admin PWA
const CACHE_VERSION = '1.0.7'; // Updated to clear installation prompts - Force refresh with network-first strategy
const CACHE_NAME = `healthconnect-admin-v${CACHE_VERSION}`;
const UPDATE_CHECK_INTERVAL = 10 * 1000; // Check for updates every 10 seconds for development
const FORCE_UPDATE_TIMESTAMP = Date.now(); // Unique timestamp for cache busting
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

// Install event - force immediate activation for development
self.addEventListener('install', event => {
  console.log('[ServiceWorker] Installing version:', CACHE_VERSION, 'at:', new Date().toISOString());
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[ServiceWorker] Caching app shell with cache busting');
        // Add cache busting parameters to ensure fresh content
        const cacheBustingUrls = urlsToCache.map(url => `${url}?v=${CACHE_VERSION}&t=${FORCE_UPDATE_TIMESTAMP}`);
        return cache.addAll(cacheBustingUrls);
      })
      .then(() => {
        console.log('[ServiceWorker] Install completed - forcing immediate activation');
        // Force immediate activation and skip waiting
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('[ServiceWorker] Install failed:', error);
      })
  );
});

// Activate event - force immediate control and cache cleanup
self.addEventListener('activate', event => {
  console.log('[ServiceWorker] Activating version:', CACHE_VERSION, 'at:', new Date().toISOString());
  event.waitUntil(
    Promise.all([
      // Delete ALL old caches to force fresh content
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('[ServiceWorker] Deleting cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
    ]).then(() => {
      console.log('[ServiceWorker] All old caches deleted');
      // Take immediate control of all clients
      return self.clients.claim();
    }).then(() => {
      console.log('[ServiceWorker] Clients claimed - app updated automatically');
      // Auto-update happens silently without user notification
      return self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'SW_UPDATED',
            version: CACHE_VERSION,
            timestamp: new Date().toISOString()
          });
        });
      });
    }).then(() => {
      console.log('[ServiceWorker] Activation completed');
      startPeriodicUpdateCheck();
    }).catch(error => {
      console.error('[ServiceWorker] Activation error:', error);
    })
  );
});

// Periodic update check function (with better error handling)
function startPeriodicUpdateCheck() {
  // Only start if we're the active service worker
  if (self.registration && self.registration.active === self) {
    setInterval(() => {
      console.log('[ServiceWorker] Checking for updates...');
      self.registration.update().then(() => {
        console.log('[ServiceWorker] Periodic update check completed');
      }).catch(error => {
        console.log('[ServiceWorker] Update check failed:', error);
      });
    }, UPDATE_CHECK_INTERVAL);
    console.log('[ServiceWorker] Periodic update checks started - checking every', UPDATE_CHECK_INTERVAL / 1000, 'seconds');
  } else {
    console.log('[ServiceWorker] Not active, skipping periodic updates');
  }
}

// Fetch event - network-first strategy for dynamic content, cache for assets
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // For PHP files and dynamic content - always fetch from network first
  if (url.pathname.endsWith('.php') || url.pathname.includes('/pages/') || url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          console.log('[ServiceWorker] Network fetch successful for:', url.pathname);
          // Clone and cache the fresh response
          if (response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        })
        .catch(error => {
          console.log('[ServiceWorker] Network failed for:', url.pathname, 'trying cache');
          // Fallback to cache only if network fails
          return caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // If both fail and it's a navigation request, show offline page
            if (event.request.mode === 'navigate') {
              return caches.match('/connect/offline.html');
            }
            throw error;
          });
        })
    );
  } else {
    // For static assets - cache first but validate freshness
    event.respondWith(
      caches.match(event.request)
        .then(cachedResponse => {
          // Always fetch from network to check for updates
          const fetchPromise = fetch(event.request)
            .then(networkResponse => {
              if (networkResponse.status === 200) {
                // Update cache with fresh content
                const responseToCache = networkResponse.clone();
                caches.open(CACHE_NAME).then(cache => {
                  cache.put(event.request, responseToCache);
                });
              }
              return networkResponse;
            })
            .catch(() => {
              // If network fails, return cached version
              return cachedResponse;
            });
          
          // Return cached version immediately if available, otherwise wait for network
          return cachedResponse || fetchPromise;
        })
    );
  }
});

// Handle skip waiting message and update notifications
self.addEventListener('message', event => {
  console.log('[ServiceWorker] Received message:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('[ServiceWorker] Skip waiting requested');
    self.skipWaiting();
  } else if (event.data && event.data.type === 'CHECK_UPDATE') {
    // Manual update check triggered by client
    if (self.registration) {
      self.registration.update().then(() => {
        console.log('[ServiceWorker] Manual update check completed');
      }).catch(error => {
        console.log('[ServiceWorker] Manual update check failed:', error);
      });
    }
  }
});

// Background sync for automatic updates (when available)
self.addEventListener('sync', event => {
  if (event.tag === 'sw-update-check') {
    event.waitUntil(
      self.registration.update().then(() => {
        console.log('[ServiceWorker] Background sync update check completed');
      }).catch(error => {
        console.log('[ServiceWorker] Background sync update failed:', error);
      })
    );
  }
}); 