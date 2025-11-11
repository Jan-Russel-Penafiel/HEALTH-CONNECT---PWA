-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2025 at 11:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `healthconnect`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `health_worker_id` int(11) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sms_notification_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `health_worker_id`, `appointment_date`, `appointment_time`, `status_id`, `reason`, `notes`, `sms_notification_sent`, `created_at`, `updated_at`) VALUES
(11, 15, 2, '2025-11-14', '14:00:00', 2, 'asdad', 'asda', 1, '2025-11-11 09:47:10', '2025-11-11 10:12:55');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_status`
--

CREATE TABLE `appointment_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_status`
--

INSERT INTO `appointment_status` (`status_id`, `status_name`, `created_at`) VALUES
(1, 'Scheduled', '2025-07-09 12:04:56'),
(2, 'Confirmed', '2025-07-09 12:04:56'),
(3, 'Done', '2025-07-09 12:04:56'),
(4, 'Cancelled', '2025-07-09 12:04:56'),
(5, 'No Show', '2025-07-09 12:04:56');

-- --------------------------------------------------------

--
-- Table structure for table `health_workers`
--

CREATE TABLE `health_workers` (
  `health_worker_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_workers`
--

INSERT INTO `health_workers` (`health_worker_id`, `user_id`, `position`, `license_number`, `specialty`, `created_at`, `updated_at`) VALUES
(2, 15, 'asda', '3212', 'asdada', '2025-08-23 06:31:05', '2025-08-23 06:31:05');

-- --------------------------------------------------------

--
-- Table structure for table `immunization_records`
--

CREATE TABLE `immunization_records` (
  `immunization_record_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `immunization_type_id` int(11) DEFAULT NULL,
  `health_worker_id` int(11) DEFAULT NULL,
  `dose_number` int(11) DEFAULT NULL,
  `date_administered` date DEFAULT NULL,
  `next_schedule_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `immunization_records`
--

INSERT INTO `immunization_records` (`immunization_record_id`, `patient_id`, `immunization_type_id`, `health_worker_id`, `dose_number`, `date_administered`, `next_schedule_date`, `notes`, `created_at`, `updated_at`) VALUES
(2, 15, 2, 2, 1, '2025-11-11', '2025-12-11', 'asda', '2025-11-11 09:44:50', '2025-11-11 09:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `immunization_types`
--

CREATE TABLE `immunization_types` (
  `immunization_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `recommended_age` varchar(100) DEFAULT NULL,
  `dose_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `immunization_types`
--

INSERT INTO `immunization_types` (`immunization_type_id`, `name`, `description`, `recommended_age`, `dose_count`, `created_at`) VALUES
(1, 'BCG', 'Bacille Calmette-Guerin - protects against tuberculosis', 'Birth', 1, '2025-07-09 12:04:56'),
(2, 'Hepatitis B', 'Protects against hepatitis B', 'Birth, 6 weeks, 10 weeks, 14 weeks', 4, '2025-07-09 12:04:56'),
(3, 'Pentavalent Vaccine', 'Protects against diphtheria, pertussis, tetanus, hepatitis B and Hib', '6 weeks, 10 weeks, 14 weeks', 3, '2025-07-09 12:04:56'),
(4, 'Oral Polio Vaccine', 'Protects against poliomyelitis', '6 weeks, 10 weeks, 14 weeks', 3, '2025-07-09 12:04:56'),
(5, 'Inactivated Polio Vaccine', 'Protects against poliomyelitis', '14 weeks', 1, '2025-07-09 12:04:56'),
(6, 'Pneumococcal Conjugate Vaccine', 'Protects against pneumonia', '6 weeks, 10 weeks, 14 weeks', 3, '2025-07-09 12:04:56'),
(7, 'Measles Vaccine', 'Protects against measles', '9 months', 1, '2025-07-09 12:04:56'),
(8, 'Measles, Mumps, Rubella', 'Protects against measles, mumps, rubella', '12 months', 1, '2025-07-09 12:04:56'),
(9, 'Tetanus Toxoid', 'Protects against tetanus', 'For pregnant women', 2, '2025-07-09 12:04:56');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `health_worker_id` int(11) DEFAULT NULL,
  `visit_date` datetime DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_approved` tinyint(1) DEFAULT 0,
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `user_id`, `blood_type`, `height`, `weight`, `emergency_contact_name`, `emergency_contact_number`, `emergency_contact_relationship`, `created_at`, `updated_at`, `is_approved`, `approved_at`) VALUES
(15, 18, 'B+', 154.00, 232.00, 'Jan Russel Elizares Peñafiel', '09677726912', 'sadada', '2025-08-23 07:12:34', '2025-08-23 07:12:34', 1, '2025-08-23 07:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `name`, `value`, `created_at`, `updated_at`) VALUES
(1, 'max_daily_appointments', '20', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(2, 'appointment_duration', '30', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(3, 'working_hours_start', '09:00', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(4, 'working_hours_end', '17:00', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(5, 'enable_sms_notifications', '1', '2025-07-09 12:55:33', '2025-07-09 12:55:49'),
(6, 'enable_email_notifications', '1', '2025-07-09 12:55:33', '2025-07-09 12:55:49'),
(7, 'sms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004', '2025-07-09 12:55:33', '2025-11-09 06:42:15'),
(8, 'sms_sender_id', 'PhilSMS', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(9, 'smtp_host', 'smtp.gmail.com', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(10, 'smtp_port', '587', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(11, 'smtp_username', 'vmctaccollege@gmail.com', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(12, 'smtp_password', 'tqqs fkkh lbuz jbeg', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(13, 'smtp_encryption', 'tls', '2025-07-09 12:55:33', '2025-07-09 12:55:33'),
(86, 'sms_provider', 'IPROG SMS', '2025-11-09 06:42:15', '2025-11-09 06:42:15'),
(87, 'sms_api_url', 'https://sms.iprogtech.com/api/v1/sms_messages', '2025-11-09 06:42:15', '2025-11-09 06:42:15');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `sms_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `recipient_number` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_logs`
--

INSERT INTO `sms_logs` (`sms_id`, `appointment_id`, `recipient_number`, `message`, `status`, `sent_at`, `created_at`) VALUES
(20, 11, '09677726912', 'Hello testpatientname, your appointment at Brgy. Poblacion Health Center has been CONFIRMED for November 14, 2025 at 2:00 PM with Dr. testname testlastname. Please arrive 15 minutes early. Thank you!', 'sent', '2025-11-11 10:12:45', '2025-11-11 10:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `username`, `email`, `password`, `mobile_number`, `first_name`, `middle_name`, `last_name`, `gender`, `date_of_birth`, `address`, `profile_picture`, `is_active`, `last_login`, `otp`, `otp_expiry`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'penafielliezl1122@gmail.com', 'admin123', NULL, 'System', NULL, 'Admin', 'Other', NULL, NULL, NULL, 1, '2025-11-12 17:18:32', NULL, NULL, '2025-07-09 12:04:56', '2025-11-12 09:18:32'),
(15, 2, 'testnurse', 'janrusselpenafiel01172005@gmail.com', 'testnurse', '09677726912', 'testname', 'testmidname', 'testlastname', 'Male', NULL, 'testadrress', NULL, 1, '2025-11-11 18:34:08', NULL, NULL, '2025-08-23 06:31:05', '2025-11-11 10:34:08'),
(18, 3, 'testpatient', 'penafielliezl9999@gmail.com', 'testpatient', '09677726912', 'testpatientname', 'testpatientmidname', 'testpatientlastname', 'Male', '2005-01-20', 'testaddress1', NULL, 1, '2025-11-11 18:42:14', NULL, NULL, '2025-08-23 07:12:34', '2025-11-11 10:42:14');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`role_id`, `role_name`, `created_at`) VALUES
(1, 'admin', '2025-07-09 12:04:55'),
(2, 'health_worker', '2025-07-09 12:04:55'),
(3, 'patient', '2025-07-09 12:04:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `health_worker_id` (`health_worker_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `appointment_status`
--
ALTER TABLE `appointment_status`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `health_workers`
--
ALTER TABLE `health_workers`
  ADD PRIMARY KEY (`health_worker_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `immunization_records`
--
ALTER TABLE `immunization_records`
  ADD PRIMARY KEY (`immunization_record_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `immunization_type_id` (`immunization_type_id`),
  ADD KEY `health_worker_id` (`health_worker_id`);

--
-- Indexes for table `immunization_types`
--
ALTER TABLE `immunization_types`
  ADD PRIMARY KEY (`immunization_type_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `health_worker_id` (`health_worker_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`sms_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `appointment_status`
--
ALTER TABLE `appointment_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `health_workers`
--
ALTER TABLE `health_workers`
  MODIFY `health_worker_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `immunization_records`
--
ALTER TABLE `immunization_records`
  MODIFY `immunization_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `immunization_types`
--
ALTER TABLE `immunization_types`
  MODIFY `immunization_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `sms_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`health_worker_id`) REFERENCES `health_workers` (`health_worker_id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `appointment_status` (`status_id`);

--
-- Constraints for table `health_workers`
--
ALTER TABLE `health_workers`
  ADD CONSTRAINT `health_workers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `immunization_records`
--
ALTER TABLE `immunization_records`
  ADD CONSTRAINT `immunization_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `immunization_records_ibfk_2` FOREIGN KEY (`immunization_type_id`) REFERENCES `immunization_types` (`immunization_type_id`),
  ADD CONSTRAINT `immunization_records_ibfk_3` FOREIGN KEY (`health_worker_id`) REFERENCES `health_workers` (`health_worker_id`);

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`health_worker_id`) REFERENCES `health_workers` (`health_worker_id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
