<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the current script name and path
$current_script = basename($_SERVER['SCRIPT_NAME']);
$current_path = $_SERVER['REQUEST_URI'];

// Skip session check for login and registration pages
$public_pages = ['login.php', 'register.php', 'forgot_password.php'];

if (!in_array($current_script, $public_pages)) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: /connect/pages/login.php");
        exit;
    }

    // Define role-based directories
    $role_directories = [
        'admin' => '/connect/pages/admin/',
        'health_worker' => '/connect/pages/health_worker/',
        'patient' => '/connect/pages/patient/'
    ];

    // Check if user is accessing their correct role directory
    $user_role = $_SESSION['role'];
    $user_directory = $role_directories[$user_role] ?? '';
    
    // If user is not in their role directory, redirect them
    // But avoid infinite redirects by checking if we're already trying to go to the dashboard
    if (!empty($user_directory) && 
        !str_starts_with($current_path, $user_directory) && 
        $current_script !== 'dashboard.php') {
        header("Location: " . $user_directory . "dashboard.php");
        exit;
    }
}

// Define role-based access constants
define('ROLE_ADMIN', 'admin');
define('ROLE_HEALTH_WORKER', 'health_worker');
define('ROLE_PATIENT', 'patient');

// Function to check if user has required role
function checkRole($allowed_roles) {
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        // Redirect to appropriate dashboard based on role
        $role_dashboards = [
            'admin' => '/connect/pages/admin/dashboard.php',
            'health_worker' => '/connect/pages/health_worker/dashboard.php',
            'patient' => '/connect/pages/patient/dashboard.php'
        ];
        
        $redirect_to = isset($_SESSION['role']) && isset($role_dashboards[$_SESSION['role']]) 
            ? $role_dashboards[$_SESSION['role']] 
            : '/connect/pages/login.php';
            
        header("Location: " . $redirect_to);
        exit;
    }
}
?> 