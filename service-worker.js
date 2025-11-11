// Unified Service Worker for HealthConnect PWA (All Roles)
// Supports Admin, Health Worker, Patient, and General users
const CACHE_VERSION = '2.0.0'; // Unified version for all roles
const CACHE_NAME = `healthconnect-unified-v${CACHE_VERSION}`;
const UPDATE_CHECK_INTERVAL = 60 * 1000; // Check for updates every 60 seconds
const FORCE_UPDATE_TIMESTAMP = Date.now(); // Unique timestamp for cache busting

// Comprehensive URLs to cache for all user roles
const urlsToCache = [
  // Root pages
  'https://aphid-major-dolphin.ngrok-free.app/connect/index.php',
  'https://aphid-major-dolphin.ngrok-free.app/connect/manifest.json',
  'https://aphid-major-dolphin.ngrok-free.app/connect/offline.html',
  
  // Admin pages
  '/connect/pages/admin/dashboard.php',
  '/connect/pages/admin/patients.php',
  '/connect/pages/admin/health_workers.php',
  '/connect/pages/admin/reports.php',
  '/connect/pages/admin/settings.php',
  '/connect/pages/admin/profile.php',
  '/connect/pages/admin/change_password.php',
  
  // Health Worker pages
  '/connect/pages/health_worker/dashboard.php',
  '/connect/pages/health_worker/appointments.php',
  '/connect/pages/health_worker/patients.php',
  '/connect/pages/health_worker/immunization.php',
  '/connect/pages/health_worker/profile.php',
  '/connect/pages/health_worker/done_appointments.php',
  '/connect/pages/health_worker/change_password.php',
  '/connect/pages/health_worker/medical_history.php',
  
  // Patient pages
  '/connect/pages/patient/dashboard.php',
  '/connect/pages/patient/appointments.php',
  '/connect/pages/patient/medical_history.php',
  '/connect/pages/patient/immunization.php',
  '/connect/pages/patient/profile.php',
  
  // Shared assets
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/css/style.css',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/js/app.js',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/icon-192x192.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/icon-512x512.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/favicon-16x16.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/favicon-32x32.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/favicon.ico',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/apple-touch-icon.png',
  'https://aphid-major-dolphin.ngrok-free.app/connect/assets/images/health-center.jpg',
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

// Install event - cache essential assets with cache busting and force immediate activation
self.addEventListener('install', event => {
  console.log('[ServiceWorker] Installing unified version:', CACHE_VERSION, 'at:', new Date().toISOString());
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[ServiceWorker] Caching app shell for all user roles with cache busting');
        // Add cache busting parameters to ensure fresh content
        const cacheBustingUrls = urlsToCache.map(url => {
          // Only add cache busting to relative URLs
          if (url.startsWith('/')) {
            return `${url}?v=${CACHE_VERSION}&t=${FORCE_UPDATE_TIMESTAMP}`;
          }
          return url;
        });
        return cache.addAll(cacheBustingUrls).catch(error => {
          console.warn('[ServiceWorker] Some resources failed to cache:', error);
          // Continue anyway - partial cache is better than no cache
          return Promise.resolve();
        });
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

// Activate event - force immediate control, clean up ALL old caches (including role-specific ones)
self.addEventListener('activate', event => {
  console.log('[ServiceWorker] Activating unified version:', CACHE_VERSION, 'at:', new Date().toISOString());
  event.waitUntil(
    Promise.all([
      // Delete ALL old caches including role-specific caches to force fresh unified cache
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            // Delete all healthconnect caches except the current unified one
            if (cacheName !== CACHE_NAME && 
                (cacheName.startsWith('healthconnect-') || 
                 cacheName.includes('admin') || 
                 cacheName.includes('health-worker') || 
                 cacheName.includes('patient'))) {
              console.log('[ServiceWorker] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
    ]).then(() => {
      console.log('[ServiceWorker] All old role-specific caches deleted');
      // Take immediate control of all clients
      return self.clients.claim();
    }).then(() => {
      console.log('[ServiceWorker] Clients claimed - unified app updated automatically');
      // Notify all clients about the automatic update
      return self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'SW_UPDATED',
            version: CACHE_VERSION,
            timestamp: new Date().toISOString(),
            message: 'HealthConnect unified PWA updated automatically to the latest version'
          });
        });
      });
    }).then(() => {
      console.log('[ServiceWorker] Activation completed - starting periodic update checks');
      startPeriodicUpdateCheck();
    }).catch(error => {
      console.error('[ServiceWorker] Activation error:', error);
    })
  );
});

// Periodic update check function with better error handling
function startPeriodicUpdateCheck() {
  // Only start if we're the active service worker
  if (self.registration && self.registration.active === self) {
    setInterval(() => {
      console.log('[ServiceWorker] Performing periodic update check...');
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

// Fetch event - intelligent network-first strategy for dynamic content, cache-first for assets
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // Skip caching for non-GET requests
  if (event.request.method !== 'GET') {
    event.respondWith(fetch(event.request));
    return;
  }
  
  // For PHP files and dynamic content (all role pages) - always fetch from network first
  if (url.pathname.endsWith('.php') || 
      url.pathname.includes('/pages/admin/') || 
      url.pathname.includes('/pages/health_worker/') || 
      url.pathname.includes('/pages/patient/') ||
      url.pathname.includes('/api/') ||
      event.request.mode === 'navigate') {
    
    event.respondWith(
      fetch(event.request)
        .then(response => {
          console.log('[ServiceWorker] Network fetch successful for:', url.pathname);
          // Clone and cache the fresh response for offline fallback
          if (response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        })
        .catch(error => {
          console.log('[ServiceWorker] Network failed for:', url.pathname, '- trying cache');
          // Fallback to cache only if network fails
          return caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
              console.log('[ServiceWorker] Serving from cache:', url.pathname);
              return cachedResponse;
            }
            // If both fail and it's a navigation request, show offline page
            if (event.request.mode === 'navigate') {
              return caches.match('https://aphid-major-dolphin.ngrok-free.app/connect/offline.html')
                .then(offlinePage => offlinePage || caches.match('/connect/offline.html'));
            }
            throw error;
          });
        })
    );
  } else {
    // For static assets (CSS, JS, images) - cache first with background update
    event.respondWith(
      caches.match(event.request)
        .then(cachedResponse => {
          // Always fetch from network in background to check for updates
          const fetchPromise = fetch(event.request)
            .then(networkResponse => {
              if (networkResponse && networkResponse.status === 200) {
                // Update cache with fresh content
                const responseToCache = networkResponse.clone();
                caches.open(CACHE_NAME).then(cache => {
                  cache.put(event.request, responseToCache);
                });
              }
              return networkResponse;
            })
            .catch(() => {
              // If network fails, return cached version (already handled below)
              return cachedResponse;
            });
          
          // Return cached version immediately if available, otherwise wait for network
          return cachedResponse || fetchPromise;
        })
        .catch(() => {
          // Fallback for image requests
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
        })
    );
  }
});

// Enhanced message handling for automatic updates and skip waiting
self.addEventListener('message', event => {
  console.log('[ServiceWorker] Received message:', event.data);
  
  if (event.data) {
    // Automatically handle skip waiting for seamless updates
    if (event.data.type === 'SKIP_WAITING') {
      console.log('[ServiceWorker] Skip waiting requested - activating immediately');
      self.skipWaiting();
    }
    
    // Handle manual update check
    if (event.data.type === 'CHECK_UPDATE') {
      console.log('[ServiceWorker] Manual update check requested');
      if (self.registration) {
        self.registration.update()
          .then(() => {
            console.log('[ServiceWorker] Manual update check completed');
            if (event.source) {
              event.source.postMessage({
                type: 'UPDATE_CHECK_COMPLETED',
                version: CACHE_VERSION
              });
            }
          })
          .catch(error => {
            console.error('[ServiceWorker] Manual update check failed:', error);
          });
      }
    }
    
    // Handle cache clear request
    if (event.data.type === 'CLEAR_CACHE') {
      console.log('[ServiceWorker] Cache clear requested');
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            console.log('[ServiceWorker] Clearing cache:', cacheName);
            return caches.delete(cacheName);
          })
        );
      }).then(() => {
        console.log('[ServiceWorker] All caches cleared');
        if (event.source) {
          event.source.postMessage({
            type: 'CACHE_CLEARED'
          });
        }
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

// Automatic update function with retry logic and error handling
const performAutoUpdate = async () => {
  try {
    console.log('[ServiceWorker] Performing automatic update check...');
    if (self.registration) {
      await self.registration.update();
      console.log('[ServiceWorker] Auto update check completed successfully');
    }
  } catch (error) {
    console.error('[ServiceWorker] Auto update check failed:', error);
    // Retry after 5 minutes on failure
    setTimeout(performAutoUpdate, 5 * 60 * 1000);
  }
};

// Start automatic updates immediately after service worker is ready
setTimeout(performAutoUpdate, 3000); // Initial check after 3 seconds

// Note: Continuous update checks are now handled by startPeriodicUpdateCheck() in activate event 