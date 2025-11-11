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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Get user input
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = trim($_POST['password'] ?? '');
                $first_name = trim($_POST['first_name']);
                $middle_name = trim($_POST['middle_name'] ?? '');
                $last_name = trim($_POST['last_name']);
                $gender = trim($_POST['gender']);
                $mobile_number = trim($_POST['mobile_number']);
                $address = trim($_POST['address']);
                $position = trim($_POST['position']);
                $license_number = trim($_POST['license_number']);
                $specialty = trim($_POST['specialty']);

                // Validate required fields
                if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($gender) || empty($position) || empty($license_number) || empty($specialty)) {
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
                                // Insert into users table
                                $query = "INSERT INTO users (role_id, username, email, password, mobile_number, first_name, middle_name, last_name, gender, address) VALUES ((SELECT role_id FROM user_roles WHERE role_name = 'health_worker'), :username, :email, :password, :mobile, :fname, :mname, :lname, :gender, :address)";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([
                                    ':username' => $username,
                                    ':email' => $email,
                                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                                    ':mobile' => $mobile_number,
                                    ':fname' => $first_name,
                                    ':mname' => $middle_name,
                                    ':lname' => $last_name,
                                    ':gender' => $gender,
                                    ':address' => $address
                                ]);
                                $user_id = $conn->lastInsertId();
                                // Insert into health_workers table
                                $query = "INSERT INTO health_workers (user_id, position, license_number, specialty) VALUES (:user_id, :position, :license, :specialty)";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([
                                    ':user_id' => $user_id,
                                    ':position' => $position,
                                    ':license' => $license_number,
                                    ':specialty' => $specialty
                                ]);
                                $conn->commit();
                                $_SESSION['success'] = "Health worker added successfully.";
                            }
                        }
                    } catch (PDOException $e) {
                        $conn->rollBack();
                        error_log("Error adding health worker: " . $e->getMessage());
                        $_SESSION['error'] = "Error adding health worker. Please try again.";
                    }
                }
                break;
                
            case 'update':
                try {
                    // Update users table
                    $query = "UPDATE users SET 
                             email = :email,
                             mobile_number = :mobile,
                             first_name = :fname,
                             middle_name = :mname,
                             last_name = :lname,
                             gender = :gender,
                             address = :address
                             WHERE user_id = :user_id";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([
                        ':email' => $_POST['email'],
                        ':mobile' => $_POST['mobile_number'],
                        ':fname' => $_POST['first_name'],
                        ':mname' => $_POST['middle_name'],
                        ':lname' => $_POST['last_name'],
                        ':gender' => $_POST['gender'],
                        ':address' => $_POST['address'],
                        ':user_id' => $_POST['user_id']
                    ]);
                    
                    // Update health_workers table
                    $query = "UPDATE health_workers SET 
                             position = :position,
                             license_number = :license,
                             specialty = :specialty
                             WHERE user_id = :user_id";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([
                        ':position' => $_POST['position'],
                        ':license' => $_POST['license_number'],
                        ':specialty' => $_POST['specialty'],
                        ':user_id' => $_POST['user_id']
                    ]);
                    
                    $_SESSION['success'] = "Health worker updated successfully.";
                } catch (PDOException $e) {
                    error_log("Error updating health worker: " . $e->getMessage());
                    $_SESSION['error'] = "Error updating health worker. Please try again.";
                }
                break;
                
            case 'delete':
                try {
                    $query = "DELETE FROM users WHERE user_id = :user_id";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([':user_id' => $_POST['user_id']]);
                    $_SESSION['success'] = "Health worker deleted successfully.";
                } catch (PDOException $e) {
                    error_log("Error deleting health worker: " . $e->getMessage());
                    $_SESSION['error'] = "Error deleting health worker. Please try again.";
                }
                break;
        }
        
        // Redirect to refresh the page
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch health workers with search functionality
try {
    $params = [];
    $whereClause = "u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'health_worker')";
    
    if (!empty($search)) {
        $whereClause .= " AND (
            u.first_name LIKE :search 
            OR u.last_name LIKE :search 
            OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search
            OR u.email LIKE :search 
            OR u.mobile_number LIKE :search
            OR u.username LIKE :search
            OR u.address LIKE :search
            OR hw.position LIKE :search
            OR hw.specialty LIKE :search
            OR hw.license_number LIKE :search
        )";
        $params[':search'] = "%$search%";
    }
    
    $query = "SELECT u.*, hw.* 
              FROM users u 
              JOIN health_workers hw ON u.user_id = hw.user_id 
              WHERE $whereClause
              ORDER BY u.last_name, u.first_name";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $health_workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching health workers: " . $e->getMessage());
    $health_workers = [];
    $_SESSION['error'] = "Error fetching health workers. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Workers Management</title>
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
        }
        
        .table-actions .btn-edit {
            background: #4a90e2;
            color: white;
        }
        
        .table-actions .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .table-actions .btn-action:hover {
            opacity: 0.9;
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
        
        .worker-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .worker-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .worker-card .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .worker-card .name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .worker-card .position {
            color: #666;
            font-style: italic;
            margin: 5px 0;
        }
        
        .worker-card .info-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
            color: #555;
        }
        
        .worker-card .info-item i {
            width: 20px;
            margin-right: 10px;
            color: #4a90e2;
        }
        
        .worker-card .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .worker-card .btn-action {
            padding: 8px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .worker-card .btn-edit {
            background: #4a90e2;
            color: white;
        }
        
        .worker-card .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .worker-card .btn-action:hover {
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .search-container {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
        
        .search-input-container {
            display: flex;
            width: 100%;
            gap: 10px;
        }
        
        .search-input {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .search-button {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-button:hover {
            background: #3a80d2;
        }
        
        .search-reset {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            align-self: flex-start;
        }
        
        .search-reset:hover {
            background: #5a6268;
        }
        
        .search-results-info {
            margin-top: 10px;
            color: #666;
            font-style: italic;
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
            
            .search-button {
                padding: 6px 10px;
                font-size: 0.9rem;
                min-width: 36px;
                width: auto;
            }
            .search-form {
                gap: 6px;
            }
            .search-reset {
                padding: 6px 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Manage Health Workers</h1>
            <div class="header-actions">
                <button class="btn btn-action btn-add" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Health Worker
                </button>
            </div>
        </div>

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
        
        <!-- Search Form -->
        <div class="search-container">
            <form class="search-form" method="GET">
                <div class="search-input-container">
                    <input type="text" name="search" class="search-input" placeholder="Search across all fields..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <button type="submit" class="search-button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <?php if (!empty($search)): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="search-reset">
                        <i class="fas fa-times"></i> Reset Search
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($search)): ?>
                    <div class="search-results-info">
                        Found <?php echo count($health_workers); ?> result<?php echo count($health_workers) !== 1 ? 's' : ''; ?> 
                        for "<?php echo htmlspecialchars($search); ?>".
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Desktop Table View -->
        <div class="desktop-table">
            <?php if (!empty($health_workers)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Specialty</th>
                            <th>License</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($health_workers as $worker): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($worker['last_name'] . ', ' . $worker['first_name']); ?>
                                        <?php if ($worker['middle_name']): ?>
                                            <?php echo ' ' . htmlspecialchars($worker['middle_name'][0]) . '.'; ?>
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td><?php echo htmlspecialchars($worker['position']); ?></td>
                                <td><?php echo htmlspecialchars($worker['specialty']); ?></td>
                                <td><?php echo htmlspecialchars($worker['license_number']); ?></td>
                                <td><?php echo htmlspecialchars($worker['email']); ?></td>
                                <td><?php echo htmlspecialchars($worker['mobile_number']); ?></td>
                                <td><?php echo htmlspecialchars($worker['address'] ? $worker['address'] : 'No address provided'); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action btn-edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($worker)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $worker['user_id']; ?>)">
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
                    <?php if (!empty($search)): ?>
                        <i class="fas fa-search fa-3x"></i>
                        <h3>No Health Workers Found</h3>
                        <p>No health workers match your search criteria. Try different keywords or <a href="<?php echo $_SERVER['PHP_SELF']; ?>">clear the search</a>.</p>
                    <?php else: ?>
                        <i class="fas fa-user-md fa-3x"></i>
                        <h3>No Health Workers Found</h3>
                        <p>Click the "Add Health Worker" button to add a new health worker.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Cards View -->
        <div class="mobile-cards">
            <div class="cards-grid">
            <?php if (!empty($health_workers)): ?>
                <?php foreach ($health_workers as $worker): ?>
                    <div class="worker-card">
                        <div class="header">
                            <h3 class="name">
                                <?php echo htmlspecialchars($worker['last_name'] . ', ' . $worker['first_name']); ?>
                                <?php if ($worker['middle_name']): ?>
                                    <?php echo ' ' . htmlspecialchars($worker['middle_name'][0]) . '.'; ?>
                                <?php endif; ?>
                            </h3>
                            <div class="position"><?php echo htmlspecialchars($worker['position']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-user-md"></i>
                            <span>Specialty: <?php echo htmlspecialchars($worker['specialty']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-id-card"></i>
                            <span>License: <?php echo htmlspecialchars($worker['license_number']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($worker['email']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($worker['mobile_number']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($worker['address'] ? $worker['address'] : 'No address provided'); ?></span>
                        </div>
                        
                        <div class="actions">
                            <button class="btn-action btn-edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($worker)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $worker['user_id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <?php if (!empty($search)): ?>
                        <i class="fas fa-search fa-3x"></i>
                        <h3>No Health Workers Found</h3>
                        <p>No health workers match your search criteria. Try different keywords or <a href="<?php echo $_SERVER['PHP_SELF']; ?>">clear the search</a>.</p>
                    <?php else: ?>
                        <i class="fas fa-user-md fa-3x"></i>
                        <h3>No Health Workers Found</h3>
                        <p>Click the "Add Health Worker" button to add a new health worker.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Modal -->
    <div id="workerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Health Worker</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="workerForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
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
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" name="position" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number">License Number</label>
                            <input type="text" id="license_number" name="license_number" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialty">Specialty</label>
                            <input type="text" id="specialty" name="specialty" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Health Worker</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const modal = document.getElementById('workerModal');
        const modalClose = document.querySelector('.modal-close');
        
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Health Worker';
            document.getElementById('formAction').value = 'add';
            document.getElementById('workerForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('username').removeAttribute('readonly');
            
            // Show password field in add mode
            const passwordGroup = document.getElementById('password').closest('.form-group');
            if (passwordGroup) {
                passwordGroup.style.display = 'block';
            }
            document.getElementById('password').required = true;
            
            modal.style.display = 'block';
        }
        
        function showEditModal(worker) {
            document.getElementById('modalTitle').textContent = 'Edit Health Worker';
            document.getElementById('formAction').value = 'update';
            document.getElementById('userId').value = worker.user_id;
            document.getElementById('username').setAttribute('readonly', true);
            
            // Hide password field in edit mode
            const passwordGroup = document.getElementById('password').closest('.form-group');
            if (passwordGroup) {
                passwordGroup.style.display = 'none';
            }
            document.getElementById('password').required = false;
            
            // Fill form fields
            Object.keys(worker).forEach(key => {
                const element = document.getElementById(key);
                if (element && key !== 'password') {
                    element.value = worker[key];
                }
            });
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this health worker?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        modalClose.onclick = closeModal;
        
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html> 