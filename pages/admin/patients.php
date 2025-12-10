<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all'; // Default to 'all'
$where_clause = '';
$params = [];

// Build where clause based on search and filter
if (!empty($search)) {
    $where_clause = "WHERE (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.mobile_number LIKE :search)";
    $params[':search'] = "%$search%";
}

// Add filter condition
if ($filter !== 'all') {
    $is_approved = ($filter === 'approved') ? 1 : 0;
    if (!empty($where_clause)) {
        $where_clause .= " AND p.is_approved = :is_approved";
    } else {
        $where_clause = "WHERE p.is_approved = :is_approved";
    }
    $params[':is_approved'] = $is_approved;
}

// Get patients list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $count_query = "SELECT COUNT(*) FROM users u 
                    JOIN user_roles r ON u.role_id = r.role_id 
                    JOIN patients p ON u.user_id = p.user_id
                    WHERE r.role_name = 'patient' " . 
                    ($where_clause ? "AND " . substr($where_clause, 6) : '');
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $total_patients = $stmt->fetchColumn();
    $total_pages = ceil($total_patients / $limit);

    // Get patients for current page
    $query = "SELECT u.*, p.patient_id, p.blood_type, p.is_approved, p.approved_at, p.height, p.weight,
              p.emergency_contact_name, p.emergency_contact_number, p.emergency_contact_relationship,
              (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id) as appointment_count,
              (SELECT COUNT(*) FROM immunization_records ir WHERE ir.patient_id = p.patient_id) as immunization_count
              FROM users u 
              JOIN user_roles r ON u.role_id = r.role_id 
              JOIN patients p ON u.user_id = p.user_id
              WHERE r.role_name = 'patient' " .
              ($where_clause ? str_replace('WHERE', 'AND', $where_clause) : '') . 
              " ORDER BY u.last_name, u.first_name 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
    $_SESSION['error'] = "Error fetching patients. Please try again.";
}

// Handle form submission for adding new patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_patient') {
    // Get user input
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name']);
    $gender = trim($_POST['gender']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $mobile_number = trim($_POST['mobile_number']);
    $address = trim($_POST['address']);
    $blood_type = trim($_POST['blood_type']);
    $height = trim($_POST['height']);
    $weight = trim($_POST['weight']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_number = trim($_POST['emergency_contact_number']);
    $emergency_contact_relationship = trim($_POST['emergency_contact_relationship']);

    // Validate required fields
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($gender) || empty($date_of_birth)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } else {
        try {
            $conn->beginTransaction();

            // Check if username already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(":username", $username);
            $check_stmt->execute();
            if ($check_stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Username already exists. Please choose another.";
                $conn->rollBack();
            } else {
                // Check if email already exists
                $check_query = "SELECT COUNT(*) FROM users WHERE email = :email";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(":email", $email);
                $check_stmt->execute();
                if ($check_stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Email already exists. Please use another or login.";
                    $conn->rollBack();
                } else {
                    // Get role_id for patient
                    $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'patient'");
                    $stmt->execute();
                    $role_id = $stmt->fetchColumn();

                    // Insert into users table
                    $stmt = $conn->prepare("INSERT INTO users (role_id, username, email, password, mobile_number, first_name, middle_name, last_name, gender, date_of_birth, address) VALUES (:role_id, :username, :email, :password, :mobile_number, :first_name, :middle_name, :last_name, :gender, :date_of_birth, :address)");
                    $stmt->execute([
                        ':role_id' => $role_id,
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':mobile_number' => $mobile_number,
                        ':first_name' => $first_name,
                        ':middle_name' => $middle_name,
                        ':last_name' => $last_name,
                        ':gender' => $gender,
                        ':date_of_birth' => $date_of_birth,
                        ':address' => $address
                    ]);

                    $user_id = $conn->lastInsertId();

                    // Insert into patients table
                    $stmt = $conn->prepare("INSERT INTO patients (user_id, blood_type, height, weight, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, is_approved, approved_at) VALUES (:user_id, :blood_type, :height, :weight, :emergency_contact_name, :emergency_contact_number, :emergency_contact_relationship, 1, NOW())");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':blood_type' => $blood_type,
                        ':height' => $height,
                        ':weight' => $weight,
                        ':emergency_contact_name' => $emergency_contact_name,
                        ':emergency_contact_number' => $emergency_contact_number,
                        ':emergency_contact_relationship' => $emergency_contact_relationship
                    ]);

                    $conn->commit();
                    $_SESSION['success'] = "Patient added successfully.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Error adding patient: " . $e->getMessage());
            $_SESSION['error'] = "Error adding patient. Please try again.";
        }
    }
}

// Handle form submission for editing patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_patient') {
    // Get user input
    $user_id = trim($_POST['user_id']);
    $patient_id = trim($_POST['patient_id']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $mobile_number = trim($_POST['mobile_number']);
    $gender = trim($_POST['gender']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $address = trim($_POST['address']);
    $blood_type = trim($_POST['blood_type']);
    $height = trim($_POST['height']);
    $weight = trim($_POST['weight']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_number = trim($_POST['emergency_contact_number']);
    $emergency_contact_relationship = trim($_POST['emergency_contact_relationship']);
    $is_approved = trim($_POST['is_approved']);

    // Validate required fields
    if (empty($user_id) || empty($first_name) || empty($last_name) || empty($email) || empty($gender) || empty($date_of_birth)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } else {
        try {
            $conn->beginTransaction();

            // Update users table
            $stmt = $conn->prepare("UPDATE users SET 
                                   first_name = :first_name, 
                                   middle_name = :middle_name, 
                                   last_name = :last_name, 
                                   email = :email, 
                                   mobile_number = :mobile_number, 
                                   gender = :gender, 
                                   date_of_birth = :date_of_birth, 
                                   address = :address 
                                   WHERE user_id = :user_id");
            $stmt->execute([
                ':first_name' => $first_name,
                ':middle_name' => $middle_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':mobile_number' => $mobile_number,
                ':gender' => $gender,
                ':date_of_birth' => $date_of_birth,
                ':address' => $address,
                ':user_id' => $user_id
            ]);

            // Update patients table
            $approved_at = ($is_approved == 1) ? 'NOW()' : 'NULL';
            $stmt = $conn->prepare("UPDATE patients SET 
                                   blood_type = :blood_type, 
                                   height = :height, 
                                   weight = :weight, 
                                   emergency_contact_name = :emergency_contact_name, 
                                   emergency_contact_number = :emergency_contact_number, 
                                   emergency_contact_relationship = :emergency_contact_relationship, 
                                   is_approved = :is_approved, 
                                   approved_at = " . ($is_approved == 1 ? 'NOW()' : 'NULL') . " 
                                   WHERE user_id = :user_id");
            $stmt->execute([
                ':blood_type' => $blood_type,
                ':height' => $height,
                ':weight' => $weight,
                ':emergency_contact_name' => $emergency_contact_name,
                ':emergency_contact_number' => $emergency_contact_number,
                ':emergency_contact_relationship' => $emergency_contact_relationship,
                ':is_approved' => $is_approved,
                ':user_id' => $user_id
            ]);

            $conn->commit();
            $_SESSION['success'] = "Patient updated successfully.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Error updating patient: " . $e->getMessage());
            $_SESSION['error'] = "Error updating patient. Please try again.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <style>
        /* Desktop Table View */
        .desktop-table {
            display: none;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .table-actions .btn-action {
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 4px;
            color: white;
            text-decoration: none;
        }
        
        .table-actions .btn-view {
            background: #2196F3;
        }
        
        .table-actions .btn-edit {
            background: #4CAF50;
        }
        
        .table-actions .btn-approve {
            background: #4CAF50;
        }
        
        .table-actions .btn-delete {
            background: #F44336;
        }
        
        .table-actions .btn-action:hover {
            opacity: 0.9;
        }
        
        .approval-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Mobile Cards View */
        .mobile-cards {
            display: block;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }
        
        .patient-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .patient-card .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .patient-card .name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .patient-card .info-section {
            margin: 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .patient-card .info-item {
            display: flex;
            align-items: center;
            margin: 8px 0;
            color: #555;
        }
        
        .patient-card .info-item i {
            width: 20px;
            margin-right: 10px;
            color: #4a90e2;
        }
        
        .patient-card .stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }
        
        .patient-card .stat-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .patient-card .stat-number {
            font-size: 1.2em;
            font-weight: bold;
            color: #4a90e2;
            margin-bottom: 5px;
        }
        
        .patient-card .stat-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .patient-card .actions {
            display: flex;
            justify-content: space-between;  /* Changed to space-between for even distribution */
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            flex-wrap: nowrap;  /* Prevent wrapping */
            width: 100%;  /* Ensure full width */
        }
        
        .patient-card .btn-action {
            flex: 1 1 0;  /* Equal width distribution */
            min-width: 0;  /* Remove min-width constraint */
            max-width: none;  /* Remove max-width constraint */
            padding: 8px 15px;  /* Medium-sized padding */
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;  /* Increased gap between icon and text */
            font-size: 0.9em;  /* Slightly larger font */
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 0;  /* Remove any margin */
            height: 36px;  /* Fixed height for consistency */
        }

        .patient-card .btn-action i {
            font-size: 1em;  /* Match icon size with text */
        }

        .patient-card .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }

        /* Update button colors with slightly adjusted shades */
        .patient-card .btn-view {
            background: #2196F3;  /* Brighter blue */
        }

        .patient-card .btn-edit {
            background: #4CAF50;  /* Brighter green */
        }

        .patient-card .btn-approve {
            background: #4CAF50;  /* Match edit button */
        }

        .patient-card .btn-delete {
            background: #F44336;  /* Brighter red */
        }

        /* Mobile responsiveness */
        @media (max-width: 480px) {
            .patient-card .actions {
                flex-direction: row;  /* Keep horizontal */
                gap: 8px;  /* Slightly reduced gap on mobile */
            }
            
            .patient-card .btn-action {
                padding: 8px 12px;  /* Slightly reduced padding */
                font-size: 0.85em;  /* Slightly smaller font */
            }
        }
        
        .patient-card .approval-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-top: 8px;
        }
        
        .patient-card .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .patient-card .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .patient-card .btn-action:hover {
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        /* Keep existing modal styles */
        .approval-status {
            margin-top: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .approval-status i {
            font-size: 1em;
        }

        .approval-status small {
            display: block;
            font-size: 0.85em;
            opacity: 0.8;
            margin-top: 2px;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Desktop Layout */
        @media (min-width: 769px) {
            .desktop-table {
                display: block;
            }
            
            .mobile-cards {
                display: none;
            }
        }

        /* Mobile Layout */
        @media (max-width: 768px) {
            .desktop-table {
                display: none;
            }
            
            .mobile-cards {
                display: block;
            }
        }

        /* Alert Styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            position: relative;
            font-size: 14px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }

        .alert-close {
            float: right;
            background: none;
            border: none;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            padding: 0;
            margin-left: 10px;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Alert animations */
        .alert {
            animation: slideInDown 0.3s ease-out;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 20px 0;
        }

        .btn-page {
            padding: 8px 12px;
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-page:hover {
            background: #e9ecef;
            color: #333;
        }

        .btn-page.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .close, .modal-close {
            font-size: 24px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close:hover, .modal-close:hover {
            color: #333;
        }

        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .modal-body p {
            margin: 0;
            padding: 8px 0;
            color: #333;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }

        .modal-body label {
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-row, .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 16px;
            padding-right: 32px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        /* Button Styles */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background-color: #45a049;
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #e4e4e4;
        }

        .btn-action {
            background-color: #4CAF50;
            color: white;
        }

        .btn-action:hover {
            background-color: #45a049;
        }

        .btn-add {
            background-color: #4CAF50;
            color: white;
        }

        .btn-add:hover {
            background-color: #45a049;
        }

        /* Page Header Styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            margin: 0;
            color: #333;
            font-size: 2rem;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 2% auto;
                padding: 15px;
            }

            .form-row, .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 style="color: #4CAF50;"> Manage Patients</h1>
            <div class="header-actions">
                <form class="search-form" action="" method="GET">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                <div class="add-and-filter">
                    <button class="btn" onclick="showAddPatientModal()">
                        <i class="fas fa-user-plus"></i> Add Patient
                    </button>
                    <div class="filter-buttons">
                        <a href="?filter=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn-filter <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> All
                        </a>
                        <a href="?filter=approved<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn-filter <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i> Approved
                        </a>
                        <a href="?filter=pending<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn-filter <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i> Pending
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .header-actions {
                display: flex;
                gap: 15px;
                align-items: flex-start;
            }

            .add-and-filter {
                display: flex;
                flex-direction: column;
                gap: 10px;
                align-items: flex-end;
            }

            .filter-buttons {
                display: flex;
                gap: 8px;
                background: #f8f9fa;
                padding: 6px;
                border-radius: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .btn-filter {
                padding: 4px 12px;
                font-size: 0.85rem;
                border-radius: 15px;
                background: transparent;
                color: #666;
                text-decoration: none;
                display: flex;
                align-items: center;
                gap: 4px;
                transition: all 0.2s ease;
            }

            .btn-filter:hover {
                background: #e9ecef;
                color: #333;
            }

            .btn-filter.active {
                background: var(--primary-color);
                color: white;
            }

            .btn-filter i {
                font-size: 0.8em;
            }

            .btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: background-color 0.2s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                background: #4CAF50;
                color: white;
            }

            .btn:hover {
                background: #45a049;
            }

            .search-form {
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .search-form input {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                min-width: 200px;
            }

            .search-form button {
                padding: 8px 12px;
                background: #4CAF50;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .search-form button:hover {
                background: #45a049;
            }

            @media (max-width: 768px) {
                .header-actions {
                    flex-direction: column;
                    align-items: stretch;
                }

                .add-and-filter {
                    align-items: stretch;
                }

                .filter-buttons {
                    justify-content: center;
                }

                .search-form {
                    width: 100%;
                }
            }

            @media (max-width: 480px) {
                .filter-buttons {
                    padding: 4px;
                }

                .btn-filter {
                    padding: 4px 8px;
                    font-size: 0.8rem;
                }
            }
        </style>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Desktop Table View -->
        <div class="desktop-table">
            <?php if (!empty($patients)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Blood Type</th>
                            <th>Appointments</th>
                            <th>Immunizations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                        <?php if ($patient['middle_name']): ?>
                                            <?php echo ' ' . htmlspecialchars($patient['middle_name'][0]) . '.'; ?>
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                <td><?php echo htmlspecialchars($patient['mobile_number']); ?></td>
                                <td><?php echo htmlspecialchars($patient['blood_type'] ?: 'Not specified'); ?></td>
                                <td><?php echo $patient['appointment_count']; ?></td>
                                <td><?php echo $patient['immunization_count']; ?></td>
                                <td>
                                    <span class="approval-status <?php echo $patient['is_approved'] ? 'status-approved' : 'status-pending'; ?>">
                                        <i class="fas <?php echo $patient['is_approved'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                        <?php echo $patient['is_approved'] ? 'Approved' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action btn-view" onclick="showViewModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn-action btn-edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if (!$patient['is_approved']): ?>
                                            <button class="btn-action btn-approve" onclick="approvePatient(<?php echo $patient['user_id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $patient['user_id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users fa-3x"></i>
                    <h3>No Patients Found</h3>
                    <p><?php echo !empty($search) ? 'No patients match your search criteria.' : 'No patients registered yet.'; ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-cards">
            <div class="cards-grid">
            <?php if (!empty($patients)): ?>
                <?php foreach ($patients as $patient): ?>
                    <div class="patient-card">
                        <div class="header">
                            <h3 class="name">
                                <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                <?php if ($patient['middle_name']): ?>
                                    <?php echo ' ' . htmlspecialchars($patient['middle_name'][0]) . '.'; ?>
                                <?php endif; ?>
                            </h3>
                            <div class="approval-status <?php echo $patient['is_approved'] ? 'status-approved' : 'status-pending'; ?>">
                                <i class="fas <?php echo $patient['is_approved'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                <?php echo $patient['is_approved'] ? 'Approved' : 'Pending Approval'; ?>
                                <?php if ($patient['is_approved']): ?>
                                    <br><small>Approved on: <?php echo date('M d, Y', strtotime($patient['approved_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($patient['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($patient['mobile_number']); ?></span>
                            </div>
                            <?php if (isset($patient['blood_type']) && $patient['blood_type']): ?>
                            <div class="info-item">
                                <i class="fas fa-tint"></i>
                                <span>Blood Type: <?php echo htmlspecialchars($patient['blood_type']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $patient['appointment_count']; ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-calendar-check"></i> Appointments
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $patient['immunization_count']; ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-syringe"></i> Immunizations
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span>Registered: <?php echo date('M d, Y', strtotime($patient['created_at'])); ?></span>
                        </div>
                        
                        <div class="actions">
                            <button class="btn-action btn-view" title="View Details" onclick="showViewModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-action btn-edit" title="Edit Patient" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if (!$patient['is_approved']): ?>
                                <button class="btn-action btn-approve" title="Approve Patient" onclick="approvePatient(<?php echo $patient['user_id']; ?>)">
                                    <i class="fas fa-check"></i> OK
                                </button>
                            <?php endif; ?>
                            <button class="btn-action btn-delete" title="Delete Patient" onclick="confirmDelete(<?php echo $patient['user_id']; ?>)">
                                <i class="fas fa-trash"></i> Del
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-injured"></i>
                    <h3>No Patients Found</h3>
                    <p>Click the "Add Patient" button to add a new patient.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn-page">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="btn-page active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn-page"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn-page">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Patient Modal -->
    <div class="modal" id="addPatientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #4CAF50";>Add Patient</h3>
                <span class="close">&times;</span>
            </div>
            <form id="addPatientForm" method="POST">
                <input type="hidden" name="action" value="add_patient">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mobile_number">Mobile Number</label>
                        <input type="tel" id="mobile_number" name="mobile_number" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="blood_type">Blood Type</label>
                        <select id="blood_type" name="blood_type" class="form-control">
                            <option value="">Select Blood Type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" class="form-control" step="0.01">
                    </div>

                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" class="form-control" step="0.01">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" required></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_number">Emergency Contact Number</label>
                        <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_relationship">Emergency Contact Relationship</label>
                        <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Patient</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddPatientModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Patient Modal -->
    <div class="modal" id="viewPatientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #4CAF50;">View Patient Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><strong>Name:</strong></label>
                        <p id="view_full_name"></p>
                    </div>
                    <div class="form-group">
                        <label><strong>Username:</strong></label>
                        <p id="view_username"></p>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><strong>Email:</strong></label>
                        <p id="view_email"></p>
                    </div>
                    <div class="form-group">
                        <label><strong>Mobile Number:</strong></label>
                        <p id="view_mobile_number"></p>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><strong>Gender:</strong></label>
                        <p id="view_gender"></p>
                    </div>
                    <div class="form-group">
                        <label><strong>Date of Birth:</strong></label>
                        <p id="view_date_of_birth"></p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><strong>Address:</strong></label>
                    <p id="view_address"></p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><strong>Blood Type:</strong></label>
                        <p id="view_blood_type"></p>
                    </div>
                    <div class="form-group">
                        <label><strong>Status:</strong></label>
                        <p id="view_status"></p>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><strong>Height:</strong></label>
                        <p id="view_height"></p>
                    </div>
                    <div class="form-group">
                        <label><strong>Weight:</strong></label>
                        <p id="view_weight"></p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><strong>Emergency Contact:</strong></label>
                    <p id="view_emergency_contact"></p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><strong>Total Appointments:</strong></label>
                        <p id="view_appointment_count"></p>
                    </div>
                    <div class="form-group">
                        <label><strong>Total Immunizations:</strong></label>
                        <p id="view_immunization_count"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div class="modal" id="editPatientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #4CAF50;">Edit Patient</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editPatientForm" method="POST">
                <input type="hidden" name="action" value="edit_patient">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="patient_id" id="edit_patient_id">
                
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_first_name">First Name</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_middle_name">Middle Name</label>
                            <input type="text" id="edit_middle_name" name="middle_name" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_last_name">Last Name</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="username" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_mobile_number">Mobile Number</label>
                            <input type="tel" id="edit_mobile_number" name="mobile_number" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_gender">Gender</label>
                            <select id="edit_gender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_date_of_birth">Date of Birth</label>
                            <input type="date" id="edit_date_of_birth" name="date_of_birth" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_blood_type">Blood Type</label>
                            <select id="edit_blood_type" name="blood_type" class="form-control">
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status</label>
                            <select id="edit_status" name="is_approved" class="form-control">
                                <option value="0">Pending</option>
                                <option value="1">Approved</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_height">Height (cm)</label>
                            <input type="number" id="edit_height" name="height" class="form-control" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="edit_weight">Weight (kg)</label>
                            <input type="number" id="edit_weight" name="weight" class="form-control" step="0.01">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea id="edit_address" name="address" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_emergency_contact_name">Emergency Contact Name</label>
                            <input type="text" id="edit_emergency_contact_name" name="emergency_contact_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_emergency_contact_number">Emergency Contact Number</label>
                            <input type="tel" id="edit_emergency_contact_number" name="emergency_contact_number" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_emergency_contact_relationship">Emergency Contact Relationship</label>
                        <input type="text" id="edit_emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Patient</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function disapprovePatient(userId) {
        if (confirm('Are you sure you want to disapprove and delete this patient? This action cannot be undone.')) {
            // Use absolute path to ensure correct endpoint location
            fetch('/connect/api/patients/update_approval.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    is_approved: false,
                    delete_on_disapprove: true
                })
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        // Try to parse as JSON
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Failed to parse JSON:", text);
                        throw new Error("Server returned invalid JSON. Check server logs.");
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    alert('Patient has been disapproved and deleted successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.message || 'Failed to disapprove patient');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
        }
    }

    function confirmDelete(userId) {
        if (confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
            fetch(`/connect/api/patients/delete.php?id=${userId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        // Try to parse as JSON
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Failed to parse JSON:", text);
                        throw new Error("Server returned invalid JSON. Check server logs.");
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    alert('Patient deleted successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.message || 'Failed to delete patient');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
        }
    }

    // Add Patient Modal functionality
    const addPatientModal = document.getElementById('addPatientModal');
    const addPatientSpan = addPatientModal.getElementsByClassName('close')[0];

    function showAddPatientModal() {
        addPatientModal.style.display = 'block';
    }

    function closeAddPatientModal() {
        addPatientModal.style.display = 'none';
    }

    addPatientSpan.onclick = function() {
        closeAddPatientModal();
    }

    // Form validation
    document.getElementById('addPatientForm').addEventListener('submit', function(e) {
        const mobileNumber = document.getElementById('mobile_number');
        const emergencyNumber = document.getElementById('emergency_contact_number');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        
        // Validate password length
        if (password.value.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long');
            return;
        }
        
        // Validate mobile number format
        const mobilePattern = /^\+?[\d\s-]{10,}$/;
        if (!mobilePattern.test(mobileNumber.value)) {
            e.preventDefault();
            alert('Please enter a valid mobile number');
            return;
        }

        // Validate emergency contact number
        if (!mobilePattern.test(emergencyNumber.value)) {
            e.preventDefault();
            alert('Please enter a valid emergency contact number');
            return;
        }

        // Validate email format
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return;
        }
    });

    function approvePatient(userId) {
        if (confirm('Are you sure you want to approve this patient?')) {
            // Use absolute path to ensure correct endpoint location
            fetch('/connect/api/patients/update_approval.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    is_approved: true
                })
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        // Try to parse as JSON
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Failed to parse JSON:", text);
                        throw new Error("Server returned invalid JSON. Check server logs.");
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    alert('Patient approved successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.message || 'Failed to approve patient');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
        }
    }

    // View Patient Modal functionality
    const viewPatientModal = document.getElementById('viewPatientModal');
    
    function showViewModal(patient) {
        // Populate view modal with patient data
        document.getElementById('view_full_name').textContent = 
            patient.last_name + ', ' + patient.first_name + (patient.middle_name ? ' ' + patient.middle_name.charAt(0) + '.' : '');
        document.getElementById('view_username').textContent = patient.username || 'N/A';
        document.getElementById('view_email').textContent = patient.email || 'N/A';
        document.getElementById('view_mobile_number').textContent = patient.mobile_number || 'N/A';
        document.getElementById('view_gender').textContent = patient.gender || 'N/A';
        document.getElementById('view_date_of_birth').textContent = patient.date_of_birth ? 
            new Date(patient.date_of_birth).toLocaleDateString() : 'N/A';
        document.getElementById('view_address').textContent = patient.address || 'N/A';
        document.getElementById('view_blood_type').textContent = patient.blood_type || 'Not specified';
        document.getElementById('view_status').innerHTML = patient.is_approved == 1 ? 
            '<span class="status-approved"><i class="fas fa-check-circle"></i> Approved</span>' : 
            '<span class="status-pending"><i class="fas fa-clock"></i> Pending</span>';
        document.getElementById('view_height').textContent = patient.height ? patient.height + ' cm' : 'N/A';
        document.getElementById('view_weight').textContent = patient.weight ? patient.weight + ' kg' : 'N/A';
        document.getElementById('view_emergency_contact').textContent = patient.emergency_contact_name ? 
            patient.emergency_contact_name + ' (' + patient.emergency_contact_relationship + ') - ' + patient.emergency_contact_number : 'N/A';
        document.getElementById('view_appointment_count').textContent = patient.appointment_count || '0';
        document.getElementById('view_immunization_count').textContent = patient.immunization_count || '0';
        
        viewPatientModal.style.display = 'block';
    }
    
    function closeViewModal() {
        viewPatientModal.style.display = 'none';
    }

    // Edit Patient Modal functionality
    const editPatientModal = document.getElementById('editPatientModal');
    
    function showEditModal(patient) {
        // Populate edit modal with patient data
        document.getElementById('edit_user_id').value = patient.user_id;
        document.getElementById('edit_patient_id').value = patient.patient_id;
        document.getElementById('edit_first_name').value = patient.first_name || '';
        document.getElementById('edit_middle_name').value = patient.middle_name || '';
        document.getElementById('edit_last_name').value = patient.last_name || '';
        document.getElementById('edit_username').value = patient.username || '';
        document.getElementById('edit_email').value = patient.email || '';
        document.getElementById('edit_mobile_number').value = patient.mobile_number || '';
        document.getElementById('edit_gender').value = patient.gender || '';
        document.getElementById('edit_date_of_birth').value = patient.date_of_birth || '';
        document.getElementById('edit_blood_type').value = patient.blood_type || '';
        document.getElementById('edit_status').value = patient.is_approved || '0';
        document.getElementById('edit_height').value = patient.height || '';
        document.getElementById('edit_weight').value = patient.weight || '';
        document.getElementById('edit_address').value = patient.address || '';
        document.getElementById('edit_emergency_contact_name').value = patient.emergency_contact_name || '';
        document.getElementById('edit_emergency_contact_number').value = patient.emergency_contact_number || '';
        document.getElementById('edit_emergency_contact_relationship').value = patient.emergency_contact_relationship || '';
        
        editPatientModal.style.display = 'block';
    }
    
    function closeEditModal() {
        editPatientModal.style.display = 'none';
    }

    // Modal click outside to close functionality  
    window.addEventListener('click', function(event) {
        if (event.target == addPatientModal) {
            closeAddPatientModal();
        }
        if (event.target == viewPatientModal) {
            closeViewModal();
        }
        if (event.target == editPatientModal) {
            closeEditModal();
        }
    });

    
    </script>
</body>
</html> 