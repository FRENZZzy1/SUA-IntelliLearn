-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 25, 2026 at 10:03 AM
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

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `user_id`, `email`, `access_level`, `position`, `created_at`) VALUES
(2, 2, 'frenzypaller@gmail.com', 'full', 'principal', '2026-07-09 12:26:15');

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
-- Table structure for table `classofferings`
--

CREATE TABLE `classofferings` (
  `offering_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `quarter` smallint(6) NOT NULL,
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
(34, 3, 3, 13, 1, 1, 'M - W', '07:00:00', '10:00:00', 50, 'active', '2026-07-25 01:39:30'),
(35, 3, 3, 13, 2, 1, 'M - W', '07:00:00', '10:00:00', 50, 'active', '2026-07-25 02:45:32'),
(37, 11, 3, 4, 1, 1, 'M - W', '07:00:00', '10:00:00', 50, 'active', '2026-07-25 07:07:06'),
(38, 12, 3, 14, 1, 1, NULL, NULL, NULL, 50, 'active', '2026-07-25 08:00:11');

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
(23, 8, 34, 'active', '2026-07-25 07:47:37'),
(24, 8, 35, 'active', '2026-07-25 07:47:48');

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
(39, 8, 7, 3, NULL, 34, 'approved', NULL, '2026-07-25 07:47:36', '2026-07-25 07:47:37', 2),
(40, 8, 7, 3, NULL, 35, 'approved', NULL, '2026-07-25 07:47:47', '2026-07-25 07:47:48', 2);

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
(1, '2025-2026', '2025-06-01', '2026-04-30', 1);

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
(1, 'Rizal', 7, NULL, 3, 1, '2026-07-10 11:47:19'),
(3, 'Mabini', 9, NULL, 3, 1, '2026-07-10 11:47:19'),
(4, 'Aguinaldo', 10, 'TVL', 4, 1, '2026-07-10 11:47:19'),
(5, 'STEM-A', 11, 'STEM', NULL, 1, '2026-07-10 11:47:19'),
(6, 'ABM-A', 11, 'ABM', NULL, 1, '2026-07-10 11:47:19'),
(7, 'HUMSS-A', 12, 'HUMSS', NULL, 1, '2026-07-10 11:47:19'),
(8, 'TVL-A', 12, 'TVL', NULL, 1, '2026-07-10 11:47:19'),
(9, 'Daniel - Bugrit', 9, NULL, 5, 1, '2026-07-11 01:05:31'),
(11, 'CSS - 1', 11, 'TVL', 2, 1, '2026-07-18 07:38:11'),
(12, 'CSS - 2', 11, 'TVL', NULL, 1, '2026-07-18 07:40:13'),
(13, 'Del Pilar', 7, NULL, NULL, 1, '2026-07-19 07:04:39'),
(14, 'Pamimingwit', 7, NULL, NULL, 1, '2026-07-25 07:49:11');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `student_lrn`, `firstname`, `lastname`, `middlename`, `email`, `birthdate`, `address`, `guardian_name`, `guardian_contact`, `created_at`, `updated_at`) VALUES
(1, 3, 0, 'Zither', 'Alphonsus Paller', NULL, 'frenz@gmail.com', '2000-01-01', '', '', '', '2026-07-09 13:38:41', '2026-07-09 13:41:25'),
(2, 4, 334456576896, 'Frenz allen J', 'Paller', 'Empimo', 'pallerxdfrenz@gmail.com', '2005-09-11', 'Blk 2 Lot 50', 'Jeffreu', '09695949394959', '2026-07-09 14:18:04', '2026-07-09 14:18:04'),
(3, 10, 100000000001, 'Miguel', 'Reyes', NULL, NULL, '2010-03-14', NULL, NULL, NULL, '2026-07-18 07:04:04', '2026-07-18 07:04:04'),
(4, 11, 100000000002, 'Ana', 'Torres', NULL, NULL, '2010-06-22', NULL, NULL, NULL, '2026-07-18 07:04:05', '2026-07-18 07:04:05'),
(5, 12, 100000000003, 'Carlo', 'Bautista', NULL, NULL, '2010-01-09', NULL, NULL, NULL, '2026-07-18 07:04:05', '2026-07-18 07:04:05'),
(6, 13, 100000000004, 'Liza', 'Mendoza', NULL, NULL, '2011-09-30', NULL, NULL, NULL, '2026-07-18 07:04:05', '2026-07-18 07:04:05'),
(7, 14, 100000000005, 'Jose', 'Fernandez', NULL, NULL, '2009-11-02', NULL, NULL, NULL, '2026-07-18 07:04:05', '2026-07-18 07:04:05'),
(8, 15, 20003644121, 'Daniel', 'Aniasco', 'Talidong', 'daniel.4niasco@gmail.com', '2003-10-02', 'Lupang Arenda, Block 24, Mahinahon Street, Taytay, Rizal', 'Ma. Cecilia T. Aniasco', '09666837362', '2026-07-18 07:26:23', '2026-07-18 07:26:23'),
(9, 16, 123456789101, 'Darcy', 'Aniasco', 'Talidong', NULL, '2026-07-13', 'Lupang Arenda, Block 24, Mahinahon Street, Taytay, Rizal', 'Ma. Cecilia T. Aniasco', '09666837362', '2026-07-25 07:22:32', '2026-07-25 07:22:32');

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
(1, 'Math', 'Mathematics', '2026-07-10 11:47:19'),
(2, 'Science', 'Science', '2026-07-10 11:47:19'),
(3, 'English', 'English', '2026-07-10 11:47:19'),
(4, 'Filipino', 'Filipino', '2026-07-10 11:47:19'),
(5, 'TLE', 'Technology and Livelihood Education', '2026-07-10 11:47:19'),
(6, 'MAPEH', 'Music, Arts, PE, Health', '2026-07-10 11:47:19'),
(8, 'Practical Research', 'Bugrit', '2026-07-11 01:06:39'),
(9, 'Araling Panlipunan', 'Arpan', '2026-07-17 12:21:00'),
(10, 'Entrepreneurship', 'ABM strand elective', '2026-07-18 07:04:04'),
(11, 'Computer System Servicing', 'CSS', '2026-07-18 07:33:57'),
(12, 'Pamimingwit', 'fishing', '2026-07-18 08:11:12'),
(13, 'Pamimingwit1', NULL, '2026-07-25 07:48:35');

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
(1, 5, 'Mario', 'Dela Cruz', NULL, 'mdelacruz@sua.edu.ph', 'full-time', 'Mathematics', 'Algebra', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(2, 6, 'Vera', 'Villanueva', NULL, 'vvillanueva@sua.edu.ph', 'full-time', 'Science', 'Biology', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(3, 7, 'Anna', 'Aquino', NULL, 'aaquino@sua.edu.ph', 'full-time', 'English', 'Literature', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(4, 8, 'Rosario', 'Ramos', NULL, 'rramos@sua.edu.ph', 'full-time', 'Filipino', 'Panitikan', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(5, 9, 'Jose', 'Soriano', NULL, 'jsoriano@sua.edu.ph', 'part-time', 'TLE', 'Entrepreneurship', '2026-07-10 11:47:19', '2026-07-10 11:47:19');

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
(2, 'Frenzz', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'admin', 'active', '2026-07-09 12:25:51', '2026-07-09 12:25:51'),
(3, 'zither', '$2y$10$Q5uySJGzv1PvD.VMr4G5D.loN60HsTyxmHQPQMobq0j1mtJ8AQ0ZO', 'student', 'active', '2026-07-09 13:38:41', '2026-07-09 13:41:25'),
(4, 'STU-6896-091105', '$2y$10$bY2Y2Yj6Krzgar//zTumYu7dkjNJNWT4f7.FgsW4R5498bJctcOWe', 'student', 'active', '2026-07-09 14:18:04', '2026-07-09 14:18:04'),
(5, 'mdelacruz', '$2b$10$cAqj6vOW0Hn9zc5hFgr5WOr1R7VgVb5V14EN8KStYiscCXW1JuyYC', 'teacher', 'active', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(6, 'vvillanueva', '$2b$10$cAqj6vOW0Hn9zc5hFgr5WOr1R7VgVb5V14EN8KStYiscCXW1JuyYC', 'teacher', 'active', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(7, 'aaquino', '$2b$10$cAqj6vOW0Hn9zc5hFgr5WOr1R7VgVb5V14EN8KStYiscCXW1JuyYC', 'teacher', 'active', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(8, 'rramos', '$2b$10$cAqj6vOW0Hn9zc5hFgr5WOr1R7VgVb5V14EN8KStYiscCXW1JuyYC', 'teacher', 'active', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(9, 'jsoriano', '$2b$10$cAqj6vOW0Hn9zc5hFgr5WOr1R7VgVb5V14EN8KStYiscCXW1JuyYC', 'teacher', 'active', '2026-07-10 11:47:19', '2026-07-10 11:47:19'),
(10, 'miguel.reyes', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active', '2026-07-18 07:04:04', '2026-07-18 07:04:04'),
(11, 'ana.torres', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active', '2026-07-18 07:04:04', '2026-07-18 07:04:04'),
(12, 'carlo.bautista', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active', '2026-07-18 07:04:05', '2026-07-18 07:04:05'),
(13, 'liza.mendoza', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active', '2026-07-18 07:04:05', '2026-07-18 07:04:05'),
(14, 'jose.fernandez', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active', '2026-07-18 07:04:05', '2026-07-18 07:04:05'),
(15, 'STU-4121-100203', '$2y$10$7LYsx.uHbU12iE63c5iGwe8RwOaotT5m9DO3ECqxPMqDrjGDXobOm', 'student', 'active', '2026-07-18 07:26:23', '2026-07-18 07:26:23'),
(16, 'STU-9101-071326', '$2y$10$B2ckRetFVqj/JJV.gScV8eUllMmuE17w5gxI.0f1W5ej8ErD4fgPW', 'student', 'active', '2026-07-25 07:22:32', '2026-07-25 07:22:32');

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
-- Indexes for table `learning_materials`
--
ALTER TABLE `learning_materials`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `fk_learningmaterials_offering_id_classofferings` (`offering_id`),
  ADD KEY `fk_learningmaterials_uploaded_by_teachers` (`uploaded_by`);

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
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `classofferings`
--
ALTER TABLE `classofferings`
  MODIFY `offering_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `learning_materials`
--
ALTER TABLE `learning_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schoolyears`
--
ALTER TABLE `schoolyears`
  MODIFY `school_year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
-- Constraints for table `learning_materials`
--
ALTER TABLE `learning_materials`
  ADD CONSTRAINT `fk_learningmaterials_offering_id_classofferings` FOREIGN KEY (`offering_id`) REFERENCES `classofferings` (`offering_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learningmaterials_uploaded_by_teachers` FOREIGN KEY (`uploaded_by`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

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
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
