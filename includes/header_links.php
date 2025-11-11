<!-- Meta Tags -->
<meta name="description" content="HealthConnect - Barangay Health Center Management System">
<meta name="keywords" content="health, medical, clinic, barangay, appointments">
<meta name="author" content="HealthConnect">
<meta name="theme-color" content="#4CAF50">

<!-- Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="/connect/assets/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/connect/assets/images/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/connect/assets/images/apple-touch-icon.png">
<link rel="mask-icon" href="/connect/assets/images/safari-pinned-tab.svg" color="#4CAF50">
<meta name="msapplication-TileColor" content="#4CAF50">
<meta name="theme-color" content="#4CAF50">

<!-- PWA Manifest -->
<?php
// Determine which manifest to use based on the current path
$current_path = $_SERVER['REQUEST_URI'];
$manifest_path = (strpos($current_path, '/connect/pages/admin/') !== false) ? 
    '/connect/pages/admin/manifest.json' : '/connect/manifest.json';
?>
<link rel="manifest" href="<?php echo $manifest_path; ?>"><?php // This dynamically selects the appropriate manifest file ?>

<!-- CSS -->
<link rel="stylesheet" href="/connect/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="/connect/assets/js/app.js" defer></script>

<!-- PWA Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            console.log('[PWA] Starting service worker initialization...');
            
            // First, unregister any existing service workers to avoid conflicts
            const registrations = await navigator.serviceWorker.getRegistrations();
            console.log(`[PWA] Found ${registrations.length} existing service worker registrations`);
            
            for (let registration of registrations) {
                // Only unregister if it's NOT the unified service worker we want
                if (!registration.scope.includes('/connect/service-worker.js')) {
                    await registration.unregister();
                    console.log('[PWA] Unregistered conflicting SW:', registration.scope);
                }
            }

            // Clear old caches to prevent conflicts
            const cacheNames = await caches.keys();
            for (let cacheName of cacheNames) {
                // Only delete old role-specific caches, keep unified cache
                if ((cacheName.includes('patient') || cacheName.includes('admin') || cacheName.includes('health-worker')) 
                    && !cacheName.includes('unified')) {
                    await caches.delete(cacheName);
                    console.log('[PWA] Deleted old cache:', cacheName);
                }
            }

            // Wait a moment then register the unified service worker
            setTimeout(async () => {
                try {
                    const registration = await navigator.serviceWorker.register('/connect/service-worker.js', { 
                        scope: '/connect/',
                        updateViaCache: 'none' // Always check for updates
                    });
                    console.log('[PWA] Unified ServiceWorker registered successfully with scope:', registration.scope);
                    
                    // Handle updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        console.log('[PWA] Service Worker update found - auto-updating!');
                        
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Auto-update: Skip waiting and activate immediately
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                            }
                        });
                    });
                } catch (err) {
                    console.error('[PWA] ServiceWorker registration failed:', err);
                }
            }, 1000); // Increased delay to ensure cleanup completes
        } catch (error) {
            console.error('[PWA] Error during SW cleanup:', error);
        }
    });
}
</script> 