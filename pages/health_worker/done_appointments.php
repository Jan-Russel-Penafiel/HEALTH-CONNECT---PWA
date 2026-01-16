<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get health worker ID from the database
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
    header('Location: /connect/pages/login.php');
    exit();
}

try {
    // Get completed appointments
    $query = "SELECT a.appointment_id as id, a.appointment_date, a.appointment_time, a.notes, a.status_id, a.reason,
                     u.first_name, u.last_name, u.email, u.mobile_number as patient_phone,
                     s.status_name as status
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.health_worker_id = ? AND a.status_id = 3
              ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
}

// Get working hours for time filter
$working_hours = [
    'start' => '09:00',
    'end' => '17:00',
    'interval' => 30
];

try {
    $settings_query = "SELECT name, value FROM settings WHERE name IN ('working_hours_start', 'working_hours_end', 'appointment_duration')";
    $stmt = $pdo->query($settings_query);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (isset($settings['working_hours_start'])) {
        $working_hours['start'] = $settings['working_hours_start'];
    }
    if (isset($settings['working_hours_end'])) {
        $working_hours['end'] = $settings['working_hours_end'];
    }
    if (isset($settings['appointment_duration'])) {
        $working_hours['interval'] = (int)$settings['appointment_duration'];
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Appointments - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <!-- jsPDF for printing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <style>
        /* Desktop Table Layout */
        .table-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-top: 1rem;
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .appointments-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        .appointments-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }

        .appointments-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .patient-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .contact-info {
            font-size: 0.85rem;
            color: #666;
        }

        .contact-info div {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.2rem;
        }

        .datetime-info {
            white-space: nowrap;
        }

        .appointment-date {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .appointment-time {
            color: #666;
            font-size: 0.9rem;
        }

        .reason-cell {
            max-width: 200px;
            word-wrap: break-word;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: #adb5bd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }

        .empty-state p {
            color: #6c757d;
            margin: 0;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        /* Filter Toolbar Styles */
        .filter-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-group label {
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .filter-input,
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
        }

        .filter-input {
            min-width: 200px;
        }

        .filter-select {
            cursor: pointer;
            min-width: 150px;
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn-print {
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-print:hover {
            background: #1976D2;
        }

        .appointment-row-hidden {
            display: none !important;
        }

        @media (max-width: 768px) {
            .filter-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-input,
            .filter-select {
                width: 100%;
            }
        }

        /* Mobile Card Layout */
        .appointments-grid {
            display: none;
        }

        .appointment-card.appointment-row-hidden {
            display: none !important;
        }

        @media (max-width: 991px) {
            .table-container {
                display: none;
            }

            .appointments-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem 0;
            }

            .appointment-card {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                padding: 1.5rem;
                position: relative;
            }

            .appointment-time {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 1.25rem;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 1rem;
            }

            .appointment-date {
                color: #666;
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }

            .patient-info {
                margin-bottom: 1rem;
            }

            .patient-info h3 {
                margin: 0 0 0.5rem 0;
                color: #2c3e50;
                font-size: 1.1rem;
            }

            .contact-details {
                font-size: 0.9rem;
                color: #666;
            }

            .contact-details div {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 0.25rem;
            }

            .appointment-reason {
                margin: 1rem 0;
                padding: 0.75rem;
                background: #f8f9fa;
                border-radius: 4px;
            }

            .appointment-reason p {
                margin: 0.5rem 0 0 0;
                color: #666;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../../includes/today_appointments_banner.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Completed Appointments</h1>
            <div class="header-actions">
                <a href="appointments.php" class="btn btn-primary">
                    <i class="fas fa-calendar"></i> Active Appointments
                </a>
            </div>
        </div>

        <?php if (empty($appointments)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h3>No Completed Appointments</h3>
            <p>There are no completed appointments to display.</p>
        </div>
        <?php else: ?>
        
        <!-- Filter Section -->
        <div class="filter-toolbar">
            <div class="filter-group">
                <label for="searchInput"><i class="fas fa-search"></i> Search:</label>
                <input type="text" id="searchInput" class="filter-input" placeholder="Search patient name, phone, email..." oninput="filterAppointments()">
                
                <label for="dateFilter"><i class="fas fa-calendar"></i> Date:</label>
                <input type="date" id="dateFilter" class="filter-input" onchange="filterAppointments()">
                
                <label for="timeFilter"><i class="fas fa-clock"></i> Time:</label>
                <select id="timeFilter" class="filter-select" onchange="filterAppointments()">
                    <option value="all">All Times</option>
                    <?php
                    $start = strtotime($working_hours['start']);
                    $end = strtotime($working_hours['end']);
                    $interval = $working_hours['interval'] * 60;
                    for ($time = $start; $time < $end; $time += $interval) {
                        echo '<option value="' . date('H:i', $time) . '">' . date('g:i A', $time) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn-print" onclick="printFilteredAppointments()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Desktop Table Layout -->
        <div class="table-container">
            <table class="appointments-table" id="appointmentsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient Information</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): 
                        $timeValue = date('H:i', strtotime($appointment['appointment_time']));
                        $dateValue = date('Y-m-d', strtotime($appointment['appointment_date']));
                        $patientName = $appointment['first_name'] . ' ' . $appointment['last_name'];
                    ?>
                    <tr data-appointment-time="<?php echo $timeValue; ?>" 
                        data-appointment-date="<?php echo $dateValue; ?>" 
                        data-patient-name="<?php echo htmlspecialchars($patientName); ?>" 
                        data-patient-email="<?php echo htmlspecialchars($appointment['email']); ?>" 
                        data-patient-phone="<?php echo htmlspecialchars($appointment['patient_phone']); ?>">
                        <td>
                            <div class="datetime-info">
                                <div class="appointment-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                </div>
                                <div class="appointment-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="patient-name">
                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                            </div>
                            <div class="contact-info">
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></div>
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                            </div>
                        </td>
                        <td class="reason-cell">
                            <?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card Layout -->
        <div class="appointments-grid">
            <?php foreach ($appointments as $appointment): 
                $timeValue = date('H:i', strtotime($appointment['appointment_time']));
                $dateValue = date('Y-m-d', strtotime($appointment['appointment_date']));
                $patientName = $appointment['first_name'] . ' ' . $appointment['last_name'];
            ?>
            <div class="appointment-card" 
                 data-appointment-time="<?php echo $timeValue; ?>" 
                 data-appointment-date="<?php echo $dateValue; ?>" 
                 data-patient-name="<?php echo htmlspecialchars($patientName); ?>" 
                 data-patient-email="<?php echo htmlspecialchars($appointment['email']); ?>" 
                 data-patient-phone="<?php echo htmlspecialchars($appointment['patient_phone']); ?>">
                <div class="appointment-date">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></span>
                </div>
                
                <div class="appointment-time">
                    <i class="fas fa-clock"></i>
                    <span><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
                </div>
                
                <div class="patient-info">
                    <h3><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></h3>
                    <div class="contact-details">
                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></div>
                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                    </div>
                </div>
                
                <div class="appointment-reason">
                    <strong>Reason:</strong>
                    <p><?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?></p>
                </div>
                
                <div class="appointment-actions">
                    <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-info w-100">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
    function filterAppointments() {
        const searchValue = document.getElementById('searchInput').value.toLowerCase();
        const dateValue = document.getElementById('dateFilter').value;
        const timeValue = document.getElementById('timeFilter').value;
        
        // Get all rows (both table and cards)
        const tableRows = document.querySelectorAll('#appointmentsTable tbody tr');
        const cards = document.querySelectorAll('.appointments-grid .appointment-card');
        
        // Filter table rows
        tableRows.forEach(row => {
            const patientName = row.dataset.patientName?.toLowerCase() || '';
            const patientEmail = row.dataset.patientEmail?.toLowerCase() || '';
            const patientPhone = row.dataset.patientPhone?.toLowerCase() || '';
            const rowDate = row.dataset.appointmentDate || '';
            const rowTime = row.dataset.appointmentTime || '';
            
            let showRow = true;
            
            // Search filter
            if (searchValue) {
                showRow = patientName.includes(searchValue) || 
                         patientEmail.includes(searchValue) || 
                         patientPhone.includes(searchValue);
            }
            
            // Date filter
            if (showRow && dateValue) {
                showRow = rowDate === dateValue;
            }
            
            // Time filter
            if (showRow && timeValue !== 'all') {
                showRow = rowTime === timeValue;
            }
            
            if (showRow) {
                row.classList.remove('appointment-row-hidden');
            } else {
                row.classList.add('appointment-row-hidden');
            }
        });
        
        // Filter cards
        cards.forEach(card => {
            const patientName = card.dataset.patientName?.toLowerCase() || '';
            const patientEmail = card.dataset.patientEmail?.toLowerCase() || '';
            const patientPhone = card.dataset.patientPhone?.toLowerCase() || '';
            const cardDate = card.dataset.appointmentDate || '';
            const cardTime = card.dataset.appointmentTime || '';
            
            let showCard = true;
            
            // Search filter
            if (searchValue) {
                showCard = patientName.includes(searchValue) || 
                          patientEmail.includes(searchValue) || 
                          patientPhone.includes(searchValue);
            }
            
            // Date filter
            if (showCard && dateValue) {
                showCard = cardDate === dateValue;
            }
            
            // Time filter
            if (showCard && timeValue !== 'all') {
                showCard = cardTime === timeValue;
            }
            
            if (showCard) {
                card.classList.remove('appointment-row-hidden');
            } else {
                card.classList.add('appointment-row-hidden');
            }
        });
        
        // Update count
        const visibleRows = document.querySelectorAll('#appointmentsTable tbody tr:not(.appointment-row-hidden)');
        console.log(`Showing ${visibleRows.length} completed appointments`);
    }
    
    function printFilteredAppointments() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Get filter values for title
        const searchValue = document.getElementById('searchInput').value;
        const dateValue = document.getElementById('dateFilter').value;
        const timeSelect = document.getElementById('timeFilter');
        const timeValue = timeSelect.value;
        const timeText = timeValue === 'all' ? 'All Times' : timeSelect.options[timeSelect.selectedIndex].text;
        
        // Title
        doc.setFontSize(18);
        doc.setTextColor(76, 175, 80);
        doc.text('HealthConnect - Completed Appointments', 14, 20);
        
        // Subtitle with filter info
        doc.setFontSize(11);
        doc.setTextColor(100);
        let filterInfo = [];
        if (searchValue) filterInfo.push(`Search: ${searchValue}`);
        if (dateValue) filterInfo.push(`Date: ${new Date(dateValue + 'T00:00:00').toLocaleDateString()}`);
        if (timeValue !== 'all') filterInfo.push(`Time: ${timeText}`);
        
        const filterText = filterInfo.length > 0 ? filterInfo.join(' | ') : 'All Appointments';
        doc.text(`Filters: ${filterText}`, 14, 28);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 34);
        
        // Collect visible appointment data
        const rows = document.querySelectorAll('#appointmentsTable tbody tr:not(.appointment-row-hidden)');
        const tableData = [];
        
        rows.forEach((row, index) => {
            const dateTime = row.querySelector('.appointment-time i')?.nextSibling?.textContent.trim() || '';
            const date = row.querySelector('.appointment-date')?.textContent.trim() || '';
            const dateOnly = date.replace(/.*?\s/, '').trim(); // Remove icon
            const patient = row.dataset.patientName || '';
            const phone = row.dataset.patientPhone || '';
            const reason = row.querySelector('.reason-cell')?.textContent.trim() || '';
            
            tableData.push([
                index + 1,
                `${dateTime}\n${dateOnly}`,
                patient,
                phone,
                reason
            ]);
        });
        
        // Create table
        doc.autoTable({
            startY: 42,
            head: [['#', 'Time & Date', 'Patient', 'Phone', 'Reason']],
            body: tableData,
            theme: 'striped',
            headStyles: {
                fillColor: [76, 175, 80],
                textColor: 255,
                fontStyle: 'bold'
            },
            styles: {
                fontSize: 9,
                cellPadding: 4,
            },
            columnStyles: {
                0: { cellWidth: 10, halign: 'center' },
                1: { cellWidth: 35 },
                2: { cellWidth: 45 },
                3: { cellWidth: 35 },
                4: { cellWidth: 50 }
            },
            didDrawPage: function(data) {
                // Footer
                doc.setFontSize(8);
                doc.setTextColor(150);
                doc.text(
                    `Page ${doc.internal.getNumberOfPages()}`,
                    doc.internal.pageSize.width / 2,
                    doc.internal.pageSize.height - 10,
                    { align: 'center' }
                );
            }
        });
        
        // Add summary
        const finalY = doc.lastAutoTable.finalY || 42;
        doc.setFontSize(10);
        doc.setTextColor(60);
        doc.text(`Total Completed Appointments: ${tableData.length}`, 14, finalY + 10);
        
        // Save PDF
        const fileName = `completed_appointments_${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(fileName);
        
        console.log(`PDF downloaded: ${fileName}`);
    }
    </script>
</body>
</html> 