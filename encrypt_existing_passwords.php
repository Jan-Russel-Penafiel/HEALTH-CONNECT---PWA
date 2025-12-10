<?php
/**
 * Script to encrypt existing plain text passwords in the database
 * This should be run ONCE to migrate existing passwords to bcrypt hashed format
 * 
 * IMPORTANT: Backup your database before running this script!
 * 
 * Usage: Run this file once by accessing it in your browser or via CLI:
 * php encrypt_existing_passwords.php
 */

require_once __DIR__ . '/includes/config/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

echo "<h1>Password Encryption Script</h1>";
echo "<p>Starting password encryption process...</p>";

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get all users with plain text passwords
    $query = "SELECT user_id, username, password FROM users";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users = count($users);
    $updated_count = 0;
    $skipped_count = 0;
    
    echo "<p>Found {$total_users} users in the database.</p>";
    echo "<ul>";
    
    foreach ($users as $user) {
        $user_id = $user['user_id'];
        $username = $user['username'];
        $current_password = $user['password'];
        
        // Check if password is already hashed (bcrypt hashes start with $2y$ and are 60 characters)
        if (strlen($current_password) === 60 && substr($current_password, 0, 4) === '$2y$') {
            echo "<li>User '{$username}' (ID: {$user_id}) - Password already encrypted, skipping...</li>";
            $skipped_count++;
            continue;
        }
        
        // Hash the plain text password
        $hashed_password = password_hash($current_password, PASSWORD_DEFAULT);
        
        // Update the user's password
        $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$hashed_password, $user_id]);
        
        echo "<li>User '{$username}' (ID: {$user_id}) - Password encrypted successfully!</li>";
        $updated_count++;
    }
    
    echo "</ul>";
    
    // Commit transaction
    $pdo->commit();
    
    echo "<h2>Encryption Complete!</h2>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>Total users found: {$total_users}</li>";
    echo "<li>Passwords encrypted: {$updated_count}</li>";
    echo "<li>Passwords already encrypted (skipped): {$skipped_count}</li>";
    echo "</ul>";
    
    echo "<p style='color: green;'><strong>✓ All passwords have been successfully encrypted using bcrypt!</strong></p>";
    echo "<p style='color: orange;'><strong>Important:</strong> Users can now login with their existing passwords. The passwords are the same, but now stored securely.</p>";
    echo "<p style='color: red;'><strong>Security Note:</strong> Delete this script file (encrypt_existing_passwords.php) after running it once!</p>";
    
} catch (PDOException $e) {
    // Rollback on error
    $pdo->rollBack();
    
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>No changes were made to the database.</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Encryption Complete</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
        }
        h2 {
            color: #4CAF50;
        }
        ul {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        li {
            margin: 10px 0;
            padding: 5px;
        }
    </style>
</head>
<body>
</body>
</html>
