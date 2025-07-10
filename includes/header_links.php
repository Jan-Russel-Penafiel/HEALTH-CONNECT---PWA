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
<link rel="manifest" href="/connect/manifest.json">

<!-- CSS -->
<link rel="stylesheet" href="/connect/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        navigator.serviceWorker.register('/connect/service-worker.js')
            .then(registration => {
                console.log('ServiceWorker registration successful');
                
                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    console.log('Service Worker update found!');
                    
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New service worker is installed but waiting
                            if (confirm('New version available! Click OK to update.')) {
                                window.location.reload();
                            }
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