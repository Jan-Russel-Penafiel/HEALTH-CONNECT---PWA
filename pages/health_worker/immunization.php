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
        // If no health worker record found, redirect to login
        header('Location: /connect/pages/login.php');
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    header('Location: /connect/pages/login.php');
    exit();
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR it.name LIKE ?)";
    $search_param = "%$search%";
    $params = [$health_worker_id, $search_param, $search_param, $search_param];
} else {
    $params = [$health_worker_id];
}

try {
    // Get immunization records with age calculation
    $query = "SELECT ir.*, 
              CONCAT(u.first_name, ' ', u.last_name) as patient_name, 
              u.email as patient_email,
              it.name as immunization_name, 
              it.description as immunization_description,
              TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as age
              FROM immunization_records ir 
              JOIN patients p ON ir.patient_id = p.patient_id 
              JOIN users u ON p.user_id = u.user_id 
              JOIN immunization_types it ON ir.immunization_type_id = it.immunization_type_id 
              WHERE ir.health_worker_id = ? $where_clause 
              ORDER BY ir.date_administered DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate immunizations into children and adults
    $child_immunizations = array_filter($immunizations, function($record) {
        return $record['age'] < 18;
    });
    
    $adult_immunizations = array_filter($immunizations, function($record) {
        return $record['age'] >= 18;
    });

    // Get immunization types for the dropdown
    $query = "SELECT * FROM immunization_types ORDER BY name ASC";
    $stmt = $pdo->query($query);
    $immunization_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get patients list for the modal
    $query = "SELECT p.patient_id, u.first_name, u.last_name, u.email 
              FROM patients p 
              JOIN users u ON p.user_id = u.user_id 
              WHERE u.is_active = 1 
              ORDER BY u.last_name, u.first_name";
    $stmt = $pdo->query($query);
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching immunization data: " . $e->getMessage());
    $immunizations = [];
    $immunization_types = [];
    $patients_list = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $next_schedule_date = !empty($_POST['next_schedule_date']) ? $_POST['next_schedule_date'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO immunization_records (patient_id, health_worker_id, immunization_type_id, dose_number, date_administered, next_schedule_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['patient_id'],
            $health_worker_id,
            $_POST['immunization_type_id'],
            $_POST['dose_number'] ?? 1,
            $_POST['date'],
            $next_schedule_date,
            $_POST['notes']
        ]);
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
        exit();
    } catch (PDOException $e) {
        error_log("Error adding immunization record: " . $e->getMessage());
        $error = "Error adding immunization record: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immunization Records - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Immunization Records</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="showAddImmunizationModal()">
                    <i class="fas fa-plus"></i> Add Immunization
                </button>
                <div class="filter-actions">
                    <div class="filter-buttons">
                        <button class="btn btn-filter active" data-filter="all">
                            <i class="fas fa-list"></i> All
                        </button>
                        <button class="btn btn-filter" data-filter="children">
                            <i class="fas fa-child"></i> Children
                        </button>
                        <button class="btn btn-filter" data-filter="adults">
                            <i class="fas fa-user"></i> Adults
                        </button>
                    </div>
                <form class="search-form" action="" method="GET">
                    <input type="text" name="search" placeholder="Search records..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Immunization record added successfully.
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <div class="immunization-sections">
            <!-- Children's Section -->
            <div class="immunization-section">
                <h2><i class="fas fa-child"></i> Children's Immunizations</h2>
        <div class="immunization-grid">
                    <?php if (empty($child_immunizations)): ?>
            <div class="empty-state">
                <i class="fas fa-syringe"></i>
                        <h3>No Children's Immunization Records Found</h3>
                        <p>There are no immunization records for children matching your search criteria.</p>
            </div>
            <?php else: ?>
                        <?php foreach ($child_immunizations as $record): ?>
                <div class="immunization-card">
                    <div class="immunization-date">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('M d, Y', strtotime($record['date_administered'])); ?></span>
                    </div>
                    
                    <div class="patient-info">
                        <h3><?php echo htmlspecialchars($record['patient_name']); ?></h3>
                        <div class="email">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($record['patient_email']); ?>
                        </div>
                                <div class="age">
                                    <i class="fas fa-birthday-cake"></i>
                                    <?php echo htmlspecialchars($record['age']); ?> years old
                                </div>
                    </div>
                    
                    <div class="immunization-details">
                        <div class="immunization-type">
                            <h4><?php echo htmlspecialchars($record['immunization_name']); ?></h4>
                            <p class="description"><?php echo htmlspecialchars($record['immunization_description']); ?></p>
                        </div>
                        
                        <div class="next-schedule">
                            <strong><i class="fas fa-clock"></i> Next Schedule:</strong>
                            <?php if (!empty($record['next_schedule_date'])): ?>
                                <span class="date"><?php echo date('M d, Y', strtotime($record['next_schedule_date'])); ?></span>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($record['notes'])): ?>
                        <div class="notes">
                            <strong><i class="fas fa-sticky-note"></i> Notes:</strong>
                            <p><?php echo htmlspecialchars($record['notes']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="immunization-actions">
                        <a href="view_immunization.php?id=<?php echo $record['immunization_record_id']; ?>" class="btn btn-view" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <button class="btn btn-danger" onclick="deleteRecord(<?php echo $record['immunization_record_id']; ?>)" title="Delete Record">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
            </div>

            <!-- Adults' Section -->
            <div class="immunization-section">
                <h2><i class="fas fa-user"></i> Adult Immunizations</h2>
                <div class="immunization-grid">
                    <?php if (empty($adult_immunizations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-syringe"></i>
                        <h3>No Adult Immunization Records Found</h3>
                        <p>There are no immunization records for adults matching your search criteria.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($adult_immunizations as $record): ?>
                        <div class="immunization-card">
                            <div class="immunization-date">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('M d, Y', strtotime($record['date_administered'])); ?></span>
                            </div>
                            
                            <div class="patient-info">
                                <h3><?php echo htmlspecialchars($record['patient_name']); ?></h3>
                                <div class="email">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($record['patient_email']); ?>
                                </div>
                                <div class="age">
                                    <i class="fas fa-birthday-cake"></i>
                                    <?php echo htmlspecialchars($record['age']); ?> years old
                                </div>
                            </div>
                            
                            <div class="immunization-details">
                                <div class="immunization-type">
                                    <h4><?php echo htmlspecialchars($record['immunization_name']); ?></h4>
                                    <p class="description"><?php echo htmlspecialchars($record['immunization_description']); ?></p>
                                </div>
                                
                                <div class="next-schedule">
                                    <strong><i class="fas fa-clock"></i> Next Schedule:</strong>
                                    <?php if (!empty($record['next_schedule_date'])): ?>
                                        <span class="date"><?php echo date('M d, Y', strtotime($record['next_schedule_date'])); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($record['notes'])): ?>
                                <div class="notes">
                                    <strong><i class="fas fa-sticky-note"></i> Notes:</strong>
                                    <p><?php echo htmlspecialchars($record['notes']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="immunization-actions">
                                <a href="view_immunization.php?id=<?php echo $record['immunization_record_id']; ?>" class="btn btn-view" title="View Details">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn btn-danger" onclick="deleteRecord(<?php echo $record['immunization_record_id']; ?>)" title="Delete Record">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
        .immunization-sections {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .immunization-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .immunization-section h2 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .immunization-section h2 i {
            color: #3498db;
        }

        .age {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .age i {
            color: #e67e22;
        }

        @media (min-width: 1200px) {
            .immunization-sections {
                flex-direction: row;
            }

            .immunization-section {
                flex: 1;
            }
        }
        </style>
    </div>

    <!-- Add Immunization Modal -->
    <div class="modal" id="addImmunizationModal" tabindex="-1" role="dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Immunization Record</h3>
                <button type="button" class="modal-close" onclick="closeModal('addImmunizationModal')">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="patient_id">Patient</label>
                        <select class="form-control" id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients_list as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>">
                                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' (' . $patient['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="immunization_type_id">Immunization Type</label>
                        <select class="form-control" id="immunization_type_id" name="immunization_type_id" required>
                            <option value="">Select Immunization Type</option>
                            <?php foreach ($immunization_types as $type): ?>
                            <option value="<?php echo $type['immunization_type_id']; ?>">
                                <?php echo htmlspecialchars($type['name']); ?> - 
                                <?php echo htmlspecialchars($type['description']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="dose_number">Dose Number</label>
                        <input type="number" class="form-control" id="dose_number" name="dose_number" min="1" value="1" required>
                    </div>

                    <div class="form-group">
                        <label for="date">Date Administered</label>
                        <input type="date" class="form-control" id="date" name="date" required
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="next_schedule_date">Next Schedule Date</label>
                        <input type="date" class="form-control" id="next_schedule_date" name="next_schedule_date"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        <small class="form-text text-muted">Leave blank if no follow-up dose is required.</small>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addImmunizationModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Record</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all filter buttons and sections
        const filterButtons = document.querySelectorAll('.btn-filter');
        const sections = document.querySelectorAll('.immunization-section');
        
        // Add click event to filter buttons
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                
                // Show/hide sections based on filter
                sections.forEach(section => {
                    switch(filter) {
                        case 'children':
                            section.style.display = section.querySelector('h2 i.fa-child') ? 'block' : 'none';
                            break;
                        case 'adults':
                            section.style.display = section.querySelector('h2 i.fa-user') ? 'block' : 'none';
                            break;
                        default: // 'all'
                            section.style.display = 'block';
                            break;
                    }
                });
                
                // Update layout
                const container = document.querySelector('.immunization-sections');
                if (filter === 'all' && window.innerWidth >= 1200) {
                    container.style.flexDirection = 'row';
                } else {
                    container.style.flexDirection = 'column';
                }
            });
        });

        // Handle window resize for layout
        window.addEventListener('resize', function() {
            const container = document.querySelector('.immunization-sections');
            const activeFilter = document.querySelector('.btn-filter.active').dataset.filter;
            
            if (activeFilter === 'all' && window.innerWidth >= 1200) {
                container.style.flexDirection = 'row';
            } else {
                container.style.flexDirection = 'column';
            }
        });
    });

    // Show/hide modal functions
    function showAddImmunizationModal() {
        document.getElementById('addImmunizationModal').style.display = 'block';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking on X or outside the modal
    document.addEventListener('DOMContentLoaded', function() {
        // Close when clicking the X button
        const closeButtons = document.querySelectorAll('.modal-close');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Close when clicking outside the modal
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Cancel button closes modal
        const cancelButtons = document.querySelectorAll('.modal-footer .btn-secondary');
        cancelButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Set up immunization type and dose number change handlers
        const immunizationTypeSelect = document.getElementById('immunization_type_id');
        const doseNumberInput = document.getElementById('dose_number');
        const dateInput = document.getElementById('date');
        const nextScheduleInput = document.getElementById('next_schedule_date');

        // Store immunization type data
        const immunizationTypes = <?php echo json_encode($immunization_types); ?>;
        
        // Function to calculate next schedule date
        function calculateNextScheduleDate() {
            const immunizationTypeId = parseInt(immunizationTypeSelect.value);
            const doseNumber = parseInt(doseNumberInput.value);
            const currentDate = new Date(dateInput.value);
            
            if (isNaN(immunizationTypeId) || isNaN(doseNumber) || isNaN(currentDate.getTime())) {
                return;
            }
            
            // Find the selected immunization type
            const selectedType = immunizationTypes.find(type => type.immunization_type_id == immunizationTypeId);
            if (!selectedType) {
                return;
            }
            
            // If dose number is less than total doses, suggest a next date
            if (doseNumber < selectedType.dose_count) {
                // Default interval is 4 weeks (28 days)
                let intervalDays = 28;
                
                // Adjust interval based on immunization type
                // This is a simplified example - in a real app, you might want to have a more
                // sophisticated schedule based on specific immunization protocols
                switch (selectedType.name) {
                    case 'Hepatitis B':
                        intervalDays = 30; // 1 month
                        break;
                    case 'Pentavalent Vaccine':
                    case 'Oral Polio Vaccine':
                    case 'Pneumococcal Conjugate Vaccine':
                        intervalDays = 28; // 4 weeks
                        break;
                    case 'Tetanus Toxoid':
                        intervalDays = 180; // 6 months
                        break;
                    default:
                        intervalDays = 28; // Default 4 weeks
                }
                
                // Calculate next date
                const nextDate = new Date(currentDate);
                nextDate.setDate(nextDate.getDate() + intervalDays);
                
                // Format date as YYYY-MM-DD
                const year = nextDate.getFullYear();
                const month = String(nextDate.getMonth() + 1).padStart(2, '0');
                const day = String(nextDate.getDate()).padStart(2, '0');
                
                nextScheduleInput.value = `${year}-${month}-${day}`;
            } else {
                // Last dose, no next schedule needed
                nextScheduleInput.value = '';
            }
        }
        
        // Add event listeners
        if (immunizationTypeSelect && doseNumberInput && dateInput) {
            immunizationTypeSelect.addEventListener('change', calculateNextScheduleDate);
            doseNumberInput.addEventListener('change', calculateNextScheduleDate);
            dateInput.addEventListener('change', calculateNextScheduleDate);
        }
    });

    function deleteRecord(id) {
        if (confirm('Are you sure you want to delete this immunization record? This action cannot be undone.')) {
            fetch(`/connect/api/immunizations/delete.php?immunization_record_id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting record: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the record');
            });
        }
    }
    </script>
    <style>
        .immunization-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem 0;
        }

        .immunization-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1.25rem;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .immunization-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .immunization-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: #2c3e50;
            margin-bottom: 0.75rem;
        }

        .immunization-date i {
            color: #3498db;
        }

        .patient-info {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #eee;
        }

        .patient-info h3 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
            font-size: 1.1rem;
            line-height: 1.3;
            word-break: break-word;
        }

        .patient-info .email {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #666;
            word-break: break-all;
        }

        .immunization-details {
            margin-bottom: 1rem;
        }

        .immunization-type {
            margin-bottom: 0.75rem;
        }

        .immunization-type h4 {
            margin: 0 0 0.35rem 0;
            color: #2c3e50;
            font-size: 1rem;
            line-height: 1.3;
        }

        .immunization-type .description {
            font-size: 0.85rem;
            color: #666;
            margin: 0;
            line-height: 1.4;
        }

        .next-schedule {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin: 0.75rem 0;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .next-schedule i {
            color: #e67e22;
        }

        .next-schedule .date {
            color: #2c3e50;
            font-weight: 500;
        }

        .notes {
            margin-top: 0.75rem;
            font-size: 0.85rem;
        }

        .notes p {
            margin: 0.35rem 0 0 0;
            color: #666;
            line-height: 1.4;
        }

        .immunization-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .immunization-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.5rem;
            font-size: 0.85rem;
            white-space: nowrap;
            width: 100%;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 2rem 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #adb5bd;
            margin-bottom: 0.75rem;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            color: #495057;
            font-size: 1.1rem;
        }

        .empty-state p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9rem;
        }

        .search-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .search-form input {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 250px;
        }

        .search-form button {
            padding: 0.5rem 1rem;
            background: blue;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }

        .search-form button:hover {
            background: green;
        }

        .header-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-filter {
            padding: 0.5rem 1rem;
            border: 1px solid #3498db;
            background: #fff;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            color: #3498db;
        }

        .btn-filter:hover {
            background: #ebf5fb;
        }

        .btn-filter.active {
            background: #3498db;
            color: #fff;
            border-color: #2980b9;
            box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
        }

        .btn-filter i {
            font-size: 0.9rem;
        }

        @media (min-width: 768px) {
            .header-actions {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        @media (max-width: 768px) {
            .immunization-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem 0;
            }

            .immunization-card {
                padding: 1rem;
                margin: 0;
            }

            .immunization-date {
                font-size: 0.9rem;
            }

            .patient-info h3 {
                font-size: 1rem;
            }

            .patient-info .email,
            .immunization-type .description,
            .next-schedule,
            .notes {
                font-size: 0.8rem;
            }

            .next-schedule {
                padding: 0.5rem;
            }

            .immunization-actions {
                grid-template-columns: 1fr;
            }

            .immunization-actions .btn {
                padding: 0.6rem;
                font-size: 0.9rem;
            }

            .age {
                font-size: 0.8rem;
            }

            .empty-state {
                padding: 1.5rem 1rem;
            }

            .empty-state i {
                font-size: 2rem;
            }

            .empty-state h3 {
                font-size: 1rem;
            }

            .empty-state p {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .immunization-grid {
                gap: 0.75rem;
            }

            .immunization-card {
                border-radius: 6px;
            }

            .immunization-actions .btn {
                padding: 0.7rem;
            }

            .next-schedule {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
        }
    </style>
</body>
</html> 