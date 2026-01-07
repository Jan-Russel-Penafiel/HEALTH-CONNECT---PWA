<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar UI Test - HealthConnect</title>
    
    <!-- FullCalendar - v6 doesn't need separate CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .page-header h1 {
            margin: 0 0 5px 0;
            font-size: 1.8rem;
        }
        
        .page-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .test-controls {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .test-controls h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-warning:hover {
            background: #f57c00;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
        }
        
        .btn-info {
            background: #2196F3;
            color: white;
        }
        
        .btn-info:hover {
            background: #1976D2;
        }
        
        .calendar-section {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        
        @media (max-width: 992px) {
            .calendar-section {
                grid-template-columns: 1fr;
            }
        }
        
        .calendar-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .info-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.1rem;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 4px;
        }
        
        .legend-available {
            background: #4CAF50;
        }
        
        .legend-unavailable {
            background: #f44336;
        }
        
        .legend-limited {
            background: #ff9800;
        }
        
        .legend-full {
            background: #9e9e9e;
        }
        
        .console-log {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85rem;
        }
        
        .console-log .log-entry {
            padding: 4px 0;
            border-bottom: 1px solid #333;
        }
        
        .console-log .log-entry:last-child {
            border-bottom: none;
        }
        
        .console-log .log-success {
            color: #4CAF50;
        }
        
        .console-log .log-error {
            color: #f44336;
        }
        
        .console-log .log-warning {
            color: #ff9800;
        }
        
        .console-log .log-info {
            color: #2196F3;
        }
        
        /* FullCalendar Customizations */
        .fc {
            font-family: inherit;
        }
        
        .fc-toolbar-title {
            font-size: 1.3rem !important;
        }
        
        .fc-button-primary {
            background-color: #4CAF50 !important;
            border-color: #4CAF50 !important;
        }
        
        .fc-button-primary:hover {
            background-color: #45a049 !important;
            border-color: #45a049 !important;
        }
        
        .fc-button-primary:disabled {
            background-color: #81C784 !important;
            border-color: #81C784 !important;
        }
        
        .fc-day-today {
            background: #e8f5e9 !important;
        }
        
        /* Custom date styling */
        .fc-daygrid-day.available-date {
            background: #e8f5e9 !important;
            cursor: pointer;
        }
        
        .fc-daygrid-day.unavailable-date {
            background: #ffebee !important;
            cursor: not-allowed;
        }
        
        .fc-daygrid-day.unavailable-date .fc-daygrid-day-number {
            color: #c62828;
            text-decoration: line-through;
        }
        
        .fc-daygrid-day.limited-slots {
            background: #fff3e0 !important;
        }
        
        .fc-daygrid-day.full-slots {
            background: #eeeeee !important;
            cursor: not-allowed;
        }
        
        .fc-daygrid-day.full-slots .fc-daygrid-day-number {
            color: #9e9e9e;
        }
        
        .slot-indicator {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            text-align: center;
            margin: 2px;
        }
        
        .slots-available {
            background: #4CAF50;
            color: white;
        }
        
        .slots-limited {
            background: #ff9800;
            color: white;
        }
        
        .slots-full {
            background: #9e9e9e;
            color: white;
        }
        
        .slots-unavailable {
            background: #f44336;
            color: white;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }
        
        .modal-close:hover {
            background: #f5f5f5;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .selected-date-display {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .selected-date-display .date {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2e7d32;
        }
        
        .availability-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .availability-option {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .availability-option:hover {
            border-color: #4CAF50;
        }
        
        .availability-option.selected {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        
        .availability-option input[type="radio"] {
            display: none;
        }
        
        .option-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .option-available .option-icon {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .option-unavailable .option-icon {
            background: #ffebee;
            color: #c62828;
        }
        
        .option-details h4 {
            margin: 0 0 4px 0;
            color: #333;
        }
        
        .option-details p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
        }
        
        .slot-limit-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .slot-limit-section.hidden {
            display: none;
        }
        
        .slot-presets {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .slot-preset {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .slot-preset:hover,
        .slot-preset.active {
            border-color: #4CAF50;
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Status info card */
        .status-info {
            margin-top: 15px;
        }
        
        .status-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .status-row:last-child {
            border-bottom: none;
        }
        
        .status-label {
            color: #666;
        }
        
        .status-value {
            font-weight: 600;
            color: #333;
        }
        
        /* Toast notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(150%);
            transition: transform 0.3s ease;
            z-index: 2000;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast-success {
            background: #4CAF50;
        }
        
        .toast-error {
            background: #f44336;
        }
        
        .toast-warning {
            background: #ff9800;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Calendar UI Test Page</h1>
            <p>Isolated testing environment for calendar availability and slot management</p>
        </div>
        
        <!-- Test Controls -->
        <div class="test-controls">
            <h3><i class="fas fa-cogs"></i> Test Controls</h3>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="testSelectDate()">
                    <i class="fas fa-mouse-pointer"></i> Test Date Selection
                </button>
                <button class="btn btn-warning" onclick="testSlotDecrement()">
                    <i class="fas fa-minus-circle"></i> Simulate Slot Decrement
                </button>
                <button class="btn btn-danger" onclick="testDisabledDate()">
                    <i class="fas fa-ban"></i> Test Disabled State
                </button>
                <button class="btn btn-info" onclick="resetTestData()">
                    <i class="fas fa-redo"></i> Reset Test Data
                </button>
                <button class="btn btn-primary" onclick="exportTestData()">
                    <i class="fas fa-download"></i> Export Data
                </button>
            </div>
        </div>
        
        <div class="calendar-section">
            <!-- Calendar -->
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
            
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Legend -->
                <div class="info-card">
                    <h3><i class="fas fa-palette"></i> Legend</h3>
                    <div class="legend-item">
                        <div class="legend-color legend-available"></div>
                        <span>Available (slots remaining)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-unavailable"></div>
                        <span>Unavailable (blocked)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-limited"></div>
                        <span>Limited slots (≤3 remaining)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-full"></div>
                        <span>Fully booked (0 slots)</span>
                    </div>
                </div>
                
                <!-- Current Status -->
                <div class="info-card">
                    <h3><i class="fas fa-chart-bar"></i> Current Status</h3>
                    <div class="status-info">
                        <div class="status-row">
                            <span class="status-label">Total Dates Configured</span>
                            <span class="status-value" id="totalDates">0</span>
                        </div>
                        <div class="status-row">
                            <span class="status-label">Unavailable Dates</span>
                            <span class="status-value" id="unavailableDates">0</span>
                        </div>
                        <div class="status-row">
                            <span class="status-label">Total Slots Available</span>
                            <span class="status-value" id="totalSlots">0</span>
                        </div>
                        <div class="status-row">
                            <span class="status-label">Selected Date</span>
                            <span class="status-value" id="selectedDate">None</span>
                        </div>
                    </div>
                </div>
                
                <!-- Console Log -->
                <div class="info-card">
                    <h3><i class="fas fa-terminal"></i> Console Log</h3>
                    <div class="console-log" id="consoleLog">
                        <div class="log-entry log-info">[Init] Calendar UI Test Page loaded</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Date Configuration Modal -->
    <div class="modal-overlay" id="dateModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-day"></i> Configure Date</h3>
                <button class="modal-close" onclick="closeDateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="selected-date-display">
                    <div class="date" id="modalSelectedDate">January 8, 2026</div>
                </div>
                
                <div class="availability-options">
                    <label class="availability-option option-available" onclick="selectAvailability('available')">
                        <input type="radio" name="availability" value="available">
                        <div class="option-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="option-details">
                            <h4>Available</h4>
                            <p>Allow appointments on this date</p>
                        </div>
                    </label>
                    
                    <label class="availability-option option-unavailable" onclick="selectAvailability('unavailable')">
                        <input type="radio" name="availability" value="unavailable">
                        <div class="option-icon">
                            <i class="fas fa-times"></i>
                        </div>
                        <div class="option-details">
                            <h4>Unavailable</h4>
                            <p>Block all appointments on this date</p>
                        </div>
                    </label>
                </div>
                
                <div class="slot-limit-section" id="slotLimitSection">
                    <div class="form-group">
                        <label for="slotLimit">Appointment Slot Limit</label>
                        <input type="number" class="form-control" id="slotLimit" min="1" max="50" value="10" placeholder="Enter slot limit">
                    </div>
                    <div class="slot-presets">
                        <button class="slot-preset" onclick="setSlotPreset(5)">5</button>
                        <button class="slot-preset" onclick="setSlotPreset(10)">10</button>
                        <button class="slot-preset" onclick="setSlotPreset(15)">15</button>
                        <button class="slot-preset" onclick="setSlotPreset(20)">20</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDateModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveDateConfig()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toast" class="toast"></div>
    
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    
    <script>
        // =====================================================
        // MOCK DATA - No database operations
        // =====================================================
        const mockData = {
            // Unavailable dates (blocked by health worker)
            unavailableDates: [
                '2026-01-12', // Sunday
                '2026-01-19', // Sunday
                '2026-01-26', // Sunday
                '2026-01-15', // Specific blocked date
                '2026-02-02', // Sunday
            ],
            
            // Slot limits per date (date: slotLimit)
            slotLimits: {
                '2026-01-08': 10,
                '2026-01-09': 15,
                '2026-01-10': 5,
                '2026-01-13': 20,
                '2026-01-14': 10,
                '2026-01-16': 8,
                '2026-01-17': 12,
                '2026-01-20': 10,
                '2026-01-21': 10,
                '2026-01-22': 10,
                '2026-01-23': 10,
                '2026-01-24': 10,
            },
            
            // Booked slots per date (simulating appointments already made)
            bookedSlots: {
                '2026-01-08': 3,
                '2026-01-09': 14, // Almost full
                '2026-01-10': 5,  // Full
                '2026-01-13': 2,
                '2026-01-14': 8,
                '2026-01-16': 1,
            },
            
            // Default slot limit for unconfigured dates
            defaultSlotLimit: 10
        };
        
        let calendar;
        let selectedDate = null;
        let currentAvailability = 'available';
        
        // =====================================================
        // CONSOLE LOGGING
        // =====================================================
        function logToConsole(message, type = 'info') {
            const consoleLog = document.getElementById('consoleLog');
            const timestamp = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.textContent = `[${timestamp}] ${message}`;
            consoleLog.appendChild(entry);
            consoleLog.scrollTop = consoleLog.scrollHeight;
            
            // Also log to browser console
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
        
        // =====================================================
        // TOAST NOTIFICATIONS
        // =====================================================
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'exclamation-triangle'}"></i> ${message}`;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // =====================================================
        // HELPER FUNCTIONS
        // =====================================================
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        function getDateStatus(dateStr) {
            // Check if unavailable
            if (mockData.unavailableDates.includes(dateStr)) {
                return { status: 'unavailable', slots: 0, remaining: 0 };
            }
            
            // Get slot limit
            const slotLimit = mockData.slotLimits[dateStr] || mockData.defaultSlotLimit;
            const booked = mockData.bookedSlots[dateStr] || 0;
            const remaining = Math.max(0, slotLimit - booked);
            
            if (remaining === 0) {
                return { status: 'full', slots: slotLimit, remaining: 0 };
            } else if (remaining <= 3) {
                return { status: 'limited', slots: slotLimit, remaining: remaining };
            } else {
                return { status: 'available', slots: slotLimit, remaining: remaining };
            }
        }
        
        function updateStatusDisplay() {
            const unavailableCount = mockData.unavailableDates.length;
            let totalSlots = 0;
            let configuredDates = Object.keys(mockData.slotLimits).length;
            
            Object.entries(mockData.slotLimits).forEach(([date, slots]) => {
                if (!mockData.unavailableDates.includes(date)) {
                    const booked = mockData.bookedSlots[date] || 0;
                    totalSlots += Math.max(0, slots - booked);
                }
            });
            
            document.getElementById('totalDates').textContent = configuredDates;
            document.getElementById('unavailableDates').textContent = unavailableCount;
            document.getElementById('totalSlots').textContent = totalSlots;
            document.getElementById('selectedDate').textContent = selectedDate ? formatDate(selectedDate) : 'None';
        }
        
        // =====================================================
        // CALENDAR INITIALIZATION
        // =====================================================
        function initCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: '2026-01-08',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth'
                },
                height: 'auto',
                selectable: true,
                selectMirror: true,
                dayMaxEvents: true,
                
                // Custom date cell rendering
                dayCellDidMount: function(arg) {
                    const dateStr = arg.date.toISOString().split('T')[0];
                    const status = getDateStatus(dateStr);
                    
                    // Add custom class based on status
                    switch(status.status) {
                        case 'unavailable':
                            arg.el.classList.add('unavailable-date');
                            break;
                        case 'full':
                            arg.el.classList.add('full-slots');
                            break;
                        case 'limited':
                            arg.el.classList.add('limited-slots');
                            break;
                        case 'available':
                            arg.el.classList.add('available-date');
                            break;
                    }
                    
                    // Add slot indicator
                    const indicator = document.createElement('div');
                    indicator.className = 'slot-indicator';
                    
                    if (status.status === 'unavailable') {
                        indicator.className += ' slots-unavailable';
                        indicator.innerHTML = '<i class="fas fa-ban"></i>';
                    } else if (status.status === 'full') {
                        indicator.className += ' slots-full';
                        indicator.textContent = 'Full';
                    } else {
                        indicator.className += status.status === 'limited' ? ' slots-limited' : ' slots-available';
                        indicator.textContent = `${status.remaining} slots`;
                    }
                    
                    const dayFrame = arg.el.querySelector('.fc-daygrid-day-frame');
                    if (dayFrame) {
                        dayFrame.appendChild(indicator);
                    }
                },
                
                // Handle date click
                dateClick: function(info) {
                    const dateStr = info.dateStr;
                    logToConsole(`Date clicked: ${dateStr}`, 'info');
                    
                    selectedDate = dateStr;
                    updateStatusDisplay();
                    openDateModal(dateStr);
                },
                
                // Handle date selection (range)
                select: function(info) {
                    logToConsole(`Date range selected: ${info.startStr} to ${info.endStr}`, 'info');
                }
            });
            
            calendar.render();
            logToConsole('Calendar initialized successfully', 'success');
            updateStatusDisplay();
        }
        
        // =====================================================
        // MODAL FUNCTIONS
        // =====================================================
        function openDateModal(dateStr) {
            const status = getDateStatus(dateStr);
            
            document.getElementById('modalSelectedDate').textContent = formatDate(dateStr);
            document.getElementById('dateModal').classList.add('active');
            
            // Set current availability
            if (status.status === 'unavailable') {
                selectAvailability('unavailable');
            } else {
                selectAvailability('available');
                document.getElementById('slotLimit').value = mockData.slotLimits[dateStr] || mockData.defaultSlotLimit;
            }
            
            logToConsole(`Modal opened for date: ${dateStr}`, 'info');
        }
        
        function closeDateModal() {
            document.getElementById('dateModal').classList.remove('active');
            logToConsole('Modal closed', 'info');
        }
        
        function selectAvailability(type) {
            currentAvailability = type;
            
            // Update UI
            document.querySelectorAll('.availability-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            const selectedOption = document.querySelector(`.option-${type}`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
                selectedOption.querySelector('input').checked = true;
            }
            
            // Show/hide slot limit section
            const slotSection = document.getElementById('slotLimitSection');
            if (type === 'available') {
                slotSection.classList.remove('hidden');
            } else {
                slotSection.classList.add('hidden');
            }
            
            logToConsole(`Availability set to: ${type}`, 'info');
        }
        
        function setSlotPreset(value) {
            document.getElementById('slotLimit').value = value;
            
            // Update preset buttons
            document.querySelectorAll('.slot-preset').forEach(btn => {
                btn.classList.remove('active');
                if (parseInt(btn.textContent) === value) {
                    btn.classList.add('active');
                }
            });
            
            logToConsole(`Slot preset selected: ${value}`, 'info');
        }
        
        function saveDateConfig() {
            if (!selectedDate) {
                showToast('No date selected', 'error');
                logToConsole('Error: No date selected for saving', 'error');
                return;
            }
            
            if (currentAvailability === 'unavailable') {
                // Add to unavailable dates
                if (!mockData.unavailableDates.includes(selectedDate)) {
                    mockData.unavailableDates.push(selectedDate);
                }
                // Remove from slot limits
                delete mockData.slotLimits[selectedDate];
                
                logToConsole(`Date ${selectedDate} marked as UNAVAILABLE`, 'warning');
                showToast('Date marked as unavailable', 'success');
            } else {
                // Remove from unavailable dates
                const index = mockData.unavailableDates.indexOf(selectedDate);
                if (index > -1) {
                    mockData.unavailableDates.splice(index, 1);
                }
                
                // Update slot limit
                const slotLimit = parseInt(document.getElementById('slotLimit').value) || mockData.defaultSlotLimit;
                mockData.slotLimits[selectedDate] = slotLimit;
                
                logToConsole(`Date ${selectedDate} set as AVAILABLE with ${slotLimit} slots`, 'success');
                showToast(`Date updated with ${slotLimit} slots`, 'success');
            }
            
            closeDateModal();
            refreshCalendar();
            updateStatusDisplay();
        }
        
        function refreshCalendar() {
            if (calendar) {
                calendar.destroy();
                initCalendar();
                logToConsole('Calendar refreshed', 'info');
            }
        }
        
        // =====================================================
        // TEST FUNCTIONS
        // =====================================================
        function testSelectDate() {
            const testDate = '2026-01-20';
            logToConsole(`TEST: Simulating date selection for ${testDate}`, 'info');
            
            selectedDate = testDate;
            updateStatusDisplay();
            openDateModal(testDate);
            
            showToast('Date selection test triggered', 'success');
        }
        
        function testSlotDecrement() {
            const testDate = '2026-01-13';
            
            if (!mockData.bookedSlots[testDate]) {
                mockData.bookedSlots[testDate] = 0;
            }
            
            mockData.bookedSlots[testDate]++;
            const status = getDateStatus(testDate);
            
            logToConsole(`TEST: Decremented slot for ${testDate}. Remaining: ${status.remaining}`, 'warning');
            
            refreshCalendar();
            updateStatusDisplay();
            
            showToast(`Slot booked for ${testDate}. ${status.remaining} remaining`, 'warning');
            
            if (status.remaining === 0) {
                logToConsole(`ALERT: Date ${testDate} is now fully booked!`, 'error');
                showToast(`${testDate} is now fully booked!`, 'error');
            }
        }
        
        function testDisabledDate() {
            const testDate = '2026-01-15'; // Already in unavailable dates
            
            logToConsole(`TEST: Attempting to select disabled date ${testDate}`, 'warning');
            
            const status = getDateStatus(testDate);
            
            if (status.status === 'unavailable') {
                logToConsole(`BLOCKED: Date ${testDate} is unavailable`, 'error');
                showToast('Cannot select unavailable date', 'error');
            } else if (status.status === 'full') {
                logToConsole(`BLOCKED: Date ${testDate} is fully booked`, 'error');
                showToast('Cannot select fully booked date', 'error');
            } else {
                logToConsole(`Date ${testDate} is selectable`, 'success');
            }
        }
        
        function resetTestData() {
            // Reset to initial mock data
            mockData.unavailableDates = ['2026-01-12', '2026-01-19', '2026-01-26', '2026-01-15', '2026-02-02'];
            mockData.slotLimits = {
                '2026-01-08': 10,
                '2026-01-09': 15,
                '2026-01-10': 5,
                '2026-01-13': 20,
                '2026-01-14': 10,
                '2026-01-16': 8,
                '2026-01-17': 12,
                '2026-01-20': 10,
                '2026-01-21': 10,
                '2026-01-22': 10,
                '2026-01-23': 10,
                '2026-01-24': 10,
            };
            mockData.bookedSlots = {
                '2026-01-08': 3,
                '2026-01-09': 14,
                '2026-01-10': 5,
                '2026-01-13': 2,
                '2026-01-14': 8,
                '2026-01-16': 1,
            };
            
            logToConsole('TEST DATA RESET: All data restored to initial state', 'info');
            showToast('Test data reset successfully', 'success');
            
            refreshCalendar();
            updateStatusDisplay();
        }
        
        function exportTestData() {
            const exportData = {
                timestamp: new Date().toISOString(),
                unavailableDates: mockData.unavailableDates,
                slotLimits: mockData.slotLimits,
                bookedSlots: mockData.bookedSlots
            };
            
            const dataStr = JSON.stringify(exportData, null, 2);
            logToConsole(`EXPORT:\n${dataStr}`, 'info');
            
            // Copy to clipboard
            navigator.clipboard.writeText(dataStr).then(() => {
                showToast('Data exported to clipboard', 'success');
            }).catch(() => {
                showToast('Data logged to console', 'warning');
            });
        }
        
        // =====================================================
        // INITIALIZATION
        // =====================================================
        document.addEventListener('DOMContentLoaded', function() {
            initCalendar();
            logToConsole('Page loaded and ready for testing', 'success');
        });
        
        // Close modal on outside click
        document.getElementById('dateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDateModal();
            }
        });
    </script>
</body>
</html>
