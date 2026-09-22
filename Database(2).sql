-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2026 at 10:49 AM
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
-- Database: `request`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('daily_request_limit', '5', '2026-09-16 06:03:10'),
('max_units_per_request', '2', '2026-09-16 05:49:47'),
('projector_total_units', '7', '2026-09-16 07:45:57');

-- --------------------------------------------------------

--
-- Table structure for table `dropdown_option`
--

CREATE TABLE `dropdown_option` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `option_value` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dropdown_option`
--

INSERT INTO `dropdown_option` (`id`, `parent_id`, `option_value`, `sort_order`, `is_active`) VALUES
(1, NULL, 'SCHOOL DIRECTOR\'S OFFICE', 1, 1),
(2, NULL, 'BASIC EDUCATION DEPARTMENT', 2, 1),
(3, 2, 'Basic Education Principal\'s Office', 1, 1),
(4, 2, 'Grade School Faculty', 2, 1),
(5, 2, 'Junior Business High School Faculty', 3, 1),
(6, 2, 'Basic Education Guidance', 4, 1),
(7, 2, 'Junior High School Prefect of Discipline', 5, 1),
(8, 2, 'Grade School Library', 6, 1),
(9, 2, 'High School Library', 7, 1),
(10, NULL, 'SENIOR HIGH SCHOOL', 3, 1),
(11, 10, 'SHS Canteen', 1, 1),
(12, 10, 'SHS Guidance', 2, 1),
(13, 10, 'SHS Faculty Office Grade 11', 3, 1),
(14, 10, 'SHS Faculty Office Grade 12', 4, 1),
(15, 10, 'SHS Library', 5, 1),
(16, 10, 'SHS Prefect of Discipline', 6, 1),
(17, NULL, 'COLLEGE / ACADEMIC CLUSTERS', 4, 1),
(18, 17, 'TECHNOLOGY CLUSTER', 1, 1),
(19, 18, 'College of Computer Studies', 1, 1),
(20, 18, 'College of Engineering', 2, 1),
(21, 18, 'College of Architecture ', 3, 1),
(22, 17, 'ALLIED CLUSTER', 2, 1),
(23, 22, 'CON', 1, 1),
(24, 22, 'COMDT', 2, 1),
(25, 22, 'CORH', 3, 1),
(26, 22, 'CORT', 4, 1),
(27, 22, 'COPT', 5, 1),
(28, 17, 'BUSINESS CLUSTER', 3, 1),
(29, 28, 'TM', 1, 1),
(30, 28, 'HM', 2, 1),
(31, 28, 'MM', 3, 1),
(32, 28, 'HRM', 4, 1),
(33, 28, 'ACC', 5, 1),
(34, 17, 'HUMSS CLUSTER', 4, 1),
(35, 34, 'EDUC', 1, 1),
(36, 34, 'PSTC', 2, 1),
(37, 34, 'COM', 3, 1),
(38, 34, 'CRIM', 4, 1),
(39, NULL, 'STUDENT AFFAIRS AND SERVICES', 5, 1),
(40, 39, 'Scholarship', 1, 1),
(41, 39, 'College Guidance', 2, 1),
(42, 39, 'Alumni', 3, 1),
(43, 39, 'PAG', 4, 1),
(44, 39, 'Clinic', 5, 1),
(45, 39, 'Canteen', 6, 1),
(46, 39, 'Bookstore', 7, 1),
(47, NULL, 'ADMINISTRATIVE SERVICES', 6, 1),
(48, 47, 'Registrar', 1, 1),
(49, 47, 'Accounting / Cashier', 2, 1),
(50, 47, 'Admissions', 3, 1),
(51, 47, 'Human Resources Department', 4, 1),
(52, 47, 'General Services Department', 5, 1),
(53, 47, 'Property', 6, 1),
(54, 47, 'MECTECH Custodian Laboratory', 7, 1),
(55, 47, 'TESDA', 8, 1),
(56, NULL, 'INSTITUTIONAL / SUPPORT OFFICES', 7, 1),
(57, 56, 'Sales & Marketing', 1, 1),
(58, 56, 'Quality Assurance Office', 2, 1),
(59, 56, 'Data Privacy Office', 3, 1),
(60, 56, 'College Library', 4, 1),
(61, NULL, 'LINKAGES', 8, 1),
(62, 61, 'Community Extension Services', 1, 1),
(63, 61, 'Campus Ministry', 2, 1),
(64, 61, 'SPER', 3, 1),
(65, 61, 'NSTP', 4, 1),
(66, NULL, 'SAFETY AND SECURITY', 9, 1),
(67, 66, 'Security', 1, 1),
(68, 66, 'CCTV Safety Office', 2, 1),
(69, 66, 'Safety', 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `mail_recipients`
--

CREATE TABLE `mail_recipients` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mail_recipients`
--

INSERT INTO `mail_recipients` (`id`, `email`, `name`, `is_active`, `created_at`) VALUES
(1, 'kakarot8823@gmail.com', 'ITS Office', 1, '2026-09-16 05:49:47'),
(3, 'macatangayfrancis1@gmail.com', 'Super Shy', 1, '2026-09-16 07:47:12');

-- --------------------------------------------------------

--
-- Table structure for table `projector_requests`
--

CREATE TABLE `projector_requests` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(50) DEFAULT '',
  `date_requested` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `venue` varchar(150) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `date_needed` date NOT NULL,
  `time_from` varchar(30) NOT NULL,
  `time_to` varchar(30) NOT NULL,
  `projector_units` int(11) NOT NULL DEFAULT 1,
  `equipment` text DEFAULT NULL COMMENT 'JSON array: screen, extension, hdmi_vga, laptop',
  `remarks` text DEFAULT NULL,
  `additional_dates` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reminder_1hr_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_30min_sent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projector_requests`
--

INSERT INTO `projector_requests` (`id`, `reference_no`, `date_requested`, `name`, `position`, `department`, `contact_number`, `venue`, `purpose`, `date_needed`, `time_from`, `time_to`, `projector_units`, `equipment`, `remarks`, `additional_dates`, `created_at`, `reminder_1hr_sent`, `reminder_30min_sent`) VALUES
(11, 'LCDR-20260915-0011', '2026-09-15', 'Francis Macatangay', 'Staff', 'SHS Canteen', '09802056555', 'EMC', 'SEMINAR', '2026-09-23', '8:29 AM', '10:00 AM', 2, '[\"Projector Screen\",\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-15 02:47:24', 0, 0),
(12, 'LCDR-20260924-0012', '2026-09-24', 'mhar', 'Faculty', 'SCHOOL DIRECTOR\'S OFFICE', '09999999999', 'ASD', 'jnkjnknjkj', '2026-09-19', '12:15', '12:20', 2, '[\"Projector Screen\",\"Extension Cord\"]', '', '[{\"date\":\"2026-09-24\",\"time_from\":\"12:15\",\"time_to\":\"12:36\"}]', '2026-09-15 04:27:31', 0, 0),
(13, 'LCDR-20260917-0013', '2026-09-17', 'mhar', 'Student Assistant', 'SCHOOL DIRECTOR\'S OFFICE', '09999999999', '123', '123', '2026-09-16', '12:30', '12:34', 1, '[\"Projector Screen\",\"Extension Cord\"]', '', '[{\"date\":\"2026-09-16\",\"time_from\":\"12:30\",\"time_to\":\"12:33\"}]', '2026-09-15 04:29:03', 0, 0),
(14, 'LCDR-20260915-0014', '2026-09-15', 'Francis Macatangay', 'Staff', 'SCHOOL DIRECTOR\'S OFFICE', '09999999999', 'EMC', 'SEMINAR', '2026-09-23', '12:38', '15:39', 1, '[\"HDMI \\/ VGA Cable\"]', '', '[{\"date\":\"2026-09-17\",\"time_from\":\"12:42\",\"time_to\":\"16:41\"}]', '2026-09-15 04:38:55', 0, 0),
(15, 'LCDR-20260915-0015', '2026-09-15', 'Francis Macatangay', 'Student Assistant', 'SHS Guidance', '09850200000', 'EMC', 'Seminar', '2026-09-24', '09:00', '22:00', 2, '[\"Projector Screen\",\"Extension Cord\"]', '', '[]', '2026-09-15 04:55:23', 0, 0),
(16, 'LCDR-20260918-0016', '2026-09-18', 'jl', 'Faculty', 'EDUC', '09999999999', 'GYM', 'SEMINAR', '2026-09-18', '15:51', '19:56', 1, '[\"Extension Cord\"]', '', '[]', '2026-09-15 07:49:29', 0, 0),
(17, 'LCDR-20260924-0017', '2026-09-24', 'mhar', 'Student Assistant', 'SCHOOL DIRECTOR\'S OFFICE', '09999999999', 'GYM', 'SEMINAR', '2026-09-24', '08:59', '09:58', 2, '[\"Projector Screen\"]', '', '[{\"date\":\"2026-09-17\",\"time_from\":\"17:00\",\"time_to\":\"18:01\"}]', '2026-09-15 07:58:51', 0, 0),
(18, 'LCDR-20260915-0018', '2026-09-15', 'Francis Macatangay', 'Faculty', 'SCHOOL DIRECTOR\'S OFFICE', '09324444444', 'gym', 'seminar', '2026-09-16', '07:34', '20:35', 2, '[\"Projector Screen\",\"Extension Cord\"]', '', '[{\"date\":\"2026-09-17\",\"time_from\":\"19:36\",\"time_to\":\"21:37\"}]', '2026-09-15 08:31:56', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `software_options`
--

CREATE TABLE `software_options` (
  `id` int(11) NOT NULL,
  `category` enum('licensed','freeware') NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `software_options`
--

INSERT INTO `software_options` (`id`, `category`, `name`, `sort_order`) VALUES
(1, 'licensed', 'Microsoft Office', 0),
(3, 'freeware', 'MySQL', 0),
(4, 'freeware', 'Cisco Packet Tracer', 1),
(5, 'freeware', 'Xampp', 2),
(6, 'freeware', 'Sublime Text', 3),
(7, 'freeware', 'Lan School', 4),
(8, 'freeware', 'SDK & NetBeans', 5),
(9, 'freeware', 'Portable Photoshop', 6),
(10, 'freeware', 'Dev C++', 7),
(11, 'freeware', 'Python', 8),
(12, 'freeware', 'Unity', 9),
(13, 'freeware', 'Visual Studio', 10),
(14, 'freeware', 'Office', 11);

-- --------------------------------------------------------

--
-- Table structure for table `software_request`
--

CREATE TABLE `software_request` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(50) DEFAULT '',
  `date_requested` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `computer_laboratory` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `licensed_software` text DEFAULT NULL,
  `freeware` text DEFAULT NULL,
  `supervisor_name` varchar(150) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `software_request`
--

INSERT INTO `software_request` (`id`, `reference_no`, `date_requested`, `name`, `position`, `computer_laboratory`, `department`, `licensed_software`, `freeware`, `supervisor_name`, `created_at`) VALUES
(6, 'SIRF-20260915-0006', '2026-09-15', 'Francis Macatangay', 'Faculty', '216', 'BASIC EDUCATION DEPARTMENT', '[\"Microsoft Office\"]', '[\"Unity\"]', '', '2026-09-15 08:28:57'),
(7, 'SIRF-20260930-0007', '2026-09-30', 'Francis', 'Custodian', '216', 'College of Engineering', '[\"qwe\",\"Microsoft Office\"]', '[\"Cisco Packet Tracer\",\"Sublime Text\"]', 'Mhar', '2026-09-16 07:47:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `dropdown_option`
--
ALTER TABLE `dropdown_option`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dropdown_option` (`parent_id`,`option_value`),
  ADD KEY `idx_dropdown_option_parent` (`parent_id`);

--
-- Indexes for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mail_recipient_email` (`email`);

--
-- Indexes for table `projector_requests`
--
ALTER TABLE `projector_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `software_options`
--
ALTER TABLE `software_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `software_request`
--
ALTER TABLE `software_request`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dropdown_option`
--
ALTER TABLE `dropdown_option`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projector_requests`
--
ALTER TABLE `projector_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `software_options`
--
ALTER TABLE `software_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `software_request`
--
ALTER TABLE `software_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dropdown_option`
--
ALTER TABLE `dropdown_option`
  ADD CONSTRAINT `fk_dropdown_option_parent` FOREIGN KEY (`parent_id`) REFERENCES `dropdown_option` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
