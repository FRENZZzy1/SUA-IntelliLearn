-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 13, 2026 at 10:50 AM
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
  `position` enum('principal','registrar','staff') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `posted_by`, `title`, `body`, `audience`, `priority`, `offering_id`, `status`, `is_pinned`, `created_at`, `updated_at`) VALUES
(1, 1, 'Do your assignments', 'Gawin ang assignment para may regalo kay santa', 'students', 'important', NULL, 'published', 0, '2026-08-11 07:38:16', '2026-08-11 07:38:16');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
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

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`assignment_id`, `offering_id`, `title`, `description`, `instructions_file_path`, `due_date`, `points`, `max_attempts`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 1, 'asdasd', 'adasda', NULL, '2026-08-13 16:50:00', 100.00, 2, 'published', 1, '2026-08-13 08:46:03', '2026-08-13 08:46:03');

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
(1, 1, 1, 1, 'TRM 1', 1, 'M - W', '07:00:00', '10:00:00', 50, 'active', '2026-08-11 07:19:02');

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
(1, 1, 1, 'active', '2026-08-11 07:19:15');

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
(1, 1, 7, 1, NULL, 1, 'approved', NULL, '2026-08-11 07:19:13', '2026-08-11 07:19:15', 1);

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
(1, NULL, 1, 3, 'topic', NULL, 'Polynomials', 'failed', 'poolside/laguna-s-2.1:free: OPENROUTER_API_KEY is not configured in config.php. | nvidia/nemotron-3-super-120b-a12b:free: OPENROUTER_API_KEY is not configured in config.php.', '2026-08-11 07:20:29', '2026-08-11 07:20:29');

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
(1, 'SY 2026 -', '2026-08-01', '2027-08-01', 1);

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
(1, 'A110', 7, NULL, NULL, 1, '2026-08-11 07:18:25');

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
(1, 4, 20003644121, 'dan', 'dan', 'dan', NULL, '2003-10-02', 'Lupang Arenda, Block 24, Mahinahon Street, Taytay, Rizal', 'Ma. Cecilia T. Aniasco', '0988695948697', '2026-08-11 07:11:30', '2026-08-11 07:11:30', 'Male');

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
(1, 'Math', NULL, '2026-08-11 07:16:04');

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

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`submission_id`, `assignment_id`, `student_id`, `attempt_number`, `submission_text`, `file_path`, `external_url`, `file_size`, `status`, `score`, `feedback`, `graded_by`, `graded_at`, `submitted_at`, `updated_at`) VALUES
(6, 6, 1, 1, NULL, NULL, NULL, NULL, 'submitted', NULL, NULL, NULL, NULL, '2026-08-13 08:46:29', '2026-08-13 08:46:29'),
(7, 6, 1, 2, NULL, NULL, NULL, NULL, 'submitted', NULL, NULL, NULL, NULL, '2026-08-13 08:46:36', '2026-08-13 08:46:36');

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

--
-- Dumping data for table `submission_files`
--

INSERT INTO `submission_files` (`file_id`, `submission_id`, `original_name`, `file_path`, `file_size`, `uploaded_at`) VALUES
(1, 6, '069f8b09-c2db-497f-912d-7a8451ef6cca.jpg', 'assets/student_submissions/1/6/student1_069f8b09-c2db-497f-912d-7a8451ef6cca_1c26a07c857e.jpg', 83735, '2026-08-13 08:46:29'),
(2, 6, 'a7cf85ca-d373-4373-8e2f-31f5b30ff5e1.jpg', 'assets/student_submissions/1/6/student1_a7cf85ca-d373-4373-8e2f-31f5b30ff5e1_4d9559ffce67.jpg', 70944, '2026-08-13 08:46:29'),
(3, 7, 'Professional Modern CV Resume (3).pdf', 'assets/student_submissions/1/6/student1_Professional_Modern_CV_Resume__3__f5fcd240188b.pdf', 643222, '2026-08-13 08:46:36'),
(4, 7, 'Professional Modern CV Resume (1).pdf', 'assets/student_submissions/1/6/student1_Professional_Modern_CV_Resume__1__1837af495be4.pdf', 103925, '2026-08-13 08:46:36');

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
(1, 3, 'pals', 'pals', 'pals', 'frenzypaller@gmail.com', 'full-time', 'Highschool/Senior High', 'Algebra', '2026-08-11 07:10:35', '2026-08-11 07:10:35');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'daniel', '$2y$10$4WbwkDthAIbnbCCrn9DsrO9fnB4mK9a.2jXOYKKUBrmDZFvwzbWKO', 'admin', 'active', '2026-08-11 07:09:42', '2026-08-11 07:10:00'),
(3, 'frenzypaller@gmail.com', '$2y$10$Y7/q3aqEh1Xbed1bUjkFF.02jCXWV5vTuGNrWDfEXpJiM1eieHEH.', 'teacher', 'active', '2026-08-11 07:10:35', '2026-08-11 07:10:35'),
(4, 'STU-4121-100203', '$2y$10$hz/tKAZprCdX1pSb.pVXfeaDd/sNx7eObvwx7W7gaffukkOKxbLri', 'student', 'active', '2026-08-11 07:11:30', '2026-08-11 07:11:30');

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
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

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
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT for table `classofferings`
--
ALTER TABLE `classofferings`
  MODIFY `offering_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `grade_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learning_materials`
--
ALTER TABLE `learning_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quiz_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  MODIFY `choice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_generation_jobs`
--
ALTER TABLE `quiz_generation_jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schoolyears`
--
ALTER TABLE `schoolyears`
  MODIFY `school_year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `submission_files`
--
ALTER TABLE `submission_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
