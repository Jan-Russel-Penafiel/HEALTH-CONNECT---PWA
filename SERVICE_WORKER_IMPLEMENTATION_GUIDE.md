# Service Worker Implementation Guide

## Quick Start

### Register the Unified Service Worker

All pages (admin, health worker, patient, and public) should now register the unified service worker:

```javascript
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/connect/service-worker.js')
      .then(registration => {
        console.log('✅ Service Worker registered successfully:', registration.scope);
        
        // Check for updates every time page loads
        registration.update();
        
        // Listen for updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'activated') {
              console.log('🔄 New version available - app updated!');
            }
          });
        });
      })
      .catch(error => {
        console.error('❌ Service Worker registration failed:', error);
      });
  });
}
```

## Important Changes

### ⚠️ Update All Service Worker Registrations

**Before:**
```javascript
// OLD - Role-specific registration (DEPRECATED)
navigator.serviceWorker.register('/connect/pages/admin/service-worker.js');
navigator.serviceWorker.register('/connect/pages/health_worker/service-worker.js');
navigator.serviceWorker.register('/connect/pages/patient/service-worker.js');
```

**After:**
```javascript
// NEW - Unified registration (CURRENT)
navigator.serviceWorker.register('/connect/service-worker.js');
```

### Files to Update

1. **Admin Pages**: `/connect/pages/admin/*.php`
2. **Health Worker Pages**: `/connect/pages/health_worker/*.php`
3. **Patient Pages**: `/connect/pages/patient/*.php`
4. **Public Pages**: `/connect/index.php`, etc.

## Listening to Service Worker Events

### Receive Update Notifications

```javascript
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', event => {
    if (event.data.type === 'SW_UPDATED') {
      console.log('App updated to version:', event.data.version);
      console.log('Message:', event.data.message);
      
      // Optional: Show a notification to user
      showNotification('App Updated', event.data.message);
    }
    
    if (event.data.type === 'SW_DEPRECATED') {
      console.warn('Old service worker detected - please refresh');
      // Auto-refresh to use new service worker
      window.location.reload();
    }
  });
}
```

### Manual Update Check

```javascript
function checkForUpdates() {
  if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
    navigator.serviceWorker.controller.postMessage({
      type: 'CHECK_UPDATE'
    });
  }
}

// Listen for update completion
navigator.serviceWorker.addEventListener('message', event => {
  if (event.data.type === 'UPDATE_CHECK_COMPLETED') {
    console.log('Update check completed. Version:', event.data.version);
  }
});
```

### Clear Cache Manually

```javascript
function clearAppCache() {
  if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
    navigator.serviceWorker.controller.postMessage({
      type: 'CLEAR_CACHE'
    });
    
    // Listen for confirmation
    navigator.serviceWorker.addEventListener('message', event => {
      if (event.data.type === 'CACHE_CLEARED') {
        console.log('Cache cleared successfully');
        window.location.reload();
      }
    });
  }
}
```

## Testing the Service Worker

### Chrome DevTools

1. Open DevTools (F12)
2. Go to **Application** tab
3. Click **Service Workers** in left sidebar
4. You should see: `/connect/service-worker.js`
5. Check that status is **activated and is running**

### Verify Cache

1. In DevTools > Application > Cache Storage
2. You should see: `healthconnect-unified-v2.0.0`
3. Expand to view cached resources

### Test Offline Mode

1. In DevTools > Network tab
2. Select **Offline** from dropdown
3. Refresh the page
4. App should still load from cache
5. Navigation to unavailable pages should show offline page

### Force Update

1. In DevTools > Application > Service Workers
2. Check "Update on reload"
3. Click "Update" button
4. Refresh the page

### Clear Service Worker

```javascript
// Run in browser console
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(registration => {
    registration.unregister();
    console.log('Unregistered:', registration.scope);
  });
});
```

## Automatic Features

### ✅ What Happens Automatically

1. **Cache Management**: Old role-specific caches are automatically deleted
2. **Updates**: Checks for updates every 60 seconds
3. **Activation**: New versions activate immediately without user action
4. **Offline Support**: Pages are served from cache when offline
5. **Background Updates**: Static assets update in the background

### 🔄 Update Flow

```
1. New service-worker.js deployed
   ↓
2. Browser detects new version (within 60 seconds)
   ↓
3. New service worker installs in background
   ↓
4. New version activates automatically (skipWaiting)
   ↓
5. Old caches deleted automatically
   ↓
6. Clients notified via postMessage (SW_UPDATED)
   ↓
7. App updated without user intervention
```

## Troubleshooting

### Service Worker Not Updating

**Problem**: Old service worker still active after deployment

**Solutions**:
1. Hard refresh: `Ctrl + Shift + R` (Windows) or `Cmd + Shift + R` (Mac)
2. Clear browser cache and cookies
3. In DevTools: Click "Unregister" then refresh
4. Close all tabs and reopen

### Cache Not Clearing

**Problem**: Old content still showing

**Solutions**:
1. Send `CLEAR_CACHE` message to service worker
2. Increment `CACHE_VERSION` in service-worker.js
3. Manual clear in DevTools > Application > Clear storage

### Offline Page Not Showing

**Problem**: Blank page when offline

**Solutions**:
1. Verify `/connect/offline.html` exists
2. Check console for fetch errors
3. Ensure offline.html is in cache (check Cache Storage)

### Multiple Service Workers Registered

**Problem**: Multiple service workers showing in DevTools

**Solutions**:
```javascript
// Unregister all and start fresh
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(reg => reg.unregister());
});
// Then refresh page
```

## Best Practices

### 1. Always Register on Page Load
```javascript
window.addEventListener('load', () => {
  // Register service worker here
});
```

### 2. Handle Update Notifications
```javascript
// Inform users when app updates
navigator.serviceWorker.addEventListener('message', event => {
  if (event.data.type === 'SW_UPDATED') {
    showToast('App updated to ' + event.data.version);
  }
});
```

### 3. Test Offline Functionality
- Test all critical user flows in offline mode
- Ensure forms have proper offline handling
- Show appropriate messages when offline

### 4. Monitor Service Worker Logs
```javascript
// Check console for service worker activity
// Look for: [ServiceWorker] messages
```

### 5. Version Incrementing
When making changes to service-worker.js:
```javascript
const CACHE_VERSION = '2.0.1'; // Increment this
```

## Production Deployment Checklist

- [ ] Update `CACHE_VERSION` in service-worker.js
- [ ] Remove all old service worker registrations from HTML/PHP files
- [ ] Test service worker registration in all roles (admin, health worker, patient)
- [ ] Test offline functionality
- [ ] Test update mechanism
- [ ] Verify cache clearing works
- [ ] Test on multiple browsers (Chrome, Firefox, Safari, Edge)
- [ ] Test on mobile devices
- [ ] Monitor browser console for errors
- [ ] Document version changes in changelog

## Browser Support

| Browser | Version | Support |
|---------|---------|---------|
| Chrome | 40+ | ✅ Full |
| Firefox | 44+ | ✅ Full |
| Safari | 11.1+ | ✅ Full |
| Edge | 17+ | ✅ Full |
| Opera | 27+ | ✅ Full |
| iOS Safari | 11.3+ | ✅ Full |
| Android Chrome | 40+ | ✅ Full |

## Additional Resources

- [MDN Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Google Service Worker Guide](https://developers.google.com/web/fundamentals/primers/service-workers)
- [Service Worker Cookbook](https://serviceworke.rs/)

## Support

For issues with the unified service worker:
1. Check browser console for `[ServiceWorker]` logs
2. Review `SERVICE_WORKER_MIGRATION.md` for migration details
3. Test in incognito mode to rule out cache issues
4. Use Chrome DevTools > Application > Service Workers for debugging

---

**Current Version**: 2.0.0  
**Last Updated**: October 5, 2025  
**Status**: Production Ready ✅
