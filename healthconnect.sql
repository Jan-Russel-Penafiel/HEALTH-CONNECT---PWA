-- Create database
CREATE DATABASE IF NOT EXISTS healthconnect;
USE healthconnect;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (name, value) VALUES 
('max_daily_appointments', '20'),
('appointment_duration', '30'),
('working_hours_start', '09:00'),
('working_hours_end', '17:00'),
('enable_sms_notifications', '0'),
('enable_email_notifications', '0'),
('sms_api_key', '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX'),
('sms_sender_id', 'PhilSMS'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_username', 'vmctaccollege@gmail.com'),
('smtp_password', 'tqqs fkkh lbuz jbeg'),
('smtp_encryption', 'tls');

-- User roles table
CREATE TABLE IF NOT EXISTS user_roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default user roles
INSERT INTO user_roles (role_name) VALUES 
('admin'),
('health_worker'),
('patient');

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE NOT NULL,
    mobile_number VARCHAR(20),
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    date_of_birth DATE,
    address TEXT,
    profile_picture VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    otp VARCHAR(6),
    otp_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id)
);

-- Insert default admin user
INSERT INTO users (role_id, username, email, first_name, last_name, gender) VALUES
(1, 'admin', 'penafielliezl1122@gmail.com', 'System', 'Admin', 'Other');

-- Patients table (extends users)
CREATE TABLE IF NOT EXISTS patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    blood_type VARCHAR(5),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    emergency_contact_name VARCHAR(200),
    emergency_contact_number VARCHAR(20),
    emergency_contact_relationship VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Health workers table (extends users)
CREATE TABLE IF NOT EXISTS health_workers (
    health_worker_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    position VARCHAR(100),
    license_number VARCHAR(50),
    specialty VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Medical records table
CREATE TABLE IF NOT EXISTS medical_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    health_worker_id INT,
    visit_date DATETIME,
    chief_complaint TEXT,
    diagnosis TEXT,
    treatment TEXT,
    prescription TEXT,
    notes TEXT,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (health_worker_id) REFERENCES health_workers(health_worker_id)
);

-- Appointment status table
CREATE TABLE IF NOT EXISTS appointment_status (
    status_id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default appointment statuses
INSERT INTO appointment_status (status_name) VALUES 
('Scheduled'),
('Confirmed'),
('Completed'),
('Cancelled'),
('No Show');

-- Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    health_worker_id INT,
    appointment_date DATE,
    appointment_time TIME,
    status_id INT,
    reason TEXT,
    notes TEXT,
    sms_notification_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (health_worker_id) REFERENCES health_workers(health_worker_id),
    FOREIGN KEY (status_id) REFERENCES appointment_status(status_id)
);

-- Immunization types table
CREATE TABLE IF NOT EXISTS immunization_types (
    immunization_type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    recommended_age VARCHAR(100),
    dose_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert common immunization types
INSERT INTO immunization_types (name, description, recommended_age, dose_count) VALUES
('BCG', 'Bacille Calmette-Guerin - protects against tuberculosis', 'Birth', 1),
('Hepatitis B', 'Protects against hepatitis B', 'Birth, 6 weeks, 10 weeks, 14 weeks', 4),
('Pentavalent Vaccine', 'Protects against diphtheria, pertussis, tetanus, hepatitis B and Hib', '6 weeks, 10 weeks, 14 weeks', 3),
('Oral Polio Vaccine', 'Protects against poliomyelitis', '6 weeks, 10 weeks, 14 weeks', 3),
('Inactivated Polio Vaccine', 'Protects against poliomyelitis', '14 weeks', 1),
('Pneumococcal Conjugate Vaccine', 'Protects against pneumonia', '6 weeks, 10 weeks, 14 weeks', 3),
('Measles Vaccine', 'Protects against measles', '9 months', 1),
('Measles, Mumps, Rubella', 'Protects against measles, mumps, rubella', '12 months', 1),
('Tetanus Toxoid', 'Protects against tetanus', 'For pregnant women', 2);

-- Immunization records table
CREATE TABLE IF NOT EXISTS immunization_records (
    immunization_record_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    immunization_type_id INT,
    health_worker_id INT,
    dose_number INT,
    date_administered DATE,
    next_schedule_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (immunization_type_id) REFERENCES immunization_types(immunization_type_id),
    FOREIGN KEY (health_worker_id) REFERENCES health_workers(health_worker_id)
);

-- SMS log table (for appointment reminders)
CREATE TABLE IF NOT EXISTS sms_logs (
    sms_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    recipient_number VARCHAR(20),
    message TEXT,
    status VARCHAR(50),
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
); 