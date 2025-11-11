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
            // First, unregister any existing service workers to avoid conflicts
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (let registration of registrations) {
                await registration.unregister();
                console.log('Unregistered existing SW:', registration.scope);
            }

            // Clear all caches to start fresh
            const cacheNames = await caches.keys();
            for (let cacheName of cacheNames) {
                await caches.delete(cacheName);
                console.log('Deleted cache:', cacheName);
            }

            // Wait a moment then register the unified service worker
            setTimeout(async () => {
                try {
                    const registration = await navigator.serviceWorker.register('/connect/service-worker.js', { 
                        scope: '/connect/' 
                    });
                    console.log('Unified ServiceWorker registered successfully with scope:', registration.scope);
                    
                    // Handle updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        console.log('Service Worker update found - auto-updating!');
                        
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Auto-update: Skip waiting and activate immediately
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                            }
                        });
                    });
                } catch (err) {
                    console.log('ServiceWorker registration failed: ', err);
                }
            }, 500);
        } catch (error) {
            console.log('Error during SW cleanup:', error);
        }
    });
}
</script> 