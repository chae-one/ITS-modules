-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2026 at 02:06 AM
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
-- Database: `req.`
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
(75, '2026-10-02 13:58:32', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(76, '2026-10-02 14:03:40', 'request_submitted', 'Enrichment Program', 'Dr. M. S. Banatiua', 'Projector request (Event) submitted for 2026-10-07', '{\"date_requested\":\"2026-09-29\",\"date_needed\":\"2026-10-07\",\"time_from\":\"12:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"Room 416 (Amphitheater)\",\"units\":1,\"type_of_event\":\"Event\"}'),
(77, '2026-10-02 14:04:01', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(78, '2026-10-02 14:40:28', 'request_submitted', 'Asean Activity', 'Marvin Rocamora', 'Projector request (Event) submitted for 2026-10-15', '{\"date_requested\":\"2026-10-02\",\"date_needed\":\"2026-10-15\",\"time_from\":\"07:00\",\"time_to\":\"15:00\",\"more_dates\":[],\"venue\":\"Gymnasium\",\"units\":2,\"type_of_event\":\"Event\"}'),
(79, '2026-10-02 14:59:35', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(80, '2026-10-02 14:59:46', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(81, '2026-10-03 20:29:18', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(82, '2026-10-05 09:44:11', 'request_submitted', 'Mcgrawhill Trial Access', 'Judy May Magallon', 'Projector request (Seminar) submitted for 2026-10-07', '{\"date_requested\":\"2026-10-02\",\"date_needed\":\"2026-10-07\",\"time_from\":\"10:30\",\"time_to\":\"18:00\",\"more_dates\":[],\"venue\":\"CLAB3\",\"units\":0,\"type_of_event\":\"Seminar\"}'),
(83, '2026-10-05 16:51:09', 'request_done', 'Teachers\' Day GS and JBHS', 'Karol Tolentino', 'Projector request (Event) marked done for 2026-10-01 - handled by John Raymark Visca', '{\"date\":\"2026-10-01\",\"type_of_event\":\"Event\",\"handled_by\":\"John Raymark Visca\"}'),
(84, '2026-10-06 08:59:57', 'request_submitted', 'Acad Meeting & other Matters ( w/ Dr. Marj', 'Norea Mae Gutierrez', 'Projector request (Meeting) submitted for 2026-10-13 (+1 more date)', '{\"date_requested\":\"2026-10-02\",\"date_needed\":\"2026-10-13\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[\"2026-10-14\"],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Meeting\"}'),
(85, '2026-10-06 10:25:34', 'request_submitted', 'CCHEIA  General Assembly & Election', 'Jaydee Querubin', 'Projector request (Event) submitted for 2026-10-19', '{\"date_requested\":\"2026-09-28\",\"date_needed\":\"2026-10-19\",\"time_from\":\"13:00\",\"time_to\":\"18:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":0,\"type_of_event\":\"Event\"}'),
(86, '2026-10-06 10:33:00', 'request_submitted', 'MANCOM Meeting', 'Norea Mae Gutierrez', 'Projector request (Meeting) submitted for 2026-10-07', '{\"date_requested\":\"2026-10-06\",\"date_needed\":\"2026-10-07\",\"time_from\":\"09:00\",\"time_to\":\"12:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":0,\"type_of_event\":\"Meeting\"}'),
(87, '2026-10-06 14:10:43', 'request_submitted', 'Career Guidance', 'Ms. Alodia  M Endrinal', 'Projector request (Event) submitted for 2026-10-21', '{\"date_requested\":\"2026-09-30\",\"date_needed\":\"2026-10-21\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"gym\",\"units\":2,\"type_of_event\":\"Event\"}'),
(88, '2026-10-06 14:13:19', 'request_updated', 'Career Guidance', 'Ms. Alodia  M Endrinal', 'Projector request (Event) updated', '{\"position\":{\"from\":\"Heads \\/ Dean\",\"to\":\"Faculty\"},\"type_of_event\":\"Event\"}'),
(89, '2026-10-06 14:13:52', 'request_updated', 'Career Guidance', 'Ms. Alodia  M Endrinal', 'Projector request (Event) updated', '{\"position\":{\"from\":\"Faculty\",\"to\":\"Staff\"},\"type_of_event\":\"Event\"}'),
(90, '2026-10-06 15:43:05', 'request_submitted', 'Enrichment Program', 'Michael Rivera', 'Projector request (Event) submitted for 2026-10-08', '{\"date_requested\":\"2026-10-02\",\"date_needed\":\"2026-10-08\",\"time_from\":\"08:00\",\"time_to\":\"16:00\",\"more_dates\":[],\"venue\":\"416 - Amphitheater\",\"units\":1,\"type_of_event\":\"Event\"}'),
(91, '2026-10-07 10:19:41', 'request_submitted', 'BASIC ROTC LECTURE', 'Ric C. Tandingan', 'Projector request (Training) submitted for 2026-10-10', '{\"date_requested\":\"2026-10-07\",\"date_needed\":\"2026-10-10\",\"time_from\":\"07:00\",\"time_to\":\"10:00\",\"more_dates\":[],\"venue\":\"gym\",\"units\":2,\"type_of_event\":\"Training\"}'),
(92, '2026-10-07 11:15:20', 'request_submitted', 'Sangguniang Kabataan', 'Alexis Basileyo', 'Projector request (SK event) submitted for 2026-10-24', '{\"date_requested\":\"2026-09-26\",\"date_needed\":\"2026-10-24\",\"time_from\":\"13:00\",\"time_to\":\"19:00\",\"more_dates\":[],\"venue\":\"gym\",\"units\":1,\"type_of_event\":\"SK event\"}'),
(93, '2026-10-07 17:24:56', 'request_submitted', 'Emergency Meeting for All Medtech Students', 'Hildegard Coloma', 'Projector request (Meeting) submitted for 2026-10-08', '{\"date_requested\":\"2026-10-05\",\"date_needed\":\"2026-10-08\",\"time_from\":\"09:00\",\"time_to\":\"12:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Meeting\"}'),
(94, '2026-10-08 08:37:58', 'request_updated', 'Emergency Meeting for All Medtech Students', 'Hildegard Coloma', 'Projector request (Meeting) updated', '{\"position\":{\"from\":\"Staff\",\"to\":\"Program Chair\"},\"type_of_event\":\"Meeting\"}'),
(95, '2026-10-08 08:54:50', 'settings_updated', '', '', 'Projector borrowing limits changed', '{\"before\":{\"projector_total_units\":5,\"max_units_per_request\":4,\"daily_request_limit\":5},\"after\":{\"projector_total_units\":4,\"max_units_per_request\":4,\"daily_request_limit\":1}}'),
(96, '2026-10-08 09:48:42', 'request_updated', 'Emergency Meeting for All Medtech Students', 'Ms. Coloma', 'Projector request (Meeting) updated', '{\"name\":{\"from\":\"Hildegard Coloma\",\"to\":\"Ms. Coloma\"},\"type_of_event\":\"Meeting\"}'),
(97, '2026-10-08 10:25:49', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(102, '2026-10-08 11:04:30', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(103, '2026-10-08 13:08:31', 'request_submitted', 'Conference Meeting', 'Mr. Ricardo E. Tandigan', 'Projector request (Meeting) submitted for 2026-10-09', '{\"date_requested\":\"2026-10-07\",\"date_needed\":\"2026-10-09\",\"time_from\":\"06:00\",\"time_to\":\"18:00\",\"more_dates\":[],\"venue\":\"Room 204\",\"units\":1,\"type_of_event\":\"Meeting\"}'),
(104, '2026-10-08 14:17:16', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(105, '2026-10-08 14:17:19', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(106, '2026-10-08 14:22:15', 'settings_updated', '', '', 'Projector borrowing limits changed', '{\"before\":{\"projector_total_units\":4,\"max_units_per_request\":4,\"daily_request_limit\":1},\"after\":{\"projector_total_units\":4,\"max_units_per_request\":4,\"daily_request_limit\":4}}'),
(107, '2026-10-08 14:22:20', 'request_submitted', 'ME One Day League', 'na', 'Projector request (Event) submitted for 2026-10-09', '{\"date_requested\":\"2026-09-10\",\"date_needed\":\"2026-10-09\",\"time_from\":\"07:00\",\"time_to\":\"20:00\",\"more_dates\":[],\"venue\":\"Gymnasium\",\"units\":2,\"type_of_event\":\"Event\"}'),
(108, '2026-10-08 15:42:49', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(109, '2026-10-08 16:01:55', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(115, '2026-10-08 16:19:44', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(116, '2026-10-09 08:09:26', 'request_submitted', 'Investiture and Overnight Camping', 'Sophia Gracia Nronne G. Erni', 'Projector request (Event) submitted for 2026-11-05 (+1 more date)', '{\"date_requested\":\"2026-08-28\",\"date_needed\":\"2026-11-05\",\"time_from\":\"08:00\",\"time_to\":\"22:00\",\"more_dates\":[\"2026-11-06\"],\"venue\":\"Gym\",\"units\":2,\"type_of_event\":\"Event\"}'),
(117, '2026-10-10 12:31:35', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(118, '2026-10-12 12:21:10', 'request_updated', 'CELEBRATION OF MEDTECH WEEK', 'Ms. Coloma', 'Projector request (Event) updated', '{\"name\":{\"from\":\"Hilidigard Coloma\",\"to\":\"Ms. Coloma\"},\"type_of_event\":\"Event\"}'),
(119, '2026-10-12 12:40:54', 'request_submitted', 'ENGINEERING DAYS', 'n.a', 'Projector request (Event) submitted for 2026-10-19 (+1 more date)', '{\"date_requested\":\"2026-09-23\",\"date_needed\":\"2026-10-19\",\"time_from\":\"07:00\",\"time_to\":\"20:30\",\"more_dates\":[\"2026-10-20\"],\"venue\":\"GYM\",\"units\":2,\"type_of_event\":\"Event\"}'),
(120, '2026-10-12 13:36:27', 'request_updated', 'Grade School Sportsfest 2026', 'Mary Jane Sario', 'Projector request (Event) updated (schedule changed)', '{\"date_needed\":{\"from\":\"2026-12-14\",\"to\":\"2026-11-09\"},\"extra_date_1\":{\"from\":\"2026-12-15\",\"to\":\"2026-11-10\"},\"extra_date_2\":{\"from\":\"2026-12-16\",\"to\":\"2026-11-11\"},\"type_of_event\":\"Event\"}'),
(121, '2026-10-13 10:18:10', 'request_cancelled', 'Investiture and Overnight Camping', 'Sophia Gracia Nronne G. Erni', 'Projector request (Event) cancelled (Cancel Change Date)', '{\"reason\":\"Cancel Change Date\",\"type_of_event\":\"Event\"}'),
(122, '2026-10-14 03:32:44', 'request_submitted', 'ERT/ FIRE BRIGADE TRAINING', 'Norea Mae Gutierrez', 'Teachmint request (Training) submitted for 2026-10-16', '{\"date_requested\":\"2026-10-13\",\"date_needed\":\"2026-10-16\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Training\"}'),
(123, '2026-10-14 09:35:18', 'request_cancelled', 'GS Sportfest 2026', 'Mary jane  Sario', 'Projector request (Event) cancelled (Duplicate entry)', '{\"reason\":\"Duplicate entry\",\"type_of_event\":\"Event\"}'),
(124, '2026-10-14 13:22:25', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(125, '2026-10-14 13:26:53', 'request_submitted', 'TOEIC ORIENTATION', 'Mariene Portal', 'Projector request (Orientation) submitted for 2026-12-02', '{\"date_requested\":\"2026-10-07\",\"date_needed\":\"2026-12-02\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"GYM\",\"units\":1,\"type_of_event\":\"Orientation\"}'),
(127, '2026-10-15 09:16:12', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(128, '2026-10-15 09:16:19', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(129, '2026-10-15 11:05:02', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(130, '2026-10-15 11:05:10', 'recipient_updated', '', '', 'Updated 7 recipient(s)', '{\"saved\":7,\"skipped\":0}'),
(131, '2026-10-15 11:09:16', 'request_submitted', 'Case Presentation', 'Melanie Banaticla', 'Projector request (Presentation) submitted for 2026-10-19 (+1 more date)', '{\"date_requested\":\"2026-10-14\",\"date_needed\":\"2026-10-19\",\"time_from\":\"11:00\",\"time_to\":\"21:00\",\"more_dates\":[\"2026-10-20\"],\"venue\":\"Room 415\",\"units\":1,\"type_of_event\":\"Presentation\"}'),
(132, '2026-10-15 13:06:55', 'request_submitted', 'Case Presentation', 'Arvin Bryce S. Zureta', 'Projector request (Presentation) submitted for 2026-10-22', '{\"date_requested\":\"2026-10-15\",\"date_needed\":\"2026-10-22\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"more_dates\":[],\"venue\":\"SHS Building Room 438\",\"units\":1,\"type_of_event\":\"Presentation\"}'),
(133, '2026-10-15 14:21:58', 'request_submitted', 'A Taste of the World: Global Food Festival', 'Cristina', 'Projector request (Presentation) submitted for 2026-11-03', '{\"date_requested\":\"2026-10-13\",\"date_needed\":\"2026-11-03\",\"time_from\":\"07:00\",\"time_to\":\"16:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Presentation\"}'),
(134, '2026-10-15 15:37:15', 'request_submitted', 'MOU Signing (Synergy Conference 2026)', 'Corrine Lechuga', 'Projector request (Conference) submitted for 2026-10-23', '{\"date_requested\":\"2026-10-15\",\"date_needed\":\"2026-10-23\",\"time_from\":\"12:00\",\"time_to\":\"18:00\",\"more_dates\":[],\"venue\":\"EMC\",\"units\":1,\"type_of_event\":\"Conference\"}');

-- --------------------------------------------------------

--
-- Table structure for table `activity_log_archives`
--

CREATE TABLE `activity_log_archives` (
  `id` int(10) UNSIGNED NOT NULL,
  `ym` char(7) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `row_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log_archives`
--

INSERT INTO `activity_log_archives` (`id`, `ym`, `filename`, `row_count`, `created_at`) VALUES
(1, '2026-09', 'activity-log-2026-09.pdf', 74, '2026-10-01 08:23:26');

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
('daily_request_limit', '4', '2026-10-08 06:22:15'),
('max_units_per_request', '4', '2026-09-18 03:03:31'),
('projector_total_units', '4', '2026-10-08 00:54:50'),
('teachmint_daily_request_limit', '5', '2026-10-08 02:13:06'),
('teachmint_max_units_per_request', '2', '2026-10-08 02:13:06'),
('teachmint_total_units', '2', '2026-10-08 02:13:06');

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
-- Table structure for table `incident_reports`
--

CREATE TABLE `incident_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(150) NOT NULL,
  `department` varchar(150) NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `category` varchar(150) NOT NULL,
  `affected_systems` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `action_taken` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL,
  `done_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_recipients`
--

CREATE TABLE `mail_recipients` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notify_new_requests` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mail_recipients`
--

INSERT INTO `mail_recipients` (`id`, `email`, `name`, `is_active`, `created_at`, `notify_new_requests`) VALUES
(1, 'dref.diamante@perpetualdalta.edu.ph', 'Sir. Dref', 1, '2026-09-16 05:49:47', 1),
(3, 'johnzeus.sideno@perpetualdalta.edu.ph', 'Sir. Zeus', 1, '2026-09-16 07:47:12', 1),
(4, 'jamesmichaelromero09@gmail.com', 'Sir. James', 1, '2026-09-17 03:46:07', 1),
(5, 'markandriedelacruz25@gmail.com', 'Sir. Mark', 1, '2026-09-21 01:24:04', 1),
(6, 'jraymarkv2003@gmail.com', 'Sir. Raymark', 1, '2026-09-21 06:51:19', 1),
(8, 'kakarot8823@gmail.com', 'test1', 1, '2026-09-21 06:51:53', 1),
(9, 'okay09okay05@gmail.com', 'test2', 1, '2026-09-21 06:59:11', 1);

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
  `done_at` datetime DEFAULT NULL,
  `reminder_1day_sent` tinyint(1) NOT NULL DEFAULT 0,
  `done_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projector_requests`
--

INSERT INTO `projector_requests` (`id`, `date_requested`, `name`, `position`, `department`, `venue`, `purpose`, `date_needed`, `time_from`, `time_to`, `projector_units`, `equipment`, `remarks`, `additional_dates`, `created_at`, `reminder_1hr_sent`, `reminder_30min_sent`, `is_cancelled`, `cancelled_at`, `cancel_reason`, `event_type`, `participants`, `is_done`, `done_at`, `reminder_1day_sent`, `done_by`) VALUES
(20, '2026-09-18', 'Kim Leila Ulat', 'Staff', 'College Guidance', 'Gymnasium', 'Parent and child Activity', '2026-09-18', '07:00', '17:00', 2, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'need splittrer', '[]', '2026-09-18 01:58:16', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:09:29', 0, NULL),
(21, '2026-09-18', 'Clesarie P. Bornco', 'Faculty', 'Pharmacy', 'GYM', 'World Pharmacy Day 2026', '2026-09-23', '07:00', '17:00', 1, '[\"Extension Cord\",\"wifi connection\"]', '', '[]', '2026-09-18 02:08:30', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL, 0, NULL),
(22, '2026-09-09', 'Jhudiel Javier', 'Faculty', 'n/a', 'GYM', 'Civil Engineering Days', '2026-09-18', '13:00', '18:00', 2, '[\"Projector Screen\"]', 'Splitter', '[{\"date\":\"2026-09-19\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:14:47\"}]', '2026-09-18 02:08:57', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:11:12', 0, NULL),
(23, '2026-09-15', 'Christine M. Olaybal', 'Faculty', 'n/a', 'EMC', 'Ink & Impact : Empowering Future Educators in Campus Journalism and Digital Media', '2026-10-29', '09:00', '15:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 02:14:57', 0, 0, 0, NULL, NULL, 'Seminar', NULL, 0, NULL, 0, NULL),
(24, '2026-09-12', 'Ms. Rui Oasay', 'Staff', 'College of International Hospitality Management', 'EMC', 'Enhancement seminar B and P', '2026-10-10', '07:00', '12:00', 1, '[\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', 'ahead of the time dapat nasa EMC na for your mga needs', '[]', '2026-09-18 02:18:24', 1, 1, 0, NULL, NULL, 'Seminar', NULL, 0, NULL, 1, NULL),
(25, '2026-09-12', 'Rui Oasay', 'Faculty', 'college of international services', 'EMD', 'Enhancement Seminar - Ban and beverage', '2026-09-21', '11:00', '15:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 02:20:11', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL, 0, NULL),
(26, '2026-09-12', 'Ms. Rui Oasay', 'Staff', 'College of International Hospitality Management', 'EMC', 'Enhancement seminar B and P', '2026-10-10', '13:00', '17:00', 1, '[\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 02:20:40', 1, 1, 0, NULL, NULL, 'Seminar', NULL, 0, NULL, 1, NULL),
(27, '2026-09-04', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'GYM', 'PQA ORIENTATION FOR BUSINESS CLUSTER', '2026-09-15', '09:00', '12:00', 2, '[\"HDMI \\/ VGA Cable\",\"Laptop\"]', 'Splitter', '[]', '2026-09-18 02:22:48', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:07:22', 0, NULL),
(28, '2026-09-15', 'Kristel P. Mendoza', 'Staff', 'n/a', 'EMC', 'Lets Lixcel: Intensive Examination for Teachers Review and Test-Tasking Stratergies Seminaer', '2026-10-10', '09:00', '15:00', 1, '[]', '', '[]', '2026-09-18 02:26:13', 1, 1, 0, NULL, NULL, '', NULL, 0, NULL, 1, NULL),
(29, '2026-09-07', 'Noreen Mae Flores', 'Faculty', 'n/a', 'JBHS Quadrangle', 'PT Days 2026: Team Building', '2026-09-12', '08:00', '15:00', 1, '[\"Projector Screen\"]', '', '[]', '2026-09-18 02:27:13', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 17:18:35', 0, NULL),
(30, '2026-09-09', 'Christine M. Olaybal', 'Staff', 'n/a', 'EMC', 'CASED TEACHER\'S DAY CELEBRATION', '2026-10-06', '13:00', '17:00', 1, '[]', '', '[]', '2026-09-18 02:28:44', 1, 1, 0, NULL, NULL, '', NULL, 0, NULL, 1, NULL),
(31, '2026-07-27', 'Marianne R. Dela Cruz', 'Faculty', 'n/a', 'EMC', 'PQA Orientation', '2026-09-11', '08:00', '17:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\",\"Laptop\"]', '', '[]', '2026-09-18 02:29:08', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:22', 0, NULL),
(32, '2026-09-07', 'Noreen Mae Flores', 'Faculty', 'n/a', 'EMC', 'Enrichment Seminar', '2026-09-11', '10:00', '12:00', 1, '[\"Projector Screen\",\"Teachmint\"]', '', '[]', '2026-09-18 02:31:22', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:20', 0, NULL),
(33, '2026-08-28', 'Jade Marie M Gunio', 'Faculty', 'n/a', 'GYM', 'Living Rosary', '2026-10-26', '08:00', '12:00', 1, '[]', '', '[]', '2026-09-18 02:32:54', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL, 0, NULL),
(34, '2026-09-18', 'Karol Tolentino', 'Faculty', 'Basic Education Department', 'GYM', 'Teachers\' Day GS and JBHS', '2026-10-01', '08:00', '12:00', 1, '[]', '', '[]', '2026-09-18 02:39:53', 1, 1, 0, NULL, NULL, 'Event', NULL, 1, '2026-10-05 16:51:09', 1, 'John Raymark Visca'),
(35, '2026-09-01', 'Noreen Mae Flores', 'Faculty', 'n/a', 'Main Lobby Hallway', 'PT days 2026: Interactive Mini games', '2026-09-10', '11:00', '15:00', 1, '[\"Projector Screen\",\"Mobile TV\"]', '', '[]', '2026-09-18 02:42:05', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:18', 0, NULL),
(36, '2026-08-03', 'Anelee San Diego', 'Principal', 'Basic Education Principal\'s Office', 'GYM', 'ACQUAINTANCE(SHS)', '2026-09-25', '10:00', '19:00', 1, '[]', '', '[]', '2026-09-18 02:42:27', 0, 0, 0, NULL, NULL, '', NULL, 0, NULL, 0, NULL),
(37, '2026-09-07', 'Noreen Mae Flores', 'Faculty', 'n/a', 'EMC', 'PT Days 2026 : Inspirational Talks with COPT Alumni', '2026-09-10', '08:00', '10:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\",\"TV\"]', '', '[]', '2026-09-18 02:44:16', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:16', 0, NULL),
(38, '2026-08-20', 'Kim Leila P. Ulat', 'Staff', 'n/a', 'GYM', 'Parent and Child Activity', '2026-09-18', '07:30', '12:00', 1, '[]', '', '[]', '2026-09-18 02:45:48', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:10:22', 0, NULL),
(39, '2026-09-19', 'NOREA MAE GUTIERREZ', 'Staff', 'n/a', 'EMC', 'PQA ORIENTATION (w/ DR. MARJ', '2026-09-07', '07:00', '18:00', 1, '[]', '', '[{\"date\":\"2026-09-06\",\"time_from\":\"07:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:03:00\"},{\"date\":\"2026-09-09\",\"time_from\":\"07:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:03:09\"}]', '2026-09-18 02:49:48', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:57:25', 0, NULL),
(40, '2026-08-10', 'Alodia M. Edrinal', 'Faculty', 'n/a', 'Room to Room', 'Mental Health Guidance Sessions', '2026-09-08', '06:00', '18:00', 2, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'needed 4 projectors', '[{\"date\":\"2026-09-09\",\"time_from\":\"06:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:03:11\"}]', '2026-09-18 02:49:58', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:06', 0, NULL),
(41, '2026-08-24', 'Julie Fate L. Dalde', 'Faculty', 'n/a', 'Lobby', 'Bingo For a Cause', '2026-09-04', '14:00', '22:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'Zoom Setup', '[]', '2026-09-18 02:51:54', 0, 0, 0, NULL, NULL, 'Event', NULL, 1, '2026-09-21 15:57:18', 0, NULL),
(42, '2026-09-02', 'Kristel P. Mendoza', 'Staff', 'n/a', 'EMC', 'Empowering Perpetual Student Leaders For Transforming and Servant Leadership with EQ', '2026-09-07', '07:00', '18:00', 1, '[]', '', '[]', '2026-09-18 02:53:17', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:03:03', 0, NULL),
(43, '2026-07-27', 'D. Montaya', 'Faculty', 'n/a', 'EMC', 'PQA Orientation', '2026-09-04', '08:00', '17:00', 1, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\",\"Laptop\"]', 'Computer Set with Laptop', '[]', '2026-09-18 02:54:21', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:57:15', 0, NULL),
(44, '2026-09-02', 'Mr. RIC C. TANDIGAN', 'n/a', 'n/a', 'GYM', '7th ROTC TRAINING DAY', '2026-09-05', '07:00', '10:00', 1, '[]', '', '[]', '2026-09-18 02:56:36', 0, 0, 0, NULL, NULL, 'Training', NULL, 1, '2026-09-21 15:57:21', 0, NULL),
(45, '2026-08-13', 'Kris Jan Jay Aranilla', 'Faculty', 'n/a', 'EMC', 'ARCHITECTURE ORIENTATION 2026', '2026-09-03', '08:00', '18:00', 1, '[\"Projector Screen\"]', 'OHP visual presentation using acetate', '[]', '2026-09-18 02:59:27', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:56:59', 0, NULL),
(46, '2026-09-10', 'MS. Alodia M. Edrinal', 'n/a', 'n/a', 'Room to Room', 'Mental Health Guidance Session', '2026-09-15', '07:00', '20:00', 2, '[\"3 Projector Request\"]', '', '[{\"date\":\"2026-09-16\",\"time_from\":\"07:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:11:56\"}]', '2026-09-18 03:00:34', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:05:54', 0, NULL),
(47, '2026-09-18', 'Juliana Cabiling', 'SSC', 'n/a', 'EMC', 'Presidential Meeting', '2026-09-17', '13:00', '17:00', 1, '[]', '', '[]', '2026-09-18 03:02:49', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:08:10', 0, NULL),
(48, '2026-09-19', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'GYM', 'PQA Orientation', '2026-09-01', '08:00', '18:00', 2, '[\"Projector Screen\",\"HDMI \\/ VGA Cable\"]', 'splitter', '[{\"date\":\"2026-09-02\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-22 03:39:18\"},{\"date\":\"2026-09-04\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:21:48\"},{\"date\":\"2026-09-14\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:05:48\"},{\"date\":\"2026-09-03\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-21 10:27:49\"}]', '2026-09-18 03:03:08', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 15:42:34', 0, NULL),
(49, '2026-09-15', 'Ms. Rui Oasay', 'n/a', 'College of International Hospitality Management', 'EMC', 'Enhancement Seminar (Prof Dev)', '2026-09-17', '13:00', '15:00', 1, '[\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-18 03:05:15', 0, 0, 0, NULL, NULL, '', NULL, 1, '2026-09-21 16:08:29', 0, NULL),
(65, '2026-08-05', 'Kristel P. Mendoza', 'Heads / Dean', 'n/a', 'EMC', 'ENHANCEMENT PROGRAM (AB COMM)', '2026-08-29', '08:00', '17:00', 1, '[]', '', '[]', '2026-09-21 09:34:46', 0, 0, 0, NULL, NULL, 'Seminar', 40, 1, '2026-09-21 17:35:14', 0, NULL),
(66, '2026-07-27', 'MARIANNE R. DELA CRUZ', 'Heads / Dean', 'n/a', 'EMC', 'PQA Orientation', '2026-08-28', '08:00', '17:00', 1, '[]', '', '[]', '2026-09-21 09:37:41', 0, 0, 0, NULL, NULL, 'Orientation', 40, 1, '2026-09-22 08:49:44', 0, NULL),
(67, '2026-08-14', 'MR. SALVADOR BALAGOT', 'Heads / Dean', 'n/a', 'AMPLI', 'PEER TRAINING', '2026-08-28', '08:00', '17:00', 1, '[]', 'AMPL', '[]', '2026-09-21 09:42:06', 0, 0, 0, NULL, NULL, 'Training', 60, 1, '2026-09-22 08:52:03', 0, NULL),
(68, '2026-07-17', 'Anelee San Dieogo', 'Heads / Dean', 'n/a', 'GYM', 'Buwan ng Wika 2026 Celebration (SHS)', '2026-08-27', '12:00', '16:00', 1, '[]', '', '[]', '2026-09-21 09:47:41', 0, 0, 0, NULL, NULL, 'Event', 100, 1, '2026-09-22 08:51:58', 0, NULL),
(69, '2026-08-24', 'Anelee San Dieogo', 'Heads / Dean', 'n/a', 'EMC', 'INDUCTION OF OFFICERS', '2026-08-26', '13:00', '17:00', 1, '[]', '', '[]', '2026-09-21 09:50:53', 0, 0, 0, NULL, NULL, 'INDUCTION OF OFFICERS', 50, 1, '2026-09-22 08:51:11', 0, NULL),
(70, '2026-08-04', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'EMC', 'PQA Orientation', '2026-08-18', '08:00', '18:00', 1, '[]', '', '[{\"date\":\"2026-08-19\",\"time_from\":\"08:00\",\"time_to\":\"18:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:32\"}]', '2026-09-21 23:47:24', 0, 0, 0, NULL, NULL, 'Orientation', 100, 1, '2026-09-22 08:49:30', 0, NULL),
(71, '2026-07-17', 'Anelee San Diego', 'Heads / Dean', 'n/a', 'GYM', 'Buwan ng Wika 2026 Celebration (SHS)', '2026-08-20', '12:00', '16:00', 1, '[]', '', '[]', '2026-09-21 23:59:34', 0, 0, 0, NULL, NULL, 'Event', 100, 1, '2026-09-22 08:51:05', 0, NULL),
(72, '2026-09-22', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'GYM', 'PQA Orientation ( Students)', '2026-08-24', '08:00', '17:00', 1, '[\"Projector Screen\"]', '', '[{\"date\":\"2026-08-25\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:36\"},{\"date\":\"2026-08-26\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:38\"},{\"date\":\"2026-08-27\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"done\":true,\"done_at\":\"2026-09-22 02:49:41\"}]', '2026-09-22 00:11:31', 0, 0, 0, NULL, NULL, 'Orientation', 150, 1, '2026-09-22 08:49:35', 0, NULL),
(73, '2026-09-21', 'Eva Melissa V. Pampay', 'Staff', 'n/a', 'Amphitheater', 'College of Criminology', '2026-09-25', '09:00', '11:00', 1, '[\"Projector Screen\"]', '', '[]', '2026-09-22 00:37:51', 0, 0, 0, NULL, NULL, 'Seminar', 50, 0, NULL, 0, NULL),
(74, '2026-09-21', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'EMC', 'Meeting of  Acads', '2026-10-12', '08:00', '17:00', 0, '[\"teachmint, whiteboard\"]', 'n/a', '[{\"date\":\"2026-10-13\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"reminder_1day_sent\":1,\"reminder_1hr_sent\":1,\"reminder_30min_sent\":1}]', '2026-09-22 00:50:07', 1, 1, 0, NULL, NULL, 'Meeting', 30, 0, NULL, 1, NULL),
(75, '2026-09-21', 'Mary jane  Sario', 'Staff', 'n/a', 'gym', 'GS Sportfest 2026', '2026-12-07', '08:00', '15:00', 2, '[\"splitter\"]', '', '[{\"date\":\"2026-12-09\",\"time_from\":\"08:00\",\"time_to\":\"15:00\"},{\"date\":\"2026-12-10\",\"time_from\":\"08:00\",\"time_to\":\"15:00\"}]', '2026-09-22 02:01:43', 0, 0, 1, '2026-10-14 09:35:18', 'Duplicate entry', 'Event', 300, 0, NULL, 0, NULL),
(76, '2026-10-05', 'ALEXIE Basileyo', 'Faculty', 'Psychology', 'EMC', 'General Assembly Of Psychology Society', '2026-10-05', '12:00', '13:00', 1, '[\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-22 08:31:00', 0, 1, 0, NULL, NULL, 'Event', 110, 0, NULL, 1, NULL),
(77, '2026-09-18', 'Francisco B. Valdespinosa Jr', 'Staff', 'n/a', 'Ampitheater', 'Synpdality and perpertualite Leaders as Helpers of God', '2026-09-21', '08:00', '16:00', 1, '[]', '', '[]', '2026-09-22 08:33:43', 0, 0, 0, NULL, NULL, 'Seminar', 100, 0, NULL, 0, NULL),
(78, '2026-09-22', 'MARIA CRYSTAL OROZCO', 'Faculty', 'College of Computer Studies', 'EMC', 'CCS DAYS', '2026-10-21', '07:00', '17:00', 1, '[\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-09-22 08:36:27', 0, 0, 0, NULL, NULL, 'Event', 100, 0, NULL, 0, NULL),
(79, '2026-09-22', 'Alodia M. Edrinal', 'Staff', 'n/a', 'Ampli', 'Peer', '2026-08-28', '08:00', '17:00', 1, '[]', '', '[]', '2026-09-22 08:39:22', 0, 0, 0, NULL, NULL, 'Training', 60, 0, NULL, 0, NULL),
(80, '2026-09-22', 'NERESSA TRASMONTE', 'Faculty', 'Grade School Faculty', 'EMC', 'CROSS-CULTURAL (JAPAN-PHILIPPINES) - GRADE 6 ONLY', '2026-12-10', '08:00', '12:00', 0, '[\"TEACHMINT\"]', '', '[]', '2026-09-22 08:41:54', 0, 0, 0, NULL, NULL, 'Seminar', 70, 0, NULL, 0, NULL),
(83, '2026-09-29', 'Ms. Coloma', 'Heads / Dean', 'Medtech', 'GYM', 'CELEBRATION OF MEDTECH WEEK', '2026-10-13', '08:00', '20:00', 1, '[]', '', '[{\"date\":\"2026-10-14\",\"time_from\":\"08:00\",\"time_to\":\"20:00\",\"reminder_1day_sent\":1,\"reminder_1hr_sent\":1,\"reminder_30min_sent\":1},{\"date\":\"2026-10-16\",\"time_from\":\"08:00\",\"time_to\":\"20:00\",\"reminder_1day_sent\":1}]', '2026-09-29 02:35:35', 1, 1, 0, NULL, NULL, 'Event', 180, 0, NULL, 1, NULL),
(84, '2026-09-21', 'NOREA MAE GUTIERREZ', 'Staff', 'n/a', 'EMC', 'CCHEIA GENERAL ASSEMBLY', '2026-10-07', '11:00', '18:00', 1, '[]', '', '[]', '2026-09-29 02:43:32', 1, 1, 0, NULL, NULL, 'Teachmint', 50, 0, NULL, 1, NULL),
(86, '2026-09-22', 'Mary Jane Sario', 'Faculty', 'n/a', 'GYM', 'Grade School Sportsfest 2026', '2026-11-09', '08:00', '15:00', 2, '[\"Projector Screen\",\"Extension Cord\",\"HDMI \\/ VGA Cable\",\"splitter\"]', '', '[{\"date\":\"2026-11-10\",\"time_from\":\"08:00\",\"time_to\":\"15:00\"},{\"date\":\"2026-11-11\",\"time_from\":\"08:00\",\"time_to\":\"15:00\"}]', '2026-09-29 02:48:25', 0, 0, 0, NULL, NULL, 'Event', 300, 0, NULL, 0, NULL),
(87, '2026-09-09', 'Alex C. Canubas', 'Faculty', 'n/a', 'GYM', 'Junior\'s Week - Junior business high school Sports fest', '2026-09-28', '07:00', '19:00', 2, '[\"Projector Screen\"]', '', '[{\"date\":\"2026-09-29\",\"time_from\":\"07:00\",\"time_to\":\"19:00\"},{\"date\":\"2026-09-30\",\"time_from\":\"07:00\",\"time_to\":\"19:00\",\"reminder_1hr_sent\":1,\"reminder_30min_sent\":1}]', '2026-09-29 02:57:30', 0, 0, 0, NULL, NULL, 'Event', 450, 0, NULL, 0, NULL),
(88, '2026-09-29', 'Ak. JOHN LARRY OSACDIN', 'Faculty', 'College of Architecture', 'EMC', 'CELEBRATING THE WORLD ARCHITECTURE DAY THORUHG LEARNING OF ARCHITECTURE STUDENT', '2026-10-15', '07:00', '18:00', 1, '[]', '', '[]', '2026-09-29 07:53:21', 1, 1, 0, NULL, NULL, 'Event', 150, 0, NULL, 1, NULL),
(89, '2026-09-29', 'CYRIC JOYCE O. ROMERO', 'Staff', 'n/a', 'EMC', 'RECOLLECTION 2026', '2026-10-26', '08:00', '15:00', 1, '[\"HDMI \\/ VGA Cable\"]', 'Before 7:00 daw i-setup', '[]', '2026-09-29 08:03:28', 0, 0, 0, NULL, NULL, 'Event', 350, 0, NULL, 0, NULL),
(90, '2026-09-29', 'CYRIC JOYCE O. ROMERO', 'Staff', 'n/a', 'EMC', 'RECOLLECTION 2026', '2026-10-27', '08:00', '15:00', 1, '[\"HDMI \\/ VGA Cable\"]', 'BEFORE 7AM DAW ISETUP', '[{\"date\":\"2026-10-28\",\"time_from\":\"08:00\",\"time_to\":\"15:00\"}]', '2026-09-29 08:49:14', 0, 0, 0, NULL, NULL, 'Event', 350, 0, NULL, 0, NULL),
(91, '2026-09-28', 'Neressa M. Pasmonte', 'Faculty', 'n/a', 'Gymnasium', 'GS and JBHS FAMILY DAY CELEBRATION', '2026-12-18', '07:00', '17:00', 2, '[\"Projector Screen\",\"Extension Cord\",\"HDMI \\/ VGA Cable\",\"Splitter\"]', '', '[]', '2026-09-30 08:00:32', 0, 0, 0, NULL, NULL, 'Event', 600, 0, NULL, 0, NULL),
(92, '2026-09-29', 'Dr. M. S. Banaticla', 'Faculty', 'n/a', 'Room 416 (Amphitheater)', 'Enrichment Program', '2026-10-07', '12:00', '17:00', 1, '[\"Projector Screen\"]', '', '[]', '2026-10-02 06:03:40', 1, 1, 0, NULL, NULL, 'Event', 150, 0, NULL, 1, NULL),
(93, '2026-10-02', 'Marvin Rocamora', 'Faculty', 'n/a', 'Gymnasium', 'Asean Activity', '2026-10-15', '07:00', '15:00', 2, '[\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-10-02 06:40:28', 1, 1, 0, NULL, NULL, 'Event', 900, 0, NULL, 1, NULL),
(94, '2026-10-02', 'Judy May Magallon', 'Staff', 'Library', 'CLAB3', 'Mcgrawhill Trial Access', '2026-10-07', '10:30', '18:00', 0, '[]', 'Room request CLAB3. 10:30 onwards', '[]', '2026-10-05 01:44:11', 1, 1, 0, NULL, NULL, 'Seminar', 30, 0, NULL, 1, NULL),
(95, '2026-10-02', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'EMC', 'Acad Meeting & other Matters ( w/ Dr. Marj', '2026-10-13', '08:00', '17:00', 1, '[\"Projector Screen\",\"Teachmint\"]', '', '[{\"date\":\"2026-10-14\",\"time_from\":\"08:00\",\"time_to\":\"17:00\",\"reminder_1day_sent\":1,\"reminder_1hr_sent\":1,\"reminder_30min_sent\":1}]', '2026-10-06 00:59:57', 1, 1, 0, NULL, NULL, 'Meeting', 30, 0, NULL, 1, NULL),
(96, '2026-09-28', 'Jaydee Querubin', 'Faculty', 'n/a', 'EMC', 'CCHEIA  General Assembly & Election', '2026-10-19', '13:00', '18:00', 0, '[\"Teachmint\"]', '', '[]', '2026-10-06 02:25:34', 0, 0, 0, NULL, NULL, 'Event', 50, 0, NULL, 0, NULL),
(97, '2026-10-06', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'EMC', 'MANCOM Meeting', '2026-10-07', '09:00', '12:00', 0, '[\"Teachmint\"]', '', '[]', '2026-10-06 02:33:00', 1, 1, 0, NULL, NULL, 'Meeting', 30, 0, NULL, 0, NULL),
(98, '2026-09-30', 'Ms. Alodia  M Endrinal', 'Staff', 'n/a', 'gym', 'Career Guidance', '2026-10-21', '08:00', '17:00', 2, '[\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-10-06 06:10:43', 0, 0, 0, NULL, NULL, 'Event', 200, 0, NULL, 0, NULL),
(99, '2026-10-02', 'Michael Rivera', 'Faculty', 'n/a', '416 - Amphitheater', 'Enrichment Program', '2026-10-08', '08:00', '16:00', 1, '[]', '', '[]', '2026-10-06 07:43:05', 1, 1, 0, NULL, NULL, 'Event', 130, 0, NULL, 1, NULL),
(100, '2026-10-07', 'Ric C. Tandingan', 'Staff', 'n/a', 'gym', 'BASIC ROTC LECTURE', '2026-10-10', '07:00', '10:00', 2, '[]', '', '[]', '2026-10-07 02:19:41', 1, 1, 0, NULL, NULL, 'Training', 650, 0, NULL, 1, NULL),
(101, '2026-09-26', 'Alexis Basileyo', 'n/a', 'n/a', 'gym', 'Sangguniang Kabataan', '2026-10-24', '13:00', '19:00', 1, '[]', '', '[]', '2026-10-07 03:15:20', 0, 0, 0, NULL, NULL, 'SK event', 25, 0, NULL, 0, NULL),
(102, '2026-10-05', 'Ms. Coloma', 'Program Chair', 'n/a', 'EMC', 'Emergency Meeting for All Medtech Students', '2026-10-08', '09:00', '12:00', 1, '[]', '', '[]', '2026-10-07 09:24:56', 1, 1, 0, NULL, NULL, 'Meeting', 50, 0, NULL, 0, NULL),
(103, '2026-10-07', 'Mr. Ricardo E. Tandigan', 'Faculty', 'n/a', 'Room 204', 'Conference Meeting', '2026-10-09', '06:00', '18:00', 1, '[]', '', '[]', '2026-10-08 05:08:31', 1, 1, 0, NULL, NULL, 'Meeting', 55, 0, NULL, 0, NULL),
(104, '2026-09-10', 'na', 'n/a', 'n/a', 'Gymnasium', 'ME One Day League', '2026-10-09', '07:00', '20:00', 2, '[\"Projector Screen\",\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-10-08 06:22:20', 1, 1, 0, NULL, NULL, 'Event', 150, 0, NULL, 0, NULL),
(108, '2026-08-28', 'Sophia Gracia Nronne G. Erni', 'Faculty', 'n/a', 'Gym', 'Investiture and Overnight Camping', '2026-11-05', '08:00', '22:00', 2, '[\"Splitter\"]', '', '[{\"date\":\"2026-11-06\",\"time_from\":\"06:00\",\"time_to\":\"10:00\"}]', '2026-10-09 00:09:26', 0, 0, 1, '2026-10-13 10:18:10', 'Cancel Change Date', 'Event', 286, 0, NULL, 0, NULL),
(109, '2026-09-23', 'n.a', 'n/a', 'College of Engineering', 'GYM', 'ENGINEERING DAYS', '2026-10-19', '07:00', '20:30', 2, '[\"Projector Screen\",\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[{\"date\":\"2026-10-20\",\"time_from\":\"07:00\",\"time_to\":\"20:30\"}]', '2026-10-12 04:40:54', 0, 0, 0, NULL, NULL, 'Event', 300, 0, NULL, 0, NULL),
(110, '2026-10-07', 'Mariene Portal', 'Staff', 'n/a', 'GYM', 'TOEIC ORIENTATION', '2026-12-02', '08:00', '17:00', 1, '[\"Projector Screen\"]', '', '[]', '2026-10-14 05:26:52', 0, 0, 0, NULL, NULL, 'Orientation', 200, 0, NULL, 0, NULL),
(112, '2026-10-14', 'Melanie Banaticla', 'Faculty', 'n/a', 'Room 415', 'Case Presentation', '2026-10-19', '11:00', '21:00', 1, '[\"Projector Screen\"]', '', '[{\"date\":\"2026-10-20\",\"time_from\":\"11:00\",\"time_to\":\"21:00\"}]', '2026-10-15 03:09:16', 0, 0, 0, NULL, NULL, 'Presentation', 38, 0, NULL, 0, NULL),
(113, '2026-10-15', 'Arvin Bryce S. Zureta', 'Student', 'n/a', 'SHS Building Room 438', 'Case Presentation', '2026-10-22', '08:00', '17:00', 1, '[\"Projector Screen\"]', '', '[]', '2026-10-15 05:06:55', 0, 0, 0, NULL, NULL, 'Presentation', 50, 0, NULL, 0, NULL),
(114, '2026-10-13', 'Cristina', 'Faculty', 'CON', 'EMC', 'A Taste of the World: Global Food Festival', '2026-11-03', '07:00', '16:00', 1, '[\"Projector Screen\",\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-10-15 06:21:58', 0, 0, 0, NULL, NULL, 'Presentation', 50, 0, NULL, 0, NULL),
(115, '2026-10-15', 'Corrine Lechuga', 'Faculty', 'n/a', 'EMC', 'MOU Signing (Synergy Conference 2026)', '2026-10-23', '12:00', '18:00', 1, '[\"Projector Screen\",\"Extension Cord\",\"HDMI \\/ VGA Cable\"]', '', '[]', '2026-10-15 07:37:15', 0, 0, 0, NULL, NULL, 'Conference', 1, 0, NULL, 0, NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `teachmint_requests`
--

CREATE TABLE `teachmint_requests` (
  `id` int(11) NOT NULL,
  `date_requested` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `venue` varchar(150) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL DEFAULT '',
  `participants` int(10) UNSIGNED DEFAULT NULL,
  `date_needed` date NOT NULL,
  `time_from` varchar(30) NOT NULL,
  `time_to` varchar(30) NOT NULL,
  `teachmint_units` int(11) NOT NULL DEFAULT 1,
  `remarks` text DEFAULT NULL,
  `additional_dates` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reminder_1day_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_1hr_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_30min_sent` tinyint(1) NOT NULL DEFAULT 0,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL,
  `done_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachmint_requests`
--

INSERT INTO `teachmint_requests` (`id`, `date_requested`, `name`, `position`, `department`, `venue`, `purpose`, `event_type`, `participants`, `date_needed`, `time_from`, `time_to`, `teachmint_units`, `remarks`, `additional_dates`, `created_at`, `reminder_1day_sent`, `reminder_1hr_sent`, `reminder_30min_sent`, `is_cancelled`, `cancelled_at`, `cancel_reason`, `is_done`, `done_at`, `done_by`) VALUES
(6, '2026-10-13', 'Norea Mae Gutierrez', 'Faculty', 'n/a', 'EMC', 'ERT/ FIRE BRIGADE TRAINING', 'Training', 50, '2026-10-16', '08:00', '17:00', 1, '', '[]', '2026-10-14 01:32:44', 1, 0, 0, 0, NULL, NULL, 0, NULL, NULL);

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
-- Indexes for table `activity_log_archives`
--
ALTER TABLE `activity_log_archives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ym` (`ym`);

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
-- Indexes for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_date` (`incident_date`);

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
-- Indexes for table `teachmint_requests`
--
ALTER TABLE `teachmint_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tm_date_needed` (`date_needed`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT for table `activity_log_archives`
--
ALTER TABLE `activity_log_archives`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dropdown_option`
--
ALTER TABLE `dropdown_option`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `incident_reports`
--
ALTER TABLE `incident_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `projector_requests`
--
ALTER TABLE `projector_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

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
-- AUTO_INCREMENT for table `teachmint_requests`
--
ALTER TABLE `teachmint_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
