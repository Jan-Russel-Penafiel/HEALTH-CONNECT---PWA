<?php
// Clear all session data and provide instructions to clear browser cache
session_start();
session_destroy();
session_unset();

// Clear any cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time() - 1000);
        setcookie($name, '', time() - 1000, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache & Session Cleared - HealthConnect</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            text-align: center;
        }
        .icon {
            font-size: 4em;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .instructions {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .step {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-size: 1.1em;
            margin: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #388E3C;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Cache & Session Cleared Successfully</h1>
        <p>Server-side session data has been cleared. Please follow the instructions below to complete the cache clearing process:</p>
        
        <div class="warning">
            <strong>Important:</strong> You need to manually clear your browser cache and data for the best results.
        </div>
        
        <div class="instructions">
            <h3>Manual Cache Clearing Instructions:</h3>
            
            <div class="step">
                <strong>Chrome/Edge:</strong> Press <code>Ctrl+Shift+Delete</code>, select "All time", check "Cookies and site data" and "Cached images and files", then click "Clear data"
            </div>
            
            <div class="step">
                <strong>Firefox:</strong> Press <code>Ctrl+Shift+Delete</code>, select "Everything", check "Cookies" and "Cache", then click "Clear Now"
            </div>
            
            <div class="step">
                <strong>Safari:</strong> Go to Safari → Clear History → All History → Clear History
            </div>
            
            <div class="step">
                <strong>Or try:</strong> Open Developer Tools (F12), right-click refresh button, select "Empty Cache and Hard Reload"
            </div>
        </div>
        
        <div style="margin-top: 30px;">
            <button onclick="clearBrowserCache()" class="btn">Clear Browser Cache (Auto)</button>
            <a href="/connect/pages/login.php" class="btn">Go to Login</a>
        </div>
    </div>

    <script>
        async function clearBrowserCache() {
            try {
                // Unregister all service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    for (let registration of registrations) {
                        await registration.unregister();
                        console.log('Unregistered SW:', registration.scope);
                    }
                }

                // Clear all caches
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    for (let cacheName of cacheNames) {
                        await caches.delete(cacheName);
                        console.log('Deleted cache:', cacheName);
                    }
                }

                // Clear localStorage and sessionStorage
                if (typeof(Storage) !== "undefined") {
                    localStorage.clear();
                    sessionStorage.clear();
                }

                alert('Browser cache cleared successfully! Please refresh the page or click "Go to Login".');
            } catch (error) {
                console.error('Error clearing cache:', error);
                alert('Some cache clearing operations failed. Please manually clear your browser cache using the instructions above.');
            }
        }

        // Auto-clear on page load
        window.addEventListener('load', () => {
            setTimeout(() => {
                clearBrowserCache();
            }, 1000);
        });
    </script>
</body>
</html>