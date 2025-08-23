<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Check if immunization record ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: immunization.php');
    exit();
}

$immunization_record_id = intval($_GET['id']);

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get health worker ID
try {
    $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$health_worker) {
        header('Location: /connect/pages/login.php');
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    header('Location: immunization.php');
    exit();
}

// Get immunization record details
try {
    $query = "SELECT ir.*, 
                     u.first_name, u.last_name, u.email, u.mobile_number, u.date_of_birth, u.gender,
                     it.name as immunization_name, it.description as immunization_description,
                     it.recommended_age, it.dose_count
              FROM immunization_records ir
              JOIN patients p ON ir.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN immunization_types it ON ir.immunization_type_id = it.immunization_type_id
              WHERE ir.immunization_record_id = ? AND ir.health_worker_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$immunization_record_id, $health_worker_id]);
    $immunization = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$immunization) {
        header('Location: immunization.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching immunization record: " . $e->getMessage());
    header('Location: immunization.php');
    exit();
}

// Get patient's other immunization records
try {
    $query = "SELECT ir.*, 
                     it.name as immunization_name,
                     CONCAT(hw_u.first_name, ' ', hw_u.last_name) as health_worker_name
              FROM immunization_records ir
              JOIN immunization_types it ON ir.immunization_type_id = it.immunization_type_id
              JOIN health_workers hw ON ir.health_worker_id = hw.health_worker_id
              JOIN users hw_u ON hw.user_id = hw_u.user_id
              WHERE ir.patient_id = ? AND ir.immunization_record_id != ?
              ORDER BY ir.date_administered DESC
              LIMIT 10";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$immunization['patient_id'], $immunization_record_id]);
    $other_immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching other immunization records: " . $e->getMessage());
    $other_immunizations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immunization Record - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <style>
        .send-reminder-btn {
            background-color: #007bff;
            border: none;
            color: white !important;
        }
        
        .send-reminder-btn i {
            color: white !important;
        }
        
        .send-reminder-btn:hover {
            background-color: #0056b3;
            color: white !important;
        }
        
        .send-reminder-btn:hover i {
            color: white !important;
        }
        
        .send-reminder-btn:disabled {
            background-color: #6c757d;
            color: white !important;
            opacity: 0.6;
        }
        
        .send-reminder-btn:disabled i {
            color: white !important;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 1000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
            max-width: 400px;
            word-wrap: break-word;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast-success {
            background: #28a745;
        }

        .toast-error {
            background: #dc3545;
        }

        .toast-info {
            background: #17a2b8;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Immunization Record Details</h1>
            <div class="header-actions">
                <a href="immunization.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Immunization Records
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="immunization-meta">
                    <div class="immunization-date">
                        <i class="far fa-calendar"></i> 
                        <?php echo date('F j, Y', strtotime($immunization['date_administered'])); ?>
                    </div>
                    <div class="immunization-type">
                        <span class="badge badge-primary">
                            <?php echo htmlspecialchars($immunization['immunization_name']); ?>
                        </span>
                        <span class="badge badge-secondary">
                            Dose <?php echo $immunization['dose_number']; ?> of <?php echo $immunization['dose_count']; ?>
                        </span>
                    </div>
                </div>
                <?php if (!empty($immunization['next_schedule_date'])): ?>
                    <div class="next-schedule-alert">
                        <i class="fas fa-calendar-alt"></i> 
                        Next dose scheduled for: <strong><?php echo date('F j, Y', strtotime($immunization['next_schedule_date'])); ?></strong>
                        <?php 
                            $days_until = floor((strtotime($immunization['next_schedule_date']) - time()) / (60 * 60 * 24));
                            if ($days_until > 0) {
                                echo "<span class=\"days-count\">($days_until days from now)</span>";
                            } elseif ($days_until == 0) {
                                echo "<span class=\"days-count urgent\">Today!</span>";
                            } else {
                                echo "<span class=\"days-count overdue\">Overdue by " . abs($days_until) . " days</span>";
                            }
                        ?>
                        <button class="btn btn-sm btn-primary send-reminder-btn" onclick="sendReminder(<?php echo $immunization['patient_id']; ?>, '<?php echo htmlspecialchars($immunization['immunization_name']); ?>', '<?php echo $immunization['next_schedule_date']; ?>')">
                            <i class="fas fa-bell"></i> Send Reminder
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Patient Information</h3>
                        <div class="info-group">
                            <label>Name:</label>
                            <div><?php echo htmlspecialchars($immunization['first_name'] . ' ' . $immunization['last_name']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Email:</label>
                            <div><?php echo htmlspecialchars($immunization['email']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Phone:</label>
                            <div><?php echo htmlspecialchars($immunization['mobile_number']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Date of Birth:</label>
                            <div><?php echo date('F j, Y', strtotime($immunization['date_of_birth'])); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Gender:</label>
                            <div><?php echo htmlspecialchars($immunization['gender']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h3>Immunization Details</h3>
                        <div class="info-group">
                            <label>Type:</label>
                            <div><?php echo htmlspecialchars($immunization['immunization_name']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Description:</label>
                            <div><?php echo htmlspecialchars($immunization['immunization_description']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Recommended Age:</label>
                            <div><?php echo htmlspecialchars($immunization['recommended_age']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Dose Number:</label>
                            <div><?php echo $immunization['dose_number']; ?> of <?php echo $immunization['dose_count']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Date Administered:</label>
                            <div><?php echo date('F j, Y', strtotime($immunization['date_administered'])); ?></div>
                        </div>
                        <?php if (!empty($immunization['next_schedule_date'])): ?>
                        <div class="info-group">
                            <label>Next Schedule Date:</label>
                            <div><?php echo date('F j, Y', strtotime($immunization['next_schedule_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-group">
                            <label>Notes:</label>
                            <div class="notes-section">
                                <?php if (!empty($immunization['notes'])): ?>
                                    <p><?php echo nl2br(htmlspecialchars($immunization['notes'])); ?></p>
                                <?php else: ?>
                                    <p class="text-muted">No notes available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($other_immunizations)): ?>
                <div class="other-immunizations-section">
                    <h3>Other Immunization Records for this Patient</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Immunization</th>
                                    <th>Dose</th>
                                    <th>Administered By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($other_immunizations as $record): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($record['date_administered'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['immunization_name']); ?></td>
                                    <td><?php echo $record['dose_number']; ?></td>
                                    <td><?php echo htmlspecialchars($record['health_worker_name']); ?></td>
                                    <td>
                                        <a href="view_immunization.php?id=<?php echo $record['immunization_record_id']; ?>" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <script>
        function sendReminder(patientId, immunizationType, scheduleDate) {
            const btn = document.querySelector('.send-reminder-btn');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            btn.disabled = true;
            
            // Format the date for display
            const formattedDate = new Date(scheduleDate).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Create the reminder message
            const message = `Reminder: Your next ${immunizationType} immunization is scheduled for ${formattedDate}. Please contact HealthConnect to confirm your appointment.`;
            
            // Use the immunization-specific reminder API
            fetch('../../api/immunizations/send_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    patient_id: patientId,
                    message: message,
                    type: 'immunization_reminder',
                    immunization_type: immunizationType,
                    schedule_date: scheduleDate
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast('Failed to send reminder: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error sending reminder. Please check your internet connection and try again.', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
        }
        
        function showToast(message, type = 'info') {
            // Remove any existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => {
                toast.remove();
            });
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = message;
            document.body.appendChild(toast);
            
            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Hide toast after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html> 