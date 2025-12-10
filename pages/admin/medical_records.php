<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch medical records with search functionality
try {
    $params = [];
    $whereClause = "1=1";
    
    if (!empty($search)) {
        $whereClause .= " AND (
            u.first_name LIKE :search 
            OR u.last_name LIKE :search 
            OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search
            OR mr.diagnosis LIKE :search
            OR mr.treatment LIKE :search
            OR mr.prescription LIKE :search
            OR mr.notes LIKE :search
            OR hw_user.first_name LIKE :search
            OR hw_user.last_name LIKE :search
            OR CONCAT(hw_user.first_name, ' ', hw_user.last_name) LIKE :search
        )";
        $params[':search'] = "%$search%";
    }
    
    $query = "SELECT 
                mr.*,
                CONCAT(u.first_name, ' ', IFNULL(CONCAT(u.middle_name, ' '), ''), u.last_name) as patient_name,
                u.mobile_number as patient_contact,
                CONCAT(hw_user.first_name, ' ', IFNULL(CONCAT(hw_user.middle_name, ' '), ''), hw_user.last_name) as health_worker_name,
                mr.created_at as record_date
              FROM medical_records mr
              JOIN patients p ON mr.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
              JOIN users hw_user ON hw.user_id = hw_user.user_id
              WHERE $whereClause
              ORDER BY mr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching medical records: " . $e->getMessage());
    $medical_records = [];
    $_SESSION['error'] = "Error fetching medical records. Please try again.";
}

// Handle export to PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    // Configure Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // Build HTML for PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 10pt;
                line-height: 1.4;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #4CAF50;
            }
            .header h1 {
                color: #4CAF50;
                margin: 0;
                font-size: 24pt;
            }
            .header p {
                color: #666;
                margin: 5px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th {
                background-color: #4CAF50;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            td {
                padding: 8px;
                border-bottom: 1px solid #ddd;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 9pt;
                color: #666;
            }
            .record-detail {
                page-break-inside: avoid;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>HealthConnect</h1>
            <p>Medical Records Report</p>
            <p>Generated on ' . date('F d, Y \a\t h:i A') . '</p>';
    
    if (!empty($search)) {
        $html .= '<p><em>Filtered by: "' . htmlspecialchars($search) . '"</em></p>';
    }
    
    $html .= '</div>';
    
    if (!empty($medical_records)) {
        $html .= '<table>
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 18%;">Patient</th>
                    <th style="width: 18%;">Health Worker</th>
                    <th style="width: 22%;">Diagnosis</th>
                    <th style="width: 22%;">Treatment</th>
                    <th style="width: 12%;">Date</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($medical_records as $record) {
            $html .= '<tr class="record-detail">
                <td>' . htmlspecialchars($record['record_id']) . '</td>
                <td>' . htmlspecialchars($record['patient_name']) . '<br><small>' . htmlspecialchars($record['patient_contact']) . '</small></td>
                <td>' . htmlspecialchars($record['health_worker_name']) . '</td>
                <td>' . nl2br(htmlspecialchars($record['diagnosis'])) . '</td>
                <td>' . nl2br(htmlspecialchars($record['treatment'])) . '</td>
                <td>' . date('M d, Y', strtotime($record['record_date'])) . '</td>
            </tr>';
            
            // Add prescription and notes if available
            if (!empty($record['prescription']) || !empty($record['notes'])) {
                $html .= '<tr class="record-detail">
                    <td colspan="6" style="background-color: #f0f0f0; font-size: 9pt;">';
                
                if (!empty($record['prescription'])) {
                    $html .= '<strong>Prescription:</strong> ' . nl2br(htmlspecialchars($record['prescription'])) . '<br>';
                }
                
                if (!empty($record['notes'])) {
                    $html .= '<strong>Notes:</strong> ' . nl2br(htmlspecialchars($record['notes']));
                }
                
                $html .= '</td>
                </tr>';
            }
        }
        
        $html .= '</tbody>
        </table>';
    } else {
        $html .= '<p style="text-align: center; margin-top: 50px; color: #666;">No medical records found.</p>';
    }
    
    $html .= '<div class="footer">
            <p>Total Records: ' . count($medical_records) . '</p>
            <p>&copy; ' . date('Y') . ' HealthConnect. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    // Load HTML to Dompdf
    $dompdf->loadHtml($html);
    
    // Set paper size
    $dompdf->setPaper('A4', 'landscape');
    
    // Render PDF
    $dompdf->render();
    
    // Output PDF
    $dompdf->stream('medical_records_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records Management</title>
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
        
        .table-actions .btn-view {
            background: #4a90e2;
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
        
        .record-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .record-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .record-card .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .record-card .name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .record-card .date {
            color: #666;
            font-style: italic;
            margin: 5px 0;
            font-size: 0.9em;
        }
        
        .record-card .info-item {
            display: flex;
            align-items: flex-start;
            margin: 10px 0;
            color: #555;
        }
        
        .record-card .info-item i {
            width: 20px;
            margin-right: 10px;
            color: #4a90e2;
            margin-top: 3px;
        }
        
        .record-card .info-item .label {
            font-weight: bold;
            margin-right: 5px;
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
        
        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .export-btn:hover {
            background: #218838;
        }
        
        .search-results-info {
            margin-top: 10px;
            color: #666;
            font-style: italic;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
                width: 100%;
                padding: 12px;
            }
            
            .search-form {
                width: 100%;
            }
            
            .search-reset {
                width: 100%;
                justify-content: center;
            }
            
            .export-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Medical Records</h1>
            <div class="header-actions">
                <?php if (!empty($medical_records)): ?>
                <a href="?export=pdf<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="export-btn">
                    <i class="fas fa-download"></i> Export to PDF
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Search Form -->
        <div class="search-container">
            <form class="search-form" method="GET">
                <div class="search-input-container">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        placeholder="Search by patient name, diagnosis, treatment, health worker..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    <button type="submit" class="search-button">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <?php if (!empty($search)): ?>
                <a href="?" class="search-reset">
                    <i class="fas fa-times"></i> Clear Search
                </a>
                <div class="search-results-info">
                    Showing <?php echo count($medical_records); ?> result(s) for "<?php echo htmlspecialchars($search); ?>"
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Desktop Table View -->
        <div class="desktop-table">
            <?php if (!empty($medical_records)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Patient</th>
                        <th>Health Worker</th>
                        <th>Diagnosis</th>
                        <th>Treatment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medical_records as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['record_id']); ?></td>
                        <td><?php echo htmlspecialchars($record['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($record['health_worker_name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 50)) . (strlen($record['diagnosis']) > 50 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars(substr($record['treatment'], 0, 50)) . (strlen($record['treatment']) > 50 ? '...' : ''); ?></td>
                        <td><?php echo date('M d, Y', strtotime($record['record_date'])); ?></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn-action btn-view" onclick="viewRecord(<?php echo htmlspecialchars(json_encode($record)); ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-medical" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                <p><?php echo !empty($search) ? 'No medical records found matching your search.' : 'No medical records available.'; ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Cards View -->
        <div class="mobile-cards">
            <div class="cards-grid">
            <?php if (!empty($medical_records)): ?>
                <?php foreach ($medical_records as $record): ?>
                <div class="record-card">
                    <div class="header">
                        <p class="name"><?php echo htmlspecialchars($record['patient_name']); ?></p>
                        <p class="date">
                            <i class="fas fa-calendar"></i>
                            <?php echo date('F d, Y', strtotime($record['record_date'])); ?>
                        </p>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-user-md"></i>
                        <div>
                            <span class="label">Health Worker:</span>
                            <?php echo htmlspecialchars($record['health_worker_name']); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-stethoscope"></i>
                        <div>
                            <span class="label">Diagnosis:</span>
                            <?php echo htmlspecialchars($record['diagnosis']); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-pills"></i>
                        <div>
                            <span class="label">Treatment:</span>
                            <?php echo htmlspecialchars($record['treatment']); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($record['prescription'])): ?>
                    <div class="info-item">
                        <i class="fas fa-prescription"></i>
                        <div>
                            <span class="label">Prescription:</span>
                            <?php echo htmlspecialchars($record['prescription']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($record['notes'])): ?>
                    <div class="info-item">
                        <i class="fas fa-notes-medical"></i>
                        <div>
                            <span class="label">Notes:</span>
                            <?php echo htmlspecialchars($record['notes']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <span class="label">Contact:</span>
                            <?php echo htmlspecialchars($record['patient_contact']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-file-medical" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <p><?php echo !empty($search) ? 'No medical records found matching your search.' : 'No medical records available.'; ?></p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- View Modal -->
    <div id="recordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Medical Record Details</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div id="recordDetails" style="padding: 20px;">
                <!-- Details will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        const modal = document.getElementById('recordModal');
        const modalClose = document.querySelector('.modal-close');
        
        function viewRecord(record) {
            const detailsHtml = `
                <div style="line-height: 1.8;">
                    <p><strong>Record ID:</strong> ${record.record_id}</p>
                    <p><strong>Patient:</strong> ${record.patient_name}</p>
                    <p><strong>Contact:</strong> ${record.patient_contact}</p>
                    <p><strong>Health Worker:</strong> ${record.health_worker_name}</p>
                    <p><strong>Date:</strong> ${new Date(record.record_date).toLocaleString()}</p>
                    <hr style="margin: 15px 0;">
                    <p><strong>Diagnosis:</strong></p>
                    <p style="margin-left: 20px;">${record.diagnosis || 'N/A'}</p>
                    <p><strong>Treatment:</strong></p>
                    <p style="margin-left: 20px;">${record.treatment || 'N/A'}</p>
                    ${record.prescription ? `<p><strong>Prescription:</strong></p><p style="margin-left: 20px;">${record.prescription}</p>` : ''}
                    ${record.notes ? `<p><strong>Notes:</strong></p><p style="margin-left: 20px;">${record.notes}</p>` : ''}
                </div>
            `;
            document.getElementById('recordDetails').innerHTML = detailsHtml;
            modal.style.display = 'block';
        }
        
        function closeModal() {
            modal.style.display = 'none';
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
