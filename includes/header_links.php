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
    // First, unregister any existing service workers
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for(let registration of registrations) {
            registration.unregister(); // Unregister each service worker
        }
    });

    // Then register the new service worker
    window.addEventListener('load', () => {
        // Determine if we're in the admin section
        const isAdmin = window.location.pathname.includes('/connect/pages/admin/');
        
        // Set the appropriate service worker path and scope
        let swPath, swScope;
        
        if (isAdmin) {
            swPath = '/connect/pages/admin/service-worker.js';
            swScope = '/connect/pages/admin/';
        } else {
            swPath = '/connect/service-worker.js';
            swScope = '/connect/';
        }
        
        // Register the service worker with the correct path and scope
        navigator.serviceWorker.register(swPath, { scope: swScope })
            .then(registration => {
                console.log('ServiceWorker registration successful with scope:', registration.scope);
                
                // Check for updates (auto-update - no user interaction needed)
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
            })
            .catch(err => {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}
</script> 