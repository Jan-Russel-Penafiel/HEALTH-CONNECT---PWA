# Service Worker Migration Guide

## Overview
All role-specific service workers have been consolidated into a single unified service worker located at:
**`/connect/service-worker.js`**

## What Changed

### Before (v1.x)
- **Root Service Worker**: `/connect/service-worker.js` (v1.0.5)
- **Admin Service Worker**: `/connect/pages/admin/service-worker.js` (v1.0.7)
- **Health Worker Service Worker**: `/connect/pages/health_worker/service-worker.js` (v1.0.5)
- **Patient Service Worker**: `/connect/pages/patient/service-worker.js` (v1.0.5)

### After (v2.0.0)
- **Unified Service Worker**: `/connect/service-worker.js` (v2.0.0)
- All role-specific service workers now redirect to the unified version

## Key Features of Unified Service Worker

### 1. **Comprehensive Caching**
- Caches all pages for Admin, Health Worker, and Patient roles
- Shared assets cached once for all roles
- Reduces redundancy and improves performance

### 2. **Intelligent Fetch Strategy**
- **Dynamic Content (PHP files, role pages)**: Network-first with cache fallback
- **Static Assets (CSS, JS, images)**: Cache-first with background updates
- Ensures fresh content while maintaining offline capability

### 3. **Advanced Update Mechanism**
- Automatic update checks every 60 seconds
- Cache busting with version and timestamp
- Seamless activation without user intervention
- Background sync support

### 4. **Cache Management**
- Automatic cleanup of old role-specific caches
- Unified cache name: `healthconnect-unified-v2.0.0`
- Removes duplicate caching across roles

### 5. **Enhanced Message Handling**
- `SKIP_WAITING`: Immediate activation of new service worker
- `CHECK_UPDATE`: Manual update trigger
- `CLEAR_CACHE`: Complete cache clearing
- `SW_UPDATED`: Notification to clients about updates

### 6. **Offline Support**
- Offline page fallback for navigation requests
- Graceful handling of network failures
- Image placeholder for failed image requests

## Benefits

1. **Single Source of Truth**: One service worker manages all roles
2. **Reduced Maintenance**: Update once, applies to all users
3. **Better Performance**: Shared cache reduces storage and network usage
4. **Consistent Behavior**: Same caching and update strategy across all roles
5. **Easier Debugging**: Single file to monitor and troubleshoot

## Migration Steps

### For Developers
1. All pages should reference the root service worker:
   ```javascript
   if ('serviceWorker' in navigator) {
     navigator.serviceWorker.register('/connect/service-worker.js')
       .then(reg => console.log('Service Worker registered:', reg))
       .catch(err => console.error('Service Worker registration failed:', err));
   }
   ```

2. Remove old service worker registrations from role-specific pages

3. The unified service worker will automatically:
   - Clean up old role-specific caches
   - Take control of all pages
   - Notify clients of the update

### For Users
- No action required!
- The update happens automatically
- Old caches are cleaned up automatically
- All functionality remains the same

## Cached Resources

### Root Level
- index.php, manifest.json, offline.html

### Admin Pages
- dashboard.php, patients.php, health_workers.php, reports.php, settings.php, profile.php, change_password.php

### Health Worker Pages
- dashboard.php, appointments.php, patients.php, immunization.php, profile.php, done_appointments.php, change_password.php, medical_history.php

### Patient Pages
- dashboard.php, appointments.php, medical_history.php, immunization.php, profile.php

### Shared Assets
- CSS: style.css
- JS: app.js
- Images: All icons, favicon, logos

## Technical Details

### Cache Version: 2.0.0
- Cache Name: `healthconnect-unified-v2.0.0`
- Update Interval: 60 seconds
- Initial Update Check: 3 seconds after activation

### Fetch Strategies
```javascript
// PHP/Dynamic Content
Network First → Cache Fallback → Offline Page

// Static Assets
Cache First → Background Update → Network Fallback
```

### Event Listeners
- `install`: Cache resources with cache busting
- `activate`: Clean old caches, claim clients, start update checks
- `fetch`: Intelligent routing based on request type
- `message`: Handle commands (SKIP_WAITING, CHECK_UPDATE, CLEAR_CACHE)
- `sync`: Background sync for updates

## Troubleshooting

### Cache Not Updating
1. Check browser console for service worker logs
2. Manually clear cache: Send `CLEAR_CACHE` message to service worker
3. Unregister and re-register service worker

### Offline Mode Not Working
1. Ensure offline.html exists at `/connect/offline.html`
2. Check network tab for failed requests
3. Verify service worker is active in DevTools

### Old Caches Not Deleted
- The unified service worker automatically deletes caches matching:
  - `healthconnect-*`
  - Contains "admin", "health-worker", or "patient"
- This happens during the `activate` event

## Version History

### v2.0.0 (Current)
- ✅ Unified all role-specific service workers
- ✅ Comprehensive caching for all user roles
- ✅ Network-first strategy for dynamic content
- ✅ Cache-first strategy for static assets
- ✅ Automatic cache cleanup
- ✅ Enhanced message handling
- ✅ Background sync support

### v1.0.7 (Admin - Deprecated)
- Network-first strategy
- Frequent update checks (10s)

### v1.0.5 (Root/Health Worker/Patient - Deprecated)
- Basic caching
- Auto-update every 60s

## Support

For issues or questions about the unified service worker, please:
1. Check browser console for detailed logs
2. Review the service worker code in `/connect/service-worker.js`
3. Test in incognito mode to rule out cache issues
4. Use Chrome DevTools > Application > Service Workers for debugging

---

**Last Updated**: October 5, 2025
**Version**: 2.0.0
**Status**: Production Ready ✅
