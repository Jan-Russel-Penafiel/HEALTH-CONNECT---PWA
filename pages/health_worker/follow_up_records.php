<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../../includes/auth_check.php';

// Check if user is health worker or admin
if (!in_array($_SESSION['role'], ['health_worker', 'admin'])) {
    header("Location: ../dashboard.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Get health worker ID if not already in session
if ($_SESSION['role'] === 'health_worker' && !isset($_SESSION['health_worker_id'])) {
    try {
        $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['health_worker_id'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching health worker ID: " . $e->getMessage());
    }
}

// Fetch all patients with follow-up appointments
$query = "SELECT 
            mr.record_id,
            mr.patient_id,
            mr.visit_date,
            mr.follow_up_date,
            mr.notes,
            mr.diagnosis,
            u.first_name,
            u.last_name,
            u.mobile_number,
            u.date_of_birth,
            DATEDIFF(mr.follow_up_date, CURDATE()) as days_until_followup
          FROM medical_records mr
          JOIN patients p ON mr.patient_id = p.patient_id
          JOIN users u ON p.user_id = u.user_id
          WHERE mr.follow_up_date IS NOT NULL
          AND mr.follow_up_date >= CURDATE()
          ORDER BY mr.follow_up_date ASC";
          
$stmt = $conn->prepare($query);
$stmt->execute();
$followup_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process records to extract follow-up messages from JSON notes
foreach ($followup_records as &$record) {
    $notes_data = json_decode($record['notes'], true);
    if ($notes_data && isset($notes_data['follow_up_message'])) {
        $record['follow_up_message'] = $notes_data['follow_up_message'];
        $record['regular_notes'] = $notes_data['notes'] ?? '';
    } else {
        $record['follow_up_message'] = '';
        $record['regular_notes'] = $record['notes'];
    }
    
    // Calculate age
    if ($record['date_of_birth']) {
        $dob = new DateTime($record['date_of_birth']);
        $now = new DateTime();
        $record['age'] = $now->diff($dob)->y;
    } else {
        $record['age'] = 'N/A';
    }
    
    // Check if reminder was already sent today
    $reminder_flag = 'reminder_sent_' . date('Y-m-d');
    $record['reminder_sent'] = ($notes_data && isset($notes_data[$reminder_flag]) && $notes_data[$reminder_flag] === true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Follow-up Records - HealthConnect</title>
    <?php include '../../includes/header_links.php'; ?>
    <style>
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #2c3e50;
        }

        .page-header h1 i {
            font-size: 1.5rem;
            color: #4CAF50;
        }



        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background-color: #fff;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        /* Records Grid */
        .records-grid {
            display: grid;
            gap: 20px;
        }

        .record-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .record-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .record-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: start;
            flex-wrap: wrap;
            gap: 15px;
        }

        .patient-info-header {
            flex: 1;
            min-width: 250px;
        }

        .patient-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .patient-name i {
            color: #4CAF50;
        }

        .patient-demographics {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .demographic-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .demographic-item i {
            color: #4CAF50;
        }

        .urgency-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .urgency-badge.urgent {
            background: #ffebee;
            color: #c62828;
        }

        .urgency-badge.soon {
            background: #fff3e0;
            color: #e65100;
        }

        .urgency-badge.upcoming {
            background: #e3f2fd;
            color: #1976d2;
        }

        .record-body {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .info-label i {
            color: #4CAF50;
        }

        .info-value {
            color: #2c3e50;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .doctor-message {
            background: #f8f9fa;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }

        .doctor-message .label {
            font-weight: 600;
            color: #4CAF50;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .doctor-message .message {
            color: #495057;
            font-style: italic;
            line-height: 1.5;
        }

        .record-footer {
            padding: 15px 20px;
            background: #fafafa;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .contact-info i {
            color: #4CAF50;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #17a2b8;
            color: white;
        }

        .btn-primary:hover {
            background: #138496;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        
        .reminder-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #d4edda;
            color: #155724;
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 10px;
            padding: 60px 30px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #6c757d;
            margin: 0 0 10px 0;
            font-size: 1.25rem;
        }

        .empty-state p {
            color: #adb5bd;
            margin: 0;
            font-size: 0.95rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.3rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .record-header {
                flex-direction: column;
                padding: 15px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .record-footer {
                flex-direction: column;
                align-items: stretch;
                padding: 15px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .urgency-badge {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/today_appointments_banner.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>
                <i class="fas fa-calendar-plus"></i>
                Follow-up Records
            </h1>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchPatient">Search Patient</label>
                    <input type="text" id="searchPatient" placeholder="Enter patient name...">
                </div>
                <div class="filter-group">
                    <label for="filterUrgency">Filter by Urgency</label>
                    <select id="filterUrgency">
                        <option value="all">All Follow-ups</option>
                        <option value="urgent">Urgent (≤ 2 days)</option>
                        <option value="soon">This Week (3-7 days)</option>
                        <option value="upcoming">Later (> 7 days)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Records Grid -->
        <div class="records-grid" id="recordsGrid">
            <?php if (empty($followup_records)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <h3>No Follow-up Records</h3>
                    <p>There are no scheduled follow-up appointments at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($followup_records as $record): ?>
                    <?php
                    $urgency_class = 'upcoming';
                    $urgency_text = 'Upcoming';
                    $urgency_icon = 'fa-calendar';
                    
                    if ($record['days_until_followup'] <= 2) {
                        $urgency_class = 'urgent';
                        $urgency_text = $record['days_until_followup'] == 0 ? 'Today' : ($record['days_until_followup'] == 1 ? 'Tomorrow' : '2 Days');
                        $urgency_icon = 'fa-exclamation-triangle';
                    } elseif ($record['days_until_followup'] <= 7) {
                        $urgency_class = 'soon';
                        $urgency_text = $record['days_until_followup'] . ' Days';
                        $urgency_icon = 'fa-clock';
                    } else {
                        $urgency_text = $record['days_until_followup'] . ' Days';
                    }
                    ?>
                    
                    <div class="record-card" data-urgency="<?php echo $urgency_class; ?>" data-patient-name="<?php echo strtolower($record['first_name'] . ' ' . $record['last_name']); ?>">
                        <div class="record-header">
                            <div class="patient-info-header">
                                <h2 class="patient-name">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                </h2>
                                <div class="patient-demographics">
                                    <span class="demographic-item">
                                        <i class="fas fa-birthday-cake"></i>
                                        <?php echo $record['age']; ?> years old
                                    </span>
                                    <span class="demographic-item">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($record['mobile_number']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="urgency-badge <?php echo $urgency_class; ?>">
                                <i class="fas <?php echo $urgency_icon; ?>"></i>
                                <?php echo $urgency_text; ?>
                            </div>
                        </div>
                        
                        <div class="record-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-calendar-day"></i>
                                        Follow-up Date
                                    </span>
                                    <span class="info-value">
                                        <?php echo date('F j, Y', strtotime($record['follow_up_date'])); ?>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-calendar-alt"></i>
                                        Last Visit
                                    </span>
                                    <span class="info-value">
                                        <?php echo date('F j, Y', strtotime($record['visit_date'])); ?>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-stethoscope"></i>
                                        Diagnosis
                                    </span>
                                    <span class="info-value">
                                        <?php echo htmlspecialchars($record['diagnosis']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($record['follow_up_message'])): ?>
                                <div class="doctor-message">
                                    <div class="label">
                                        <i class="fas fa-user-md"></i>
                                        Doctor's Note
                                    </div>
                                    <div class="message">
                                        "<?php echo htmlspecialchars($record['follow_up_message']); ?>"
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="record-footer">
                            <div class="contact-info">
                                <i class="fas fa-mobile-alt"></i>
                                <span><?php echo htmlspecialchars($record['mobile_number']); ?></span>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <a href="/connect/pages/health_worker/view_medical_history.php?patient_id=<?php echo $record['patient_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-file-medical"></i>
                                    View History
                                </a>
                                <?php if ($record['days_until_followup'] == 0 && $record['reminder_sent']): ?>
                                    <span class="reminder-status">
                                        <i class="fas fa-check-circle"></i>
                                        Auto-Reminder Sent Today
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Search functionality
        document.getElementById('searchPatient').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            filterRecords();
        });
        
        // Filter functionality
        document.getElementById('filterUrgency').addEventListener('change', function() {
            filterRecords();
        });
        
        function filterRecords() {
            const searchTerm = document.getElementById('searchPatient').value.toLowerCase();
            const urgencyFilter = document.getElementById('filterUrgency').value;
            const cards = document.querySelectorAll('.record-card');
            
            cards.forEach(card => {
                const patientName = card.getAttribute('data-patient-name');
                const urgency = card.getAttribute('data-urgency');
                
                const matchesSearch = patientName.includes(searchTerm);
                const matchesUrgency = urgencyFilter === 'all' || urgency === urgencyFilter;
                
                if (matchesSearch && matchesUrgency) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
