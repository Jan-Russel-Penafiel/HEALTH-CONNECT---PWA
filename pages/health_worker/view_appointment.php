<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: appointments.php');
    exit();
}

$appointment_id = intval($_GET['id']);

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
    header('Location: appointments.php');
    exit();
}

// Get appointment details
try {
    $query = "SELECT a.*, 
                     u.first_name, u.last_name, u.email, u.mobile_number,
                     s.status_name
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.appointment_id = ? AND a.health_worker_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id, $health_worker_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        header('Location: appointments.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching appointment: " . $e->getMessage());
    header('Location: appointments.php');
    exit();
}

// Get patient medical history
try {
    $query = "SELECT mr.*, 
                     hw.position,
                     u.first_name as hw_first_name, u.last_name as hw_last_name
              FROM medical_records mr
              JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
              JOIN users u ON hw.user_id = u.user_id
              WHERE mr.patient_id = ?
              ORDER BY mr.visit_date DESC
              LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment['patient_id']]);
    $medical_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching medical history: " . $e->getMessage());
    $medical_history = [];
}

// Get status options for dropdown
try {
    $query = "SELECT * FROM appointment_status ORDER BY status_id";
    $stmt = $pdo->query($query);
    $status_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching status options: " . $e->getMessage());
    $status_options = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <!-- Toast container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="smsToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-sms me-2"></i>
                <strong class="me-auto" id="toastTitle">SMS Notification</strong>
                <small class="text-muted">just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>Appointment Details</h1>
            <div class="header-actions">
                <a href="appointments.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="appointment-meta">
                    <div class="appointment-date">
                        <i class="far fa-calendar"></i> 
                        <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                    </div>
                    <div class="appointment-time">
                        <i class="far fa-clock"></i> 
                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                    </div>
                    <div class="appointment-status">
                        <span class="status-badge <?php echo strtolower($appointment['status_name']); ?>">
                            <?php echo $appointment['status_name']; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Patient Information</h3>
                        <div class="info-group">
                            <label>Name:</label>
                            <div><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Email:</label>
                            <div><?php echo htmlspecialchars($appointment['email']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Phone:</label>
                            <div><?php echo htmlspecialchars($appointment['mobile_number']); ?></div>
                        </div>
                        <div class="info-group">
                            <label>Reason for Visit:</label>
                            <div><?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : '<span class="text-muted">Not specified</span>'; ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h3>Appointment Notes</h3>
                        <div class="notes-section">
                            <?php if (!empty($appointment['notes'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted">No notes available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($appointment['status_name'] === 'Scheduled' || $appointment['status_name'] === 'Confirmed'): ?>
                <div class="action-section">
                    <h3>Update Status</h3>
                    <div class="status-update-form">
                        <select id="statusSelect" class="form-control">
                            <?php foreach ($status_options as $option): ?>
                                <option value="<?php echo $option['status_name']; ?>" 
                                        <?php echo ($option['status_name'] === $appointment['status_name']) ? 'selected' : ''; ?>>
                                    <?php echo $option['status_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" onclick="updateAppointmentStatus()">Update Status</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($medical_history)): ?>
                <div class="medical-history-section">
                    <h3>Recent Medical History</h3>
                    <div class="medical-records">
                        <?php foreach ($medical_history as $record): ?>
                            <div class="medical-record-card">
                                <div class="record-header">
                                    <div class="record-date">
                                        <?php echo date('F j, Y', strtotime($record['visit_date'])); ?>
                                    </div>
                                    <div class="record-doctor">
                                        Dr. <?php echo htmlspecialchars($record['hw_first_name'] . ' ' . $record['hw_last_name']); ?> 
                                        (<?php echo htmlspecialchars($record['position']); ?>)
                                    </div>
                                </div>
                                <div class="record-content">
                                    <?php if (!empty($record['chief_complaint'])): ?>
                                        <div class="record-section">
                                            <h4>Chief Complaint</h4>
                                            <p><?php echo nl2br(htmlspecialchars($record['chief_complaint'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($record['diagnosis'])): ?>
                                        <div class="record-section">
                                            <h4>Diagnosis</h4>
                                            <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($record['treatment'])): ?>
                                        <div class="record-section">
                                            <h4>Treatment</h4>
                                            <p><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
    // Function to show toast notification
    function showToast(message, success = true) {
        const toast = document.getElementById('smsToast');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');
        
        // Set toast classes based on success/failure
        toast.classList.remove('bg-success', 'bg-danger', 'text-white');
        if (success) {
            toast.classList.add('bg-success', 'text-white');
            toastTitle.innerHTML = '<i class="fas fa-check-circle me-2"></i>SMS Sent';
        } else {
            toast.classList.add('bg-danger', 'text-white');
            toastTitle.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>SMS Failed';
        }
        
        // Set message
        toastMessage.textContent = message;
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast, {
            animation: true,
            autohide: true,
            delay: 5000
        });
        bsToast.show();
    }

    function updateAppointmentStatus() {
        const status = document.getElementById('statusSelect').value;
        
        if (confirm('Are you sure you want to update the appointment status to ' + status + '?')) {
            fetch('/connect/api/appointments/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointment_id: <?php echo $appointment_id; ?>,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Function to safely insert alert messages
                    function showAlert(message, type = 'success') {
                        const alertDiv = document.createElement('div');
                        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                        alertDiv.innerHTML = `
                            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> 
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        
                        // Find the container and the first child (if any)
                        const container = document.querySelector('.container');
                        if (container) {
                            const firstChild = container.firstChild;
                            if (firstChild) {
                                container.insertBefore(alertDiv, firstChild);
                            } else {
                                container.appendChild(alertDiv);
                            }
                        }
                        
                        // Auto-remove the alert after 5 seconds
                        setTimeout(() => {
                            alertDiv.remove();
                        }, 5000);
                    }

                    // If status is "Confirmed", send SMS notification
                    if (status.toLowerCase() === 'confirmed') {
                        // Check if SMS was already sent in the status update response
                        if (data.sms_result) {
                            if (data.sms_result.success) {
                                window.location.href = 'appointments.php?sms_status=success&message=' + encodeURIComponent('SMS notification sent successfully to patient');
                            } else {
                                if (data.sms_result.message.includes('already sent')) {
                                    window.location.href = 'appointments.php?sms_status=info&message=' + encodeURIComponent('SMS notification was already sent for this appointment');
                                } else {
                                    window.location.href = 'appointments.php?sms_status=error&message=' + encodeURIComponent('Failed to send SMS: ' + data.sms_result.message);
                                }
                            }
                        } else {
                            // Send SMS notification only if it wasn't handled in the status update
                            fetch('/connect/api/appointments/send_reminder.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    appointment_id: <?php echo $appointment_id; ?>
                                })
                            })
                            .then(response => response.json())
                            .then(smsData => {
                                if (smsData.success) {
                                    window.location.href = 'appointments.php?sms_status=success&message=' + encodeURIComponent('SMS notification sent successfully to patient');
                                } else {
                                    if (smsData.message.includes('already sent')) {
                                        window.location.href = 'appointments.php?sms_status=info&message=' + encodeURIComponent('SMS notification was already sent for this appointment');
                                    } else {
                                        window.location.href = 'appointments.php?sms_status=error&message=' + encodeURIComponent('Failed to send SMS: ' + smsData.message);
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error sending SMS:', error);
                                window.location.href = 'appointments.php?sms_status=error&message=' + encodeURIComponent('Failed to send SMS: Network or server error');
                            });
                        }
                    } else {
                        // For non-confirmed status updates, redirect back to appointments
                        window.location.href = 'appointments.php?status_update=success&message=' + encodeURIComponent('Status updated successfully to ' + status);
                    }
                    
                    // Reload the page after a short delay
                    setTimeout(() => location.reload(), 5000);
                } else {
                    showAlert('Error updating appointment status: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while updating the appointment status', 'danger');
            });
        }
    }
    </script>

    <style>
    /* Toast styling */
    .toast {
        min-width: 300px;
    }
    .toast.bg-success .toast-header,
    .toast.bg-danger .toast-header {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }
    .toast.bg-success .btn-close,
    .toast.bg-danger .btn-close {
        filter: brightness(0) invert(1);
    }
    .toast-container {
        z-index: 1056;
    }
    </style>
</body>
</html> 