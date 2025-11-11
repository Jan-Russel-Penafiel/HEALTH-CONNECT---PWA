<?php
session_start();

// Check current session state
echo "<h1>Session Debug Information</h1>";
echo "<strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "<br>";
echo "<strong>Session ID:</strong> " . session_id() . "<br><br>";

echo "<h2>Session Variables:</h2>";
if (empty($_SESSION)) {
    echo "No session variables found.<br>";
} else {
    foreach ($_SESSION as $key => $value) {
        echo "<strong>$key:</strong> $value<br>";
    }
}

echo "<h2>Current URL Information:</h2>";
echo "<strong>Script Name:</strong> " . basename($_SERVER['SCRIPT_NAME']) . "<br>";
echo "<strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";
echo "<strong>HTTP Host:</strong> " . $_SERVER['HTTP_HOST'] . "<br>";

echo "<h2>Test Login:</h2>";
echo '<a href="/connect/pages/login.php" style="background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Login Page</a>';
?>