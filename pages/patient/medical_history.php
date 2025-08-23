<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is patient
if ($_SESSION['role'] !== 'patient') {
    header('Location: /connect/pages/login.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get patient ID
    $query = "SELECT p.patient_id, CONCAT(u.first_name, ' ', u.last_name) as patient_name 
              FROM patients p 
              JOIN users u ON p.user_id = u.user_id 
              WHERE p.user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception("Patient record not found");
    }

    // Get medical records with health worker details
    $query = "SELECT m.*,
              CONCAT('Dr. ', hw_u.first_name, ' ', hw_u.last_name) as health_worker_name,
              hw.position as health_worker_position
              FROM medical_records m
              JOIN health_workers hw ON m.health_worker_id = hw.health_worker_id
              JOIN users hw_u ON hw.user_id = hw_u.user_id
              WHERE m.patient_id = :patient_id
              ORDER BY m.visit_date DESC, m.record_id DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error in medical history: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            margin: 0;
        }
        
        .date-display {
            color: #666;
            font-size: 1.1em;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .medical-records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 1rem 0;
        }
        
        /* Table styles for desktop */
        .medical-records-table {
            display: none;
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .medical-records-table th,
        .medical-records-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .medical-records-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }
        
        .medical-records-table tr:hover {
            background: #f8f9fa;
        }
        
        .medical-records-table tr:last-child td {
            border-bottom: none;
        }
        
        .table-cell-content {
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
        }
        
        .table-follow-up {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.85rem;
            display: inline-block;
            margin-top: 0.25rem;
        }
        
        /* Desktop view - show table, hide cards */
        @media (min-width: 992px) {
            .medical-records-grid {
                display: none;
            }
            
            .medical-records-table {
                display: table;
            }
        }
        
        /* Mobile view - show cards, hide table */
        @media (max-width: 991px) {
            .medical-records-grid {
                display: grid;
                grid-template-columns: 1fr;
            }
            
            .medical-records-table {
                display: none;
            }
        }
        
        .medical-record {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .medical-record:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .record-header {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .record-date {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .record-doctor {
            color: #34495e;
            font-size: 0.95rem;
        }

        .record-section {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 6px;
        }

        .record-section h4 {
            color: #2c3e50;
            font-size: 1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .record-section p {
            color: #34495e;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .empty-message {
            text-align: center;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .empty-message i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .empty-message p {
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .follow-up {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .follow-up i {
            font-size: 1.2em;
        }

        .follow-up-badge {
            margin-left: auto;
        }

        .follow-up-badge .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .medical-records-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Medical History</h1>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($medical_records)): ?>
            <div class="empty-message">
                <i class="fas fa-notes-medical"></i>
                <h3>No Medical Records</h3>
                <p>You don't have any medical records yet.</p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <table class="medical-records-table">
                <thead>
                    <tr>
                        <th>Visit Date</th>
                        <th>Health Worker</th>
                        <th>Chief Complaint</th>
                        <th>Diagnosis</th>
                        <th>Treatment</th>
                        <th>Follow-up</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medical_records as $record): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: #2c3e50;">
                                    <?php echo date('M j, Y', strtotime($record['visit_date'])); ?>
                                </div>
                                <div style="color: #7f8c8d; font-size: 0.9rem;">
                                    <?php echo date('l', strtotime($record['visit_date'])); ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #2c3e50;">
                                    <?php echo htmlspecialchars($record['health_worker_name']); ?>
                                </div>
                                <div style="color: #7f8c8d; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($record['health_worker_position']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="table-cell-content">
                                    <?php echo nl2br(htmlspecialchars($record['chief_complaint'] ?? 'Not specified')); ?>
                                </div>
                            </td>
                            <td>
                                <div class="table-cell-content">
                                    <?php echo nl2br(htmlspecialchars($record['diagnosis'] ?? 'Not specified')); ?>
                                </div>
                            </td>
                            <td>
                                <div class="table-cell-content">
                                    <?php echo nl2br(htmlspecialchars($record['treatment'] ?? 'Not specified')); ?>
                                    <?php if (!empty($record['prescription'])): ?>
                                        <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #eee;">
                                            <strong>Prescription:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($record['prescription'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($record['follow_up_date'])): ?>
                                    <div class="table-follow-up">
                                        <?php echo date('M j, Y', strtotime($record['follow_up_date'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                                        <?php 
                                        $today = new DateTime();
                                        $follow_up = new DateTime($record['follow_up_date']);
                                        $interval = $today->diff($follow_up);
                                        
                                        if ($follow_up < $today) {
                                            echo 'Overdue';
                                        } else {
                                            echo 'In ' . $interval->days . ' days';
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">None</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Mobile Card View -->
            <div class="medical-records-grid">
                <?php foreach ($medical_records as $record): ?>
                    <div class="medical-record">
                        <div class="record-header">
                            <div class="record-date">
                                <?php echo date('l, M j, Y', strtotime($record['visit_date'])); ?>
                            </div>
                            <div class="record-doctor">
                                Attended by <?php echo htmlspecialchars($record['health_worker_name']); ?>
                                <div class="text-muted"><?php echo htmlspecialchars($record['health_worker_position']); ?></div>
                            </div>
                            <?php if (!empty($record['follow_up_date'])): ?>
                                <div class="follow-up-badge">
                                    <span class="badge bg-info text-white">
                                        <i class="fas fa-calendar-check"></i>
                                        Follow-up: <?php echo date('M j, Y', strtotime($record['follow_up_date'])); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="record-section">
                            <h4><i class="fas fa-comment-medical"></i> Chief Complaint</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['chief_complaint'] ?? 'Not specified')); ?></p>
                        </div>

                        <div class="record-section">
                            <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['diagnosis'] ?? 'Not specified')); ?></p>
                        </div>

                        <div class="record-section">
                            <h4><i class="fas fa-pills"></i> Treatment</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['treatment'] ?? 'Not specified')); ?></p>
                        </div>

                        <?php if (!empty($record['prescription'])): ?>
                        <div class="record-section">
                            <h4><i class="fas fa-prescription"></i> Prescription</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($record['notes'])): ?>
                        <div class="record-section">
                            <h4><i class="fas fa-sticky-note"></i> Additional Notes</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($record['follow_up_date'])): ?>
                        <div class="follow-up">
                            <i class="fas fa-calendar-day"></i>
                            <div>
                                <strong>Follow-up Appointment:</strong> <?php echo date('l, F j, Y', strtotime($record['follow_up_date'])); ?>
                                <?php 
                                $today = new DateTime();
                                $follow_up = new DateTime($record['follow_up_date']);
                                $interval = $today->diff($follow_up);
                                
                                if ($follow_up < $today) {
                                    echo '<div class="text-muted">This follow-up date has passed</div>';
                                } else {
                                    echo '<div class="text-muted">Coming up in ' . $interval->days . ' days</div>';
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html> 