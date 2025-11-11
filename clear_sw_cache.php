<?php
// Clear service worker cache script
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clear Service Worker Cache</title>
</head>
<body>
    <h1>Service Worker Cache Cleaner</h1>
    <p>This page will help clear all service worker caches and re-register the unified service worker.</p>
    <button id="clearCaches">Clear All Caches & Re-register</button>
    <div id="status"></div>

    <script>
        async function clearAllCaches() {
            const status = document.getElementById('status');
            status.innerHTML = '<p>Starting cache cleanup...</p>';

            try {
                // Unregister all service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    status.innerHTML += '<p>Found ' + registrations.length + ' service worker registrations</p>';
                    
                    for (let registration of registrations) {
                        await registration.unregister();
                        status.innerHTML += '<p>Unregistered SW: ' + registration.scope + '</p>';
                    }
                }

                // Clear all caches
                const cacheNames = await caches.keys();
                status.innerHTML += '<p>Found ' + cacheNames.length + ' caches</p>';
                
                for (let cacheName of cacheNames) {
                    await caches.delete(cacheName);
                    status.innerHTML += '<p>Deleted cache: ' + cacheName + '</p>';
                }

                status.innerHTML += '<p>All caches cleared!</p>';
                
                // Wait a moment then register unified service worker
                setTimeout(async () => {
                    try {
                        const registration = await navigator.serviceWorker.register('/connect/service-worker.js', {
                            scope: '/connect/'
                        });
                        status.innerHTML += '<p>✅ Unified service worker registered successfully!</p>';
                        status.innerHTML += '<p>Scope: ' + registration.scope + '</p>';
                    } catch (error) {
                        status.innerHTML += '<p>❌ Error registering unified service worker: ' + error.message + '</p>';
                    }
                }, 1000);

            } catch (error) {
                status.innerHTML += '<p>❌ Error during cleanup: ' + error.message + '</p>';
            }
        }

        document.getElementById('clearCaches').addEventListener('click', clearAllCaches);
    </script>
</body>
</html>