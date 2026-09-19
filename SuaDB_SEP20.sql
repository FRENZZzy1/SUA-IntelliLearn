-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 19, 2026 at 05:13 PM
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
-- Database: `lms`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `access_level` enum('full','limited','read_only') NOT NULL DEFAULT 'limited',
  `position` enum('principal','registrar','it_administrator') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `user_id`, `email`, `access_level`, `position`, `created_at`) VALUES
(8, 478, 'jong@gmail.com', 'full', 'it_administrator', '2026-09-18 13:58:23'),
(9, 474, 'frenzpogi@gmail.com', 'full', 'it_administrator', '2026-09-18 15:42:23'),
(10, 479, 'zitherpaller7@gmail.com', 'limited', 'registrar', '2026-09-18 15:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `admin_permissions`
--

CREATE TABLE `admin_permissions` (
  `permission_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `permission` enum('read','write') NOT NULL DEFAULT 'read',
  `can_approve_enrollment` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_permissions`
--

INSERT INTO `admin_permissions` (`permission_id`, `user_id`, `module_key`, `permission`, `can_approve_enrollment`, `created_at`, `updated_at`) VALUES
(5, 479, 'dashboard', 'read', 0, '2026-09-18 15:50:46', '2026-09-18 15:50:46'),
(6, 479, 'courses', 'read', 0, '2026-09-18 15:50:46', '2026-09-18 15:50:46'),
(7, 479, 'enrollment', 'write', 0, '2026-09-18 15:50:46', '2026-09-18 15:50:46'),
(8, 479, 'announcements', 'read', 0, '2026-09-18 15:50:46', '2026-09-18 15:50:46'),
(9, 479, 'settings', 'read', 0, '2026-09-18 15:50:46', '2026-09-18 15:50:46');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `posted_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `audience` enum('all','teachers','students') NOT NULL DEFAULT 'all',
  `priority` enum('normal','important','urgent') NOT NULL DEFAULT 'normal',
  `offering_id` int(11) DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('Activity','Exam') NOT NULL DEFAULT 'Activity',
  `description` text DEFAULT NULL,
  `instructions_file_path` varchar(500) DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `points` decimal(5,2) NOT NULL DEFAULT 100.00,
  `max_attempts` int(11) NOT NULL DEFAULT 1,
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'published',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late','Excused') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `at_risk_insights`
--

CREATE TABLE `at_risk_insights` (
  `insight_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `risk_label` varchar(20) NOT NULL,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `why` text DEFAULT NULL,
  `how` text DEFAULT NULL,
  `recommended_actions` text DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lang` varchar(5) NOT NULL DEFAULT 'en',
  `key_observations` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classofferings`
--

CREATE TABLE `classofferings` (
  `offering_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `quarter` enum('TRM 1','TRM 2','TRM 3') NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `schedule_days` varchar(20) DEFAULT NULL COMMENT 'Free-form day pattern, e.g. "M - W", "MWF", "TTh"',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 50,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classofferings`
--

INSERT INTO `classofferings` (`offering_id`, `subject_id`, `teacher_id`, `section_id`, `quarter`, `school_year_id`, `schedule_days`, `start_time`, `end_time`, `capacity`, `status`, `created_at`) VALUES
(51, 25, 17, 21, 'TRM 1', 2, 'M - W', NULL, NULL, 50, 'active', '2026-09-06 10:13:32'),
(52, 25, 17, 21, 'TRM 2', 2, 'M - W', NULL, NULL, 50, 'active', '2026-09-08 13:13:45'),
(53, 25, 17, 21, 'TRM 3', 2, 'M - W', NULL, NULL, 50, 'active', '2026-09-18 15:53:43');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `status` enum('active','dropped','completed') NOT NULL DEFAULT 'active',
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `offering_id`, `status`, `enrolled_at`) VALUES
(155, 450, 51, 'active', '2026-09-06 10:13:51'),
(156, 450, 52, 'active', '2026-09-08 13:13:45'),
(157, 450, 53, 'active', '2026-09-18 15:53:43'),
(158, 451, 53, 'active', '2026-09-18 15:54:19'),
(159, 452, 53, 'active', '2026-09-19 14:38:06'),
(160, 453, 53, 'active', '2026-09-19 14:38:29'),
(161, 453, 51, 'active', '2026-09-19 14:57:33'),
(162, 454, 53, 'active', '2026-09-19 15:04:11'),
(163, 454, 51, 'active', '2026-09-19 15:06:30');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_requests`
--

CREATE TABLE `enrollment_requests` (
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `grade_level` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `strand` varchar(50) DEFAULT NULL,
  `offering_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `notes` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `decided_at` timestamp NULL DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_requests`
--

INSERT INTO `enrollment_requests` (`request_id`, `student_id`, `grade_level`, `subject_id`, `strand`, `offering_id`, `status`, `notes`, `submitted_at`, `decided_at`, `decided_by`) VALUES
(173, 450, 7, 25, NULL, 51, 'approved', NULL, '2026-09-06 10:13:50', '2026-09-06 10:13:51', 474),
(174, 451, 7, 25, NULL, 53, 'approved', NULL, '2026-09-18 15:54:19', '2026-09-18 15:54:19', 479),
(175, 452, 7, 25, NULL, 53, 'approved', NULL, '2026-09-19 14:38:06', '2026-09-19 14:38:06', 474),
(176, 453, 7, 25, NULL, 53, 'approved', NULL, '2026-09-19 14:38:29', '2026-09-19 14:38:29', 474),
(177, 454, 7, 25, NULL, 53, 'approved', NULL, '2026-09-19 15:04:11', '2026-09-19 15:04:11', 474);

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `grade_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `quarter` enum('Prelim','Midterm','Prefinal','Final') NOT NULL,
  `grade` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `graded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learning_materials`
--

CREATE TABLE `learning_materials` (
  `material_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('pdf','video','link','slides','other') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learning_materials`
--

INSERT INTO `learning_materials` (`material_id`, `offering_id`, `title`, `type`, `file_path`, `external_url`, `file_size`, `uploaded_by`, `created_at`) VALUES
(6, 51, 'Module 1', 'pdf', 'assets/uploads/materials/51/01_Handout_1_45___3__5b92323a733a.pdf', NULL, 421594, 17, '2026-09-06 10:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `source_material_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `generation_source` enum('manual','pdf','topic') NOT NULL DEFAULT 'manual',
  `time_limit_minutes` int(11) DEFAULT NULL,
  `max_attempts` int(11) NOT NULL DEFAULT 1,
  `shuffle_questions` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'draft',
  `available_from` datetime DEFAULT NULL,
  `available_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `answer_id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_choice_id` int(11) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_awarded` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `attempt_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `status` enum('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
  `score` decimal(6,2) DEFAULT NULL,
  `max_score` decimal(6,2) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_choices`
--

CREATE TABLE `quiz_choices` (
  `choice_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `choice_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `order_index` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_generation_jobs`
--

CREATE TABLE `quiz_generation_jobs` (
  `job_id` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `offering_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `source_type` enum('pdf','topic') NOT NULL,
  `source_material_id` int(11) DEFAULT NULL,
  `topic_prompt` text DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_generation_jobs`
--

INSERT INTO `quiz_generation_jobs` (`job_id`, `quiz_id`, `offering_id`, `requested_by`, `source_type`, `source_material_id`, `topic_prompt`, `status`, `error_message`, `created_at`, `completed_at`) VALUES
(27, NULL, 51, 476, 'topic', NULL, 'human body parts', 'completed', NULL, '2026-09-06 10:17:37', '2026-09-06 10:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `question_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('mcq','true_false','short_answer') NOT NULL DEFAULT 'mcq',
  `points` decimal(5,2) NOT NULL DEFAULT 1.00,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `ai_generated` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_edited` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schoolyears`
--

CREATE TABLE `schoolyears` (
  `school_year_id` int(11) NOT NULL,
  `label` varchar(9) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schoolyears`
--

INSERT INTO `schoolyears` (`school_year_id`, `label`, `start_date`, `end_date`, `is_current`) VALUES
(2, '2026', '2026-09-06', '2027-09-19', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `grade_level` int(11) NOT NULL,
  `strand` varchar(50) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `school_year_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `section_name`, `grade_level`, `strand`, `adviser_id`, `school_year_id`, `created_at`) VALUES
(21, 'Grade7-A', 7, NULL, 17, 2, '2026-09-06 10:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_lrn` bigint(20) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `middlename` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `birthdate` date NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `guardian_name` varchar(255) DEFAULT NULL,
  `guardian_contact` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Gender` enum('Male','Female') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `student_lrn`, `firstname`, `lastname`, `middlename`, `email`, `birthdate`, `address`, `guardian_name`, `guardian_contact`, `created_at`, `updated_at`, `Gender`) VALUES
(450, 477, 111111111111, 'Zither', 'Paller', 'Empimo', 'zither@gmail.com', '2006-12-30', '', '', '', '2026-09-06 10:10:07', '2026-09-06 10:10:07', 'Male'),
(451, 480, 254677877898, 'sdasda', 'dasdasd', 'asdasdasd', 'dasd@gmail.com', '2026-09-15', '', '', '', '2026-09-18 15:51:33', '2026-09-18 15:51:33', 'Female'),
(452, 481, 343546342313, 'Juan', 'Paler', 'Del', 'dasdads@gmail.com', '2026-09-26', NULL, NULL, NULL, '2026-09-19 14:37:06', '2026-09-19 14:45:18', 'Male'),
(453, 482, 232335676767, 'lopez', 'cruz', NULL, 'dasd@gmail.com', '2026-09-08', 'sadasd', NULL, NULL, '2026-09-19 14:37:45', '2026-09-19 14:48:39', 'Male'),
(454, 483, 423667887666, 'John', 'Cantero', 'Carmen', 'sdads@gmail.com', '2007-02-14', 'dasdas', '', '', '2026-09-19 15:00:43', '2026-09-19 15:00:43', 'Male');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `description`, `created_at`) VALUES
(25, 'Science', NULL, '2026-09-06 10:08:08');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `submission_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `submission_text` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('submitted','late','graded','returned','missing') NOT NULL DEFAULT 'submitted',
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submission_files`
--

CREATE TABLE `submission_files` (
  `file_id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`, `updated_by`) VALUES
('auto_approve_enrollment', '0', '2026-09-19 15:04:20', 474),
('default_class_capacity', '30', '2026-09-18 14:19:44', 474),
('enrollment_open', '1', '2026-09-18 14:19:35', 474),
('school_name', 'St. Uriel Academy', '2026-09-06 10:13:18', 474),
('term_intervals', '{\"TRM 1\":{\"mode\":\"date\",\"start_month\":null,\"end_month\":null,\"start_date\":\"2026-09-06\",\"end_date\":\"2026-09-07\"},\"TRM 2\":{\"mode\":\"date\",\"start_month\":null,\"end_month\":null,\"start_date\":\"2026-09-08\",\"end_date\":\"2026-09-09\"},\"TRM 3\":{\"mode\":\"date\",\"start_month\":null,\"end_month\":null,\"start_date\":\"2026-09-10\",\"end_date\":\"2026-09-30\"}}', '2026-09-18 15:53:43', 474);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `middlename` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `firstname`, `lastname`, `middlename`, `email`, `employment_status`, `department`, `specialization`, `created_at`, `updated_at`) VALUES
(17, 476, 'Frenz', 'Paller', 'Empimo', 'frenzypaller@gmail.com', 'full-time', 'High School', NULL, '2026-09-06 10:09:06', '2026-09-06 10:09:16');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_evaluation_answers`
--

CREATE TABLE `teacher_evaluation_answers` (
  `answer_id` int(11) NOT NULL,
  `response_id` int(11) NOT NULL,
  `question_key` varchar(40) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL
) ;

--
-- Dumping data for table `teacher_evaluation_answers`
--

INSERT INTO `teacher_evaluation_answers` (`answer_id`, `response_id`, `question_key`, `rating`) VALUES
(1, 1, 'clarity_explains', 5),
(2, 1, 'clarity_knowledge', 5),
(3, 1, 'clarity_examples', 5),
(4, 1, 'environment_respect', 5),
(5, 1, 'environment_order', 5),
(6, 1, 'environment_punctual', 5),
(7, 1, 'support_participation', 5),
(8, 1, 'support_approachable', 5),
(9, 1, 'support_care', 5),
(10, 1, 'assessment_aligned', 5),
(11, 1, 'assessment_feedback', 5),
(12, 1, 'assessment_grading', 5),
(13, 2, 'clarity_explains', 3),
(14, 2, 'clarity_knowledge', 3),
(15, 2, 'clarity_examples', 2),
(16, 2, 'environment_respect', 3),
(17, 2, 'environment_order', 2),
(18, 2, 'environment_punctual', 3),
(19, 2, 'support_participation', 3),
(20, 2, 'support_approachable', 2),
(21, 2, 'support_care', 2),
(22, 2, 'assessment_aligned', 3),
(23, 2, 'assessment_feedback', 1),
(24, 2, 'assessment_grading', 4),
(25, 3, 'clarity_explains', 4),
(26, 3, 'clarity_knowledge', 4),
(27, 3, 'clarity_examples', 4),
(28, 3, 'environment_respect', 4),
(29, 3, 'environment_order', 4),
(30, 3, 'environment_punctual', 4),
(31, 3, 'support_participation', 4),
(32, 3, 'support_approachable', 4),
(33, 3, 'support_care', 4),
(34, 3, 'assessment_aligned', 4),
(35, 3, 'assessment_feedback', 4),
(36, 3, 'assessment_grading', 4);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_evaluation_responses`
--

CREATE TABLE `teacher_evaluation_responses` (
  `response_id` int(11) NOT NULL,
  `round_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `strengths` text DEFAULT NULL,
  `improvements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_evaluation_responses`
--

INSERT INTO `teacher_evaluation_responses` (`response_id`, `round_id`, `offering_id`, `teacher_id`, `strengths`, `improvements`) VALUES
(1, 1, 51, 17, 'I love the way he teach he is very clear', 'He often mock us for not knowing things'),
(2, 1, 51, 17, 'I like his humor', 'he often forgot the activities sumbissions'),
(3, 1, 51, 17, 'Galing po nya magturooo!', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_evaluation_rounds`
--

CREATE TABLE `teacher_evaluation_rounds` (
  `round_id` int(11) NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `term` enum('TRM 1','TRM 2','TRM 3') NOT NULL,
  `title` varchar(150) NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `closes_on` date DEFAULT NULL,
  `opened_by` int(11) DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_evaluation_rounds`
--

INSERT INTO `teacher_evaluation_rounds` (`round_id`, `school_year_id`, `term`, `title`, `status`, `closes_on`, `opened_by`, `opened_at`, `closed_at`) VALUES
(1, 2, 'TRM 1', 'Teacher Evaluation - 2026 TRM 1', 'closed', '2026-09-22', 474, '2026-09-19 14:25:31', '2026-09-19 15:12:37');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_evaluation_submissions`
--

CREATE TABLE `teacher_evaluation_submissions` (
  `submission_id` int(11) NOT NULL,
  `round_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_evaluation_submissions`
--

INSERT INTO `teacher_evaluation_submissions` (`submission_id`, `round_id`, `student_id`, `offering_id`, `submitted_at`) VALUES
(1, 1, 450, 51, '2026-09-19 14:34:42'),
(2, 1, 454, 51, '2026-09-19 15:07:21'),
(3, 1, 453, 51, '2026-09-19 15:09:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_session_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `status`, `created_at`, `updated_at`, `current_session_token`) VALUES
(474, 'Frenzz', '$2y$10$UyCTbPCimuMwFjKVxxSoHe4xBZsTrLFhu0.sgaSL.fAUP8mPo81KW', 'admin', 'active', '2026-09-06 10:06:34', '2026-09-19 14:16:05', 'd17184f959b804cfc77254e63083aafda0ea175436a591673f0331dffe2550dc'),
(476, 'frenzypaller@gmail.com', '$2y$10$XDZ94Mn2G4raGF6m21oKhOTIXzMizRHP9kLpjQCK1R8FfS79iLRXq', 'teacher', 'active', '2026-09-06 10:09:06', '2026-09-18 13:02:13', '0a12cd666df992332283ecff821b63608e9399e2944ca2008126df35a13a66d1'),
(477, 'STU-1111-123006', '$2y$10$TTMq27CiffY9T3HJElogMe0bzLu9oGuZwuLA25nN9iJsgxrvfnYEK', 'student', 'active', '2026-09-06 10:10:07', '2026-09-19 14:44:01', NULL),
(478, 'jong@gmail.com', '$2y$10$UKY2qQcLSsxrw9RWDmQiSO4Xfd.jbeXDGPxCAk6DsJoU5cJWIiYIu', 'admin', 'active', '2026-09-18 13:58:23', '2026-09-18 13:58:23', NULL),
(479, 'zitherpaller7@gmail.com', '$2y$10$bVIuFgc8ImN.LPNjxR8wmub8rsm6aEN0wnAp19gnwzXQMUOqbWRBu', 'admin', 'active', '2026-09-18 15:48:09', '2026-09-18 15:50:46', '3f7345fb5733e49e070234542a340eb9a0d90f9f982914cd8ab7c7744977de07'),
(480, 'STU-7898-091526', '$2y$10$8S/FlXcluJHJRV5QQZNumOOw48vr46.lS9THPnIRteIJle0pxcuRK', 'student', 'active', '2026-09-18 15:51:33', '2026-09-18 15:51:33', NULL),
(481, 'STU-2313-092626', '$2y$10$Yai1G7uufxsyTtPp21GCe.73pvD4bZWQ0Nd3IFmGdj.UMH5lDeQsC', 'student', 'active', '2026-09-19 14:37:06', '2026-09-19 14:45:18', NULL),
(482, 'STU-6767-090826', '$2y$10$aw.F2rdJIV1O.AodC7Hjj.IL7kMhQ027jWyBwqW0pTS5esOKUXpDG', 'student', 'active', '2026-09-19 14:37:45', '2026-09-19 15:08:43', '7a8ea6b3cf103fa103d24361df79e460916cb880ac14d6e3e789947ca62c82e6'),
(483, 'STU-7666-021407', '$2y$10$QzgxJbFh.Bj8QhDA5HvhHe2j/MqIAysS/z7mWJ3bcho7YPfQzUojK', 'student', 'active', '2026-09-19 15:00:42', '2026-09-19 15:08:36', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `uq_admin_module` (`user_id`,`module_key`),
  ADD KEY `idx_admin_permissions_user` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `fk_announcements_users` (`posted_by`),
  ADD KEY `fk_announcements_offering` (`offering_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `fk_assignments_offering` (`offering_id`),
  ADD KEY `fk_assignments_teacher` (`created_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `fk_attendance_student` (`student_id`),
  ADD KEY `fk_attendance_offering` (`offering_id`),
  ADD KEY `fk_attendance_teacher` (`recorded_by`);

--
-- Indexes for table `at_risk_insights`
--
ALTER TABLE `at_risk_insights`
  ADD PRIMARY KEY (`insight_id`),
  ADD UNIQUE KEY `uq_student_offering_lang` (`student_id`,`offering_id`,`lang`);

--
-- Indexes for table `classofferings`
--
ALTER TABLE `classofferings`
  ADD PRIMARY KEY (`offering_id`),
  ADD UNIQUE KEY `uq_classofferings_subject_section_quarter_schoolyear` (`subject_id`,`section_id`,`quarter`,`school_year_id`),
  ADD KEY `fk_classofferings_teacher_id_teachers` (`teacher_id`),
  ADD KEY `fk_classofferings_section_id_sections` (`section_id`),
  ADD KEY `fk_classofferings_school_year_id_schoolyears` (`school_year_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `uq_enrollments_student_offering` (`student_id`,`offering_id`),
  ADD KEY `fk_enrollments_offering_id_classofferings` (`offering_id`);

--
-- Indexes for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_er_student` (`student_id`),
  ADD KEY `fk_er_subject` (`subject_id`),
  ADD KEY `fk_er_offering` (`offering_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD KEY `fk_grades_enrollment` (`enrollment_id`),
  ADD KEY `fk_grades_teacher` (`graded_by`);

--
-- Indexes for table `learning_materials`
--
ALTER TABLE `learning_materials`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `fk_learningmaterials_offering_id_classofferings` (`offering_id`),
  ADD KEY `fk_learningmaterials_uploaded_by_teachers` (`uploaded_by`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quiz_id`),
  ADD KEY `idx_quizzes_offering` (`offering_id`),
  ADD KEY `idx_quizzes_created_by` (`created_by`),
  ADD KEY `idx_quizzes_source_material` (`source_material_id`);

--
-- Indexes for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD UNIQUE KEY `uniq_ans_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `idx_ans_question` (`question_id`),
  ADD KEY `fk_ans_choice` (`selected_choice_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD UNIQUE KEY `uniq_qa_attempt` (`quiz_id`,`student_id`,`attempt_number`),
  ADD KEY `idx_qa_student` (`student_id`);

--
-- Indexes for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  ADD PRIMARY KEY (`choice_id`),
  ADD KEY `idx_qc_question` (`question_id`);

--
-- Indexes for table `quiz_generation_jobs`
--
ALTER TABLE `quiz_generation_jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `idx_jobs_offering` (`offering_id`),
  ADD KEY `fk_jobs_quiz` (`quiz_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `idx_qq_quiz` (`quiz_id`);

--
-- Indexes for table `schoolyears`
--
ALTER TABLE `schoolyears`
  ADD PRIMARY KEY (`school_year_id`),
  ADD UNIQUE KEY `label` (`label`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `uq_sections_name_grade_year` (`section_name`,`grade_level`,`school_year_id`),
  ADD KEY `fk_sections_school_year_id_schoolyears` (`school_year_id`),
  ADD KEY `adviser_id_idx` (`adviser_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_lrn` (`student_lrn`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD UNIQUE KEY `uq_submissions_assignment_student_attempt` (`assignment_id`,`student_id`,`attempt_number`),
  ADD KEY `fk_submissions_student` (`student_id`),
  ADD KEY `fk_submissions_grader` (`graded_by`);

--
-- Indexes for table `submission_files`
--
ALTER TABLE `submission_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `fk_submission_files_submission` (`submission_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `teacher_evaluation_answers`
--
ALTER TABLE `teacher_evaluation_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD UNIQUE KEY `uq_tea_response_question` (`response_id`,`question_key`),
  ADD KEY `idx_tea_question` (`question_key`);

--
-- Indexes for table `teacher_evaluation_responses`
--
ALTER TABLE `teacher_evaluation_responses`
  ADD PRIMARY KEY (`response_id`),
  ADD KEY `idx_ter_resp_round_teacher` (`round_id`,`teacher_id`),
  ADD KEY `idx_ter_resp_offering` (`offering_id`),
  ADD KEY `fk_ter_resp_teacher` (`teacher_id`);

--
-- Indexes for table `teacher_evaluation_rounds`
--
ALTER TABLE `teacher_evaluation_rounds`
  ADD PRIMARY KEY (`round_id`),
  ADD KEY `idx_ter_school_year` (`school_year_id`),
  ADD KEY `idx_ter_status` (`status`),
  ADD KEY `fk_ter_opened_by` (`opened_by`);

--
-- Indexes for table `teacher_evaluation_submissions`
--
ALTER TABLE `teacher_evaluation_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD UNIQUE KEY `uq_tes_round_student_offering` (`round_id`,`student_id`,`offering_id`),
  ADD KEY `idx_tes_student` (`student_id`),
  ADD KEY `idx_tes_offering` (`offering_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `at_risk_insights`
--
ALTER TABLE `at_risk_insights`
  MODIFY `insight_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `classofferings`
--
ALTER TABLE `classofferings`
  MODIFY `offering_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `grade_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learning_materials`
--
ALTER TABLE `learning_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quiz_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  MODIFY `choice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=186;

--
-- AUTO_INCREMENT for table `quiz_generation_jobs`
--
ALTER TABLE `quiz_generation_jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `schoolyears`
--
ALTER TABLE `schoolyears`
  MODIFY `school_year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=455;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `submission_files`
--
ALTER TABLE `submission_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `teacher_evaluation_answers`
--
ALTER TABLE `teacher_evaluation_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_evaluation_responses`
--
ALTER TABLE `teacher_evaluation_responses`
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teacher_evaluation_rounds`
--
ALTER TABLE `teacher_evaluation_rounds`
  MODIFY `round_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_evaluation_submissions`
--
ALTER TABLE `teacher_evaluation_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=484;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD CONSTRAINT `fk_admin_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_offering` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcements_users` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignments_offering` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_teacher` FOREIGN KEY (`created_by`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_offering` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_teacher` FOREIGN KEY (`recorded_by`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `classofferings`
--
ALTER TABLE `classofferings`
  ADD CONSTRAINT `fk_classofferings_school_year_id_schoolyears` FOREIGN KEY (`school_year_id`) REFERENCES `schoolyears` (`school_year_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_classofferings_section_id_sections` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_classofferings_subject_id_subjects` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_classofferings_teacher_id_teachers` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `fk_enrollments_offering_id_classofferings` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrollments_student_id_students` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD CONSTRAINT `fk_er_offering` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_er_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_er_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `fk_grades_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grades_teacher` FOREIGN KEY (`graded_by`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `learning_materials`
--
ALTER TABLE `learning_materials`
  ADD CONSTRAINT `fk_learningmaterials_offering_id_classofferings` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learningmaterials_uploaded_by_teachers` FOREIGN KEY (`uploaded_by`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quizzes_material` FOREIGN KEY (`source_material_id`) REFERENCES `learning_materials` (`material_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_quizzes_offering` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quizzes_teacher` FOREIGN KEY (`created_by`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `fk_ans_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`attempt_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ans_choice` FOREIGN KEY (`selected_choice_id`) REFERENCES `quiz_choices` (`choice_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ans_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `fk_qa_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qa_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  ADD CONSTRAINT `fk_qc_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_generation_jobs`
--
ALTER TABLE `quiz_generation_jobs`
  ADD CONSTRAINT `fk_jobs_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE SET NULL;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `fk_qq_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_sections_adviser_id_teachers` FOREIGN KEY (`adviser_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sections_school_year_id_schoolyears` FOREIGN KEY (`school_year_id`) REFERENCES `schoolyears` (`school_year_id`) ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_submissions_grader` FOREIGN KEY (`graded_by`) REFERENCES `teachers` (`teacher_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `submission_files`
--
ALTER TABLE `submission_files`
  ADD CONSTRAINT `fk_submission_files_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher_evaluation_answers`
--
ALTER TABLE `teacher_evaluation_answers`
  ADD CONSTRAINT `fk_tea_response` FOREIGN KEY (`response_id`) REFERENCES `teacher_evaluation_responses` (`response_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher_evaluation_responses`
--
ALTER TABLE `teacher_evaluation_responses`
  ADD CONSTRAINT `fk_ter_resp_offering` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ter_resp_round` FOREIGN KEY (`round_id`) REFERENCES `teacher_evaluation_rounds` (`round_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ter_resp_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher_evaluation_rounds`
--
ALTER TABLE `teacher_evaluation_rounds`
  ADD CONSTRAINT `fk_ter_opened_by` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ter_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `schoolyears` (`school_year_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher_evaluation_submissions`
--
ALTER TABLE `teacher_evaluation_submissions`
  ADD CONSTRAINT `fk_tes_offering` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tes_round` FOREIGN KEY (`round_id`) REFERENCES `teacher_evaluation_rounds` (`round_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tes_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
