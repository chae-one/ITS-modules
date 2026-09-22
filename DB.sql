-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 03:35 AM
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
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL,
  `action` varchar(40) NOT NULL,
  `event_type` varchar(100) NOT NULL DEFAULT '',
  `requested_by` varchar(150) NOT NULL DEFAULT '',
  `summary` varchar(500) NOT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `created_at`, `action`, `event_type`, `requested_by`, `summary`, `details`) VALUES
(1, '2026-09-21 16:21:48', 'request_done', '', '', 'Projector request #48 marked done for 2026-09-04', '{\"date\":\"2026-09-04\"}'),
(2, '2026-09-21 16:27:49', 'request_done', '', '', 'Projector request #48 marked done for 2026-09-03', '{\"date\":\"2026-09-03\"}'),
(3, '2026-09-21 16:27:52', 'request_reopened', '', '', 'Projector request #48 reopened for 2026-09-02', '{\"date\":\"2026-09-02\"}'),
(4, '2026-09-21 16:28:19', 'request_cancelled', '', '', 'Projector request #62 cancelled (asas)', '{\"reason\":\"asas\"}'),
(5, '2026-09-21 17:09:21', 'request_reopened', '', '', 'Projector request #29 reopened for 2026-09-12', '{\"date\":\"2026-09-12\"}'),
(6, '2026-09-21 17:18:35', 'request_done', '', 'Noreen Mae Flores', 'Projector request marked done for 2026-09-12', '{\"date\":\"2026-09-12\"}'),
(7, '2026-09-21 17:34:46', 'request_submitted', 'ENHANCEMENT PROGRAM (AB COMM)', 'Kristel P. Mendoza', 'Projector request (Seminar) submitted for 2026-08-29', '{\"date_requested\":\"2026-08-05\",\"date_needed\":\"2026-08-29\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Seminar\"}'),
(8, '2026-09-21 17:35:14', 'request_done', 'ENHANCEMENT PROGRAM (AB COMM)', 'Kristel P. Mendoza', 'Projector request (Seminar) marked done for 2026-08-29', '{\"date\":\"2026-08-29\",\"type_of_event\":\"Seminar\"}'),
(9, '2026-09-21 17:37:41', 'request_submitted', 'PQA Orientation', 'MARIANNE R. DELA CRUZ', 'Projector request (Orientation) submitted for 2026-08-28', '{\"date_requested\":\"2026-07-27\",\"date_needed\":\"2026-08-28\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Orientation\"}'),
(10, '2026-09-21 17:41:16', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(11, '2026-09-21 17:42:06', 'request_submitted', 'PEER TRAINING', 'MR. SALVADOR BALAGOT', 'Projector request (Training) submitted for 2026-08-28', '{\"date_requested\":\"2026-08-14\",\"date_needed\":\"2026-08-28\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"AMPLI\",\"units\":1,\"type_of_event\":\"Training\"}'),
(12, '2026-09-21 17:47:41', 'request_submitted', 'Buwan ng Wika 2026 Celebration (SHS)', 'Anelee San Dieogo', 'Projector request (Event) submitted for 2026-08-27', '{\"date_requested\":\"2026-07-17\",\"date_needed\":\"2026-08-27\",\"time_from\":\"12:00\",\"time_to\":\"16:00\",\"more_dates\":[],\"venue\":\"GYM\",\"units\":1,\"type_of_event\":\"Event\"}'),
(13, '2026-09-21 17:50:53', 'request_submitted', 'INDUCTION OF OFFICERS', 'Anelee San Dieogo', 'Projector request (INDUCTION OF OFFICERS) submitted for 2026-08-26', '{\"date_requested\":\"2026-08-24\",\"date_needed\":\"2026-08-26\",\"time_from\":\"13:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"INDUCTION OF OFFICERS\"}'),
(14, '2026-09-22 07:47:24', 'request_submitted', 'PQA Orientation', 'Norea Mae Gutierrez', 'Projector request (Orientation) submitted for 2026-08-18 (+1 more date)', '{\"date_requested\":\"2026-08-04\",\"date_needed\":\"2026-08-18\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"more_dates\":[\"2026-08-19\"],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Orientation\"}'),
(15, '2026-09-22 07:59:34', 'request_submitted', 'Buwan ng Wika 2026 Celebration (SHS)', 'Anelee San Dieogo', 'Projector request (Event) submitted for 2026-08-20', '{\"date_requested\":\"2026-07-17\",\"date_needed\":\"2026-08-20\",\"time_from\":\"12:00\",\"time_to\":\"16:00\",\"more_dates\":[],\"venue\":\"GYM\",\"units\":1,\"type_of_event\":\"Event\"}'),
(16, '2026-09-22 08:11:31', 'request_submitted', 'PQA Orientation ( Students)', 'Norea Mae Gutierrez', 'Projector request (Orientation) submitted for 2026-08-24 (+3 more dates)', '{\"date_requested\":\"2026-09-22\",\"date_needed\":\"2026-08-24\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[\"2026-08-25\",\"2026-08-26\",\"2026-08-27\"],\"venue\":\"GYM\",\"units\":1,\"type_of_event\":\"Orientation\"}'),
(17, '2026-09-22 08:37:51', 'request_submitted', 'College of Criminology', 'Eva Melissa V. Pampay', 'Projector request (Seminar) submitted for 2026-09-25', '{\"date_requested\":\"2026-09-21\",\"date_needed\":\"2026-09-25\",\"time_from\":\"09:00\",\"time_to\":\"11:00\",\"more_dates\":[],\"venue\":\"Amphitheater\",\"units\":1,\"type_of_event\":\"Seminar\"}'),
(18, '2026-09-22 08:49:30', 'request_done', 'PQA Orientation', 'Norea Mae Gutierrez', 'Projector request (Orientation) marked done for 2026-08-18', '{\"date\":\"2026-08-18\",\"type_of_event\":\"Orientation\"}'),
(19, '2026-09-22 08:49:32', 'request_done', 'PQA Orientation', 'Norea Mae Gutierrez', 'Projector request (Orientation) marked done for 2026-08-19', '{\"date\":\"2026-08-19\",\"type_of_event\":\"Orientation\"}'),
(20, '2026-09-22 08:49:35', 'request_done', 'PQA Orientation ( Students)', 'Norea Mae Gutierrez', 'Projector request (Orientation) marked done for 2026-08-24', '{\"date\":\"2026-08-24\",\"type_of_event\":\"Orientation\"}'),
(21, '2026-09-22 08:49:36', 'request_done', 'PQA Orientation ( Students)', 'Norea Mae Gutierrez', 'Projector request (Orientation) marked done for 2026-08-25', '{\"date\":\"2026-08-25\",\"type_of_event\":\"Orientation\"}'),
(22, '2026-09-22 08:49:38', 'request_done', 'PQA Orientation ( Students)', 'Norea Mae Gutierrez', 'Projector request (Orientation) marked done for 2026-08-26', '{\"date\":\"2026-08-26\",\"type_of_event\":\"Orientation\"}'),
(23, '2026-09-22 08:49:41', 'request_done', 'PQA Orientation ( Students)', 'Norea Mae Gutierrez', 'Projector request (Orientation) marked done for 2026-08-27', '{\"date\":\"2026-08-27\",\"type_of_event\":\"Orientation\"}'),
(24, '2026-09-22 08:49:44', 'request_done', 'PQA Orientation', 'MARIANNE R. DELA CRUZ', 'Projector request (Orientation) marked done for 2026-08-28', '{\"date\":\"2026-08-28\",\"type_of_event\":\"Orientation\"}'),
(25, '2026-09-22 08:50:07', 'request_submitted', 'Meeting of  Acads', 'Norea Mae Gutierrez', 'Projector request (Meeting) submitted for 2026-10-12 (+1 more date)', '{\"date_requested\":\"2026-09-21\",\"date_needed\":\"2026-10-12\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[\"2026-10-13\"],\"venue\":\"EMC\",\"units\":0,\"type_of_event\":\"Meeting\"}'),
(26, '2026-09-22 08:51:03', 'request_updated', 'Buwan ng Wika 2026 Celebration (SHS)', 'Anelee San Diego', 'Projector request (Event) updated', '{\"name\":{\"from\":\"Anelee San Dieogo\",\"to\":\"Anelee San Diego\"},\"type_of_event\":\"Event\"}'),
(27, '2026-09-22 08:51:05', 'request_done', 'Buwan ng Wika 2026 Celebration (SHS)', 'Anelee San Diego', 'Projector request (Event) marked done for 2026-08-20', '{\"date\":\"2026-08-20\",\"type_of_event\":\"Event\"}'),
(28, '2026-09-22 08:51:12', 'request_done', 'INDUCTION OF OFFICERS', 'Anelee San Dieogo', 'Projector request (INDUCTION OF OFFICERS) marked done for 2026-08-26', '{\"date\":\"2026-08-26\",\"type_of_event\":\"INDUCTION OF OFFICERS\"}'),
(29, '2026-09-22 08:51:58', 'request_done', 'Buwan ng Wika 2026 Celebration (SHS)', 'Anelee San Dieogo', 'Projector request (Event) marked done for 2026-08-27', '{\"date\":\"2026-08-27\",\"type_of_event\":\"Event\"}'),
(30, '2026-09-22 08:52:03', 'request_done', 'PEER TRAINING', 'MR. SALVADOR BALAGOT', 'Projector request (Training) marked done for 2026-08-28', '{\"date\":\"2026-08-28\",\"type_of_event\":\"Training\"}');

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
('daily_request_limit', '5', '2026-09-16 08:54:12'),
('max_units_per_request', '4', '2026-09-18 03:03:31'),
('projector_total_units', '5', '2026-09-16 08:54:09');

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
(1, 'name1@email.com', 'test1', 0, '2026-09-16 05:49:47'),
(3, 'name2@email.com', 'test2', 0, '2026-09-16 07:47:12'),
(4, 'name3@email.com', 'test3', 0, '2026-09-17 03:46:07'),
(5, 'name4@email.com', 'test4', 0, '2026-09-21 01:24:04'),
(6, 'name5@email.com', 'test5', 0, '2026-09-21 06:51:19'),
(8, 'name6@email.com', 'test6', 0, '2026-09-21 06:51:53'),
(9, 'okay09okay05@gmail.com', 'mhar', 0, '2026-09-21 06:59:11');

-- --------------------------------------------------------

--
-- Table structure for table `projector_requests`
--

CREATE TABLE `projector_requests` (
  `id` int(11) NOT NULL,
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
  `reminder_30min_sent` tinyint(1) NOT NULL DEFAULT 0,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL DEFAULT '',
  `participants` int(10) UNSIGNED DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projector_requests`
--

INSERT INTO `projector_requests` (`id`, `date_requested`, `name`, `position`, `department`, `contact_number`, `venue`, `purpose`, `date_needed`, `time_from`, `time_to`, `projector_units`, `equipment`, `remarks`, `additional_dates`, `created_at`, `reminder_1hr_sent`, `reminder_30min_sent`, `is_cancelled`, `cancelled_at`, `cancel_reason`, `event_type`, `participants`, `is_done`, `done_at`) VALUES
(20, '2026-09-18', 'Kim Leila Ulat', 'Staff', 'College Guidance', '09232323231', 'Gymnasium', 'Parent and child Activity', '2026-09-18', '07:00', '17:00', 2, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'need splittrer', '[]', '2026-09-18 01:58:16', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:09:29'),
(21, '2026-09-18', 'Clesarie P. Bornco', 'Faculty', 'Pharmacy', '', 'GYM', 'World Pharmacy Day 2026', '2026-09-23', '07:00', '17:00', 1, '[\"Extension Cord\",\"wifi connection\"]', '', '[]', '2026-09-18 02:08:30', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(22, '2026-09-09', 'Jhudiel Javier', 'Faculty', 'n/a', '', 'GYM', 'Civil Engineering Days', '2026-09-18', '13:00', '18:00', 2, '[\"Projector Screen\"]', 'Splitter', '[{\"date\":\"2026-09-19\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:14:47\"}]', '2026-09-18 02:08:57', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:11:12'),
(23, '2026-09-15', 'Christine M. Olaybal', 'Faculty', 'n/a', '', 'EMC', 'Ink & Impact : Empowering Future Educators in Campus Journalism and Digital Media', '2026-10-29', '09:00', '15:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 02:14:57', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(24, '2026-09-12', 'Ms. Rui Oasay', 'Staff', 'College of International Hospitality Management', '', 'EMC', 'Enhancement seminar B and P', '2026-10-10', '07:00', '12:00', 1, '[\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', 'ahead of the time dapat nasa EMC na for your mga needs', '[]', '2026-09-18 02:18:24', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(25, '2026-09-12', 'Rui Oasay', 'Faculty', 'college of international services', '', 'EMD', 'Enhancement Seminar - Ban and beverage', '2026-09-21', '11:00', '15:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 02:20:11', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(26, '2026-09-12', 'Ms. Rui Oasay', 'Staff', 'College of International Hospitality Management', '', 'EMC', 'Enhancement seminar B and P', '2026-10-10', '13:00', '17:00', 1, '[\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 02:20:40', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(27, '2026-09-04', 'Norea Mae Gutierrez', 'Faculty', 'n/a', '', 'GYM', 'PQA ORIENTATION FOR BUSINESS CLUSTER', '2026-09-15', '09:00', '12:00', 2, '[\"HDMI \\/ VGA Cable\",\"Laptop\"]', 'Splitter', '[]', '2026-09-18 02:22:48', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:07:22'),
(28, '2026-09-15', 'Kristel P. Mendoza', 'Staff', 'n/a', '', 'EMC', 'Lets Lixcel: Intensive Examination for Teachers Review and Test-Tasking Stratergies Seminaer', '2026-10-10', '09:00', '15:00', 1, '[]', '', '[]', '2026-09-18 02:26:13', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(29, '2026-09-07', 'Noreen Mae Flores', 'Faculty', 'n/a', '', 'JBHS Quadrangle', 'PT Days 2026: Team Building', '2026-09-12', '08:00', '15:00', 1, '[\"Projector Screen\"]', '', '[]', '2026-09-18 02:27:13', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 17:18:35'),
(30, '2026-09-09', 'Christine M. Olaybal', 'Staff', 'n/a', '', 'EMC', 'CASED TEACHER\'S DAY CELEBRATION', '2026-10-06', '13:00', '17:00', 1, '[]', '', '[]', '2026-09-18 02:28:44', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(31, '2026-07-27', 'Marianne R. Dela Cruz', 'Faculty', 'n/a', '', 'EMC', 'PQA Orientation', '2026-09-11', '08:00', '17:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\",\"Laptop\"]', '', '[]', '2026-09-18 02:29:08', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:22'),
(32, '2026-09-07', 'Noreen Mae Flores', 'Faculty', 'n/a', '', 'EMC', 'Enrichment Seminar', '2026-09-11', '10:00', '12:00', 1, '[\"Projector Screen\",\"Teachmint\"]', '', '[]', '2026-09-18 02:31:22', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:20'),
(33, '2026-08-28', 'Jade Marie M Gunio', 'Faculty', 'n/a', '', 'GYM', 'Living Rosary', '2026-10-26', '08:00', '12:00', 1, '[]', '', '[]', '2026-09-18 02:32:54', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(34, '2026-09-18', 'Karol Tolentino', 'Faculty', 'Basic Education Department', '', 'GYM', 'Teachers\' Day GS and JBHS', '2026-10-01', '08:00', '12:00', 1, '[]', '', '[]', '2026-09-18 02:39:53', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(35, '2026-09-01', 'Noreen Mae Flores', 'Faculty', 'n/a', '', 'Main Lobby Hallway', 'PT days 2026: Interactive Mini games', '2026-09-10', '11:00', '15:00', 1, '[\"Projector Screen\",\"Mobile TV\"]', '', '[]', '2026-09-18 02:42:05', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:18'),
(36, '2026-08-03', 'Anelee San Diego', 'Principal', 'Basic Education Principal\'s Office', '', 'GYM', 'ACQUAINTANCE(SHS)', '2026-09-25', '10:00', '19:00', 1, '[]', '', '[]', '2026-09-18 02:42:27', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL),
(37, '2026-09-07', 'Noreen Mae Flores', 'Faculty', 'n/a', '', 'EMC', 'PT Days 2026 : Inspirational Talks with COPT Alumni', '2026-09-10', '08:00', '10:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\",\"TV\"]', '', '[]', '2026-09-18 02:44:16', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:16'),
(38, '2026-08-20', 'Kim Leila P. Ulat', 'Staff', 'n/a', '', 'GYM', 'Parent and Child Activity', '2026-09-18', '07:30', '12:00', 1, '[]', '', '[]', '2026-09-18 02:45:48', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:10:22'),
(39, '2026-09-19', 'NOREA MAE GUTIERREZ', 'Staff', 'n/a', '', 'EMC', 'PQA ORIENTATION (w/ DR. MARJ', '2026-09-07', '07:00', '18:00', 1, '[]', '', '[{\"date\":\"2026-09-06\",\"time_from\":\"07:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:03:00\"},{\"date\":\"2026-09-09\",\"time_from\":\"07:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:03:09\"}]', '2026-09-18 02:49:48', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:57:25'),
(40, '2026-08-10', 'Alodia M. Edrinal', 'Faculty', 'n/a', '', 'Room to Room', 'Mental Health Guidance Sessions', '2026-09-08', '06:00', '18:00', 2, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'needed 4 projectors', '[{\"date\":\"2026-09-09\",\"time_from\":\"06:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:03:11\"}]', '2026-09-18 02:49:58', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:06'),
(41, '2026-08-24', 'Julie Fate L. Dalde', 'Faculty', 'n/a', '', 'Lobby', 'Bingo For a Cause', '2026-09-04', '14:00', '22:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'Zoom Setup', '[]', '2026-09-18 02:51:54', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:57:18'),
(42, '2026-09-02', 'Kristel P. Mendoza', 'Staff', 'n/a', '', 'EMC', 'Empowering Perpetual Student Leaders For Transforming and Servant Leadership with EQ', '2026-09-07', '07:00', '18:00', 1, '[]', '', '[]', '2026-09-18 02:53:17', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:03'),
(43, '2026-07-27', 'D. Montaya', 'Faculty', 'n/a', '', 'EMC', 'PQA Orientation', '2026-09-04', '08:00', '17:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\",\"Laptop\"]', 'Computer Set with Laptop', '[]', '2026-09-18 02:54:21', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:57:15'),
(44, '2026-09-02', 'Mr. RIC C. TANDIGAN', 'n/a', 'n/a', '', 'GYM', '7th ROTC TRAINING DAY', '2026-09-05', '07:00', '10:00', 1, '[]', '', '[]', '2026-09-18 02:56:36', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:57:21'),
(45, '2026-08-13', 'Kris Jan Jay Aranilla', 'Faculty', 'n/a', '', 'EMC', 'ARCHITECTURE ORIENTATION 2026', '2026-09-03', '08:00', '18:00', 1, '[\"Projector Screen\"]', 'OHP visual presentation using acetate', '[]', '2026-09-18 02:59:27', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:56:59'),
(46, '2026-09-10', 'MS. Alodia M. Edrinal', 'n/a', 'n/a', '', 'Room to Room', 'Mental Health Guidance Session', '2026-09-15', '07:00', '20:00', 2, '[\"3 Projector Request\"]', '', '[{\"date\":\"2026-09-16\",\"time_from\":\"07:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:11:56\"}]', '2026-09-18 03:00:34', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:05:54'),
(47, '2026-09-18', 'Juliana Cabiling', 'SSC', 'n/a', '', 'EMC', 'Presidential Meeting', '2026-09-17', '13:00', '17:00', 1, '[]', '', '[]', '2026-09-18 03:02:49', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:08:10'),
(48, '2026-09-19', 'Norea Mae Gutierrez', 'Faculty', 'n/a', '', 'GYM', 'PQA Orientation', '2026-09-01', '08:00', '18:00', 2, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'splitter', '[{\"date\":\"2026-09-02\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":false,\"done_at\":null},{\"date\":\"2026-09-04\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:21:48\"},{\"date\":\"2026-09-14\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:05:48\"},{\"date\":\"2026-09-03\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:27:49\"}]', '2026-09-18 03:03:08', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:42:34'),
(49, '2026-09-15', 'Ms. Rui Oasay', 'n/a', 'College of International Hospitality Management', '', 'EMC', 'Enhancement Seminar ((Prof Dev)', '2026-09-17', '13:00', '15:00', 1, '[\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 03:05:15', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:08:29'),
(65, '2026-08-05', 'Kristel P. Mendoza', 'Heads / Dean', 'n/a', '', 'EMC', 'ENHANCEMENT PROGRAM (AB COMM)', '2026-08-29', '08:00', '17:00', 1, '[]', '', '[]', '2026-09-21 09:34:46', 0, 0, 0, NULL, NULL, 'Seminar', 40, 1, '2026-09-21 17:35:14'),
(66, '2026-07-27', 'MARIANNE R. DELA CRUZ', 'Heads / Dean', 'n/a', '', 'EMC', 'PQA Orientation', '2026-08-28', '08:00', '17:00', 1, '[]', '', '[]', '2026-09-21 09:37:41', 0, 0, 0, NULL, NULL, 'Orientation', 40, 1, '2026-09-22 08:49:44'),
(67, '2026-08-14', 'MR. SALVADOR BALAGOT', 'Heads / Dean', 'n/a', '', 'AMPLI', 'PEER TRAINING', '2026-08-28', '08:00', '17:00', 1, '[]', 'AMPL', '[]', '2026-09-21 09:42:06', 0, 0, 0, NULL, NULL, 'Training', 60, 1, '2026-09-22 08:52:03'),
(68, '2026-07-17', 'Anelee San Dieogo', 'Heads / Dean', 'n/a', '', 'GYM', 'Buwan ng Wika 2026 Celebration (SHS)', '2026-08-27', '12:00', '16:00', 1, '[]', '', '[]', '2026-09-21 09:47:41', 0, 0, 0, NULL, NULL, 'Event', 100, 1, '2026-09-22 08:51:58'),
(69, '2026-08-24', 'Anelee San Dieogo', 'Heads / Dean', 'n/a', '', 'EMC', 'INDUCTION OF OFFICERS', '2026-08-26', '13:00', '17:00', 1, '[]', '', '[]', '2026-09-21 09:50:53', 0, 0, 0, NULL, NULL, 'INDUCTION OF OFFICERS', 50, 1, '2026-09-22 08:51:11'),
(70, '2026-08-04', 'Norea Mae Gutierrez', 'Faculty', 'n/a', '', 'EMC', 'PQA Orientation', '2026-08-18', '08:00', '18:00', 1, '[]', '', '[{\"date\":\"2026-08-19\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:32\"}]', '2026-09-21 23:47:24', 0, 0, 0, NULL, NULL, 'Orientation', 100, 1, '2026-09-22 08:49:30'),
(71, '2026-07-17', 'Anelee San Diego', 'Heads / Dean', 'n/a', '', 'GYM', 'Buwan ng Wika 2026 Celebration (SHS)', '2026-08-20', '12:00', '16:00', 1, '[]', '', '[]', '2026-09-21 23:59:34', 0, 0, 0, NULL, NULL, 'Event', 100, 1, '2026-09-22 08:51:05'),
(72, '2026-09-22', 'Norea Mae Gutierrez', 'Faculty', 'n/a', '', 'GYM', 'PQA Orientation ( Students)', '2026-08-24', '08:00', '17:00', 1, '[\"Projector Screen\"]', '', '[{\"date\":\"2026-08-25\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:36\"},{\"date\":\"2026-08-26\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:38\"},{\"date\":\"2026-08-27\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:41\"}]', '2026-09-22 00:11:31', 0, 0, 0, NULL, NULL, 'Orientation', 150, 1, '2026-09-22 08:49:35'),
(73, '2026-09-21', 'Eva Melissa V. Pampay', 'Staff', 'n/a', '', 'Amphitheater', 'College of Criminology', '2026-09-25', '09:00', '11:00', 1, '[\"Projector Screen\"]', '', '[]', '2026-09-22 00:37:51', 0, 0, 0, NULL, NULL, 'Seminar', 50, 0, NULL),
(74, '2026-09-21', 'Norea Mae Gutierrez', 'Faculty', 'n/a', '', 'EMC', 'Meeting of  Acads', '2026-10-12', '08:00', '17:00', 0, '[\"teachmint, whiteboard\"]', 'n/a', '[{\"date\":\"2026-10-13\",\"time_from\":\"08:00\",\"time_to\":\"17:00\"}]', '2026-09-22 00:50:07', 0, 0, 0, NULL, NULL, 'Meeting', 30, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `service_call_requests`
--

CREATE TABLE `service_call_requests` (
  `id` int(11) NOT NULL,
  `date_requested` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `immediate_superior` varchar(150) DEFAULT '',
  `contact_number` varchar(50) NOT NULL,
  `problem_type` text DEFAULT NULL COMMENT 'JSON array: Software, Hardware',
  `description` text NOT NULL,
  `status` enum('Pending','Accomplished') NOT NULL DEFAULT 'Pending',
  `time_attended` varchar(30) DEFAULT NULL,
  `time_accomplished` varchar(30) DEFAULT NULL,
  `its_remarks` text DEFAULT NULL,
  `attended_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `date_requested` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `computer_laboratory` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `licensed_software` text DEFAULT NULL,
  `freeware` text DEFAULT NULL,
  `supervisor_name` varchar(150) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `requested_by` (`requested_by`);

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
-- Indexes for table `service_call_requests`
--
ALTER TABLE `service_call_requests`
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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `dropdown_option`
--
ALTER TABLE `dropdown_option`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `projector_requests`
--
ALTER TABLE `projector_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `service_call_requests`
--
ALTER TABLE `service_call_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `software_options`
--
ALTER TABLE `software_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `software_request`
--
ALTER TABLE `software_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
