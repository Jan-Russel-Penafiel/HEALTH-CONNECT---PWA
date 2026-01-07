<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'health_worker') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set banner as dismissed for today
$_SESSION['banner_dismissed'] = true;
$_SESSION['banner_dismissed_date'] = date('Y-m-d');

echo json_encode(['success' => true]);
