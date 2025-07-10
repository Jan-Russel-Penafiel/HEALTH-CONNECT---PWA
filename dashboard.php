<?php
session_start();
require_once 'includes/auth_check.php';

// Redirect based on user role
switch ($_SESSION['role']) {
    case 'admin':
        header("Location: pages/admin/dashboard.php");
        break;
    case 'health_worker':
        header("Location: pages/health_worker/dashboard.php");
        break;
    case 'patient':
        header("Location: pages/patient/dashboard.php");
        break;
    default:
        // If role is not set or invalid, logout
        session_destroy();
        header("Location: pages/login.php");
}
exit;
?> 