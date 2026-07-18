-- =================================================================
-- ENROLLMENT MODULE MIGRATION
-- Run this once against the `lms` database (phpMyAdmin > Import,
-- or SQL tab > paste + Go) after importing lms new.sql.
-- Safe to re-run: every INSERT is guarded against duplicates.
-- =================================================================

-- -----------------------------------------------------------------
-- 1. enrollment_requests table
--    One row per "a student wants to take this subject at this
--    grade level" request. Approving a request finds (or lets the
--    admin pick, if there's more than one match) the matching
--    classofferings row and inserts into `enrollments` — which is
--    the same table courses.php already reads its Enrollment /
--    Enrolled Students counts from.
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `enrollment_requests` (
  `request_id`   int(11) NOT NULL AUTO_INCREMENT,
  `student_id`   int(11) NOT NULL,
  `grade_level`  int(11) NOT NULL,
  `subject_id`   int(11) NOT NULL,
  `strand`       varchar(50) DEFAULT NULL,
  `offering_id`  int(11) DEFAULT NULL,
  `status`       enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `notes`        varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `decided_at`   timestamp NULL DEFAULT NULL,
  `decided_by`   int(11) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `fk_er_student` (`student_id`),
  KEY `fk_er_subject` (`subject_id`),
  KEY `fk_er_offering` (`offering_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add FKs separately so the migration doesn't die if they already exist
-- from a previous partial run.
SET @fk1 := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_er_student');
SET @sql1 := IF(@fk1 = 0,
    'ALTER TABLE enrollment_requests ADD CONSTRAINT fk_er_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

SET @fk2 := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_er_subject');
SET @sql2 := IF(@fk2 = 0,
    'ALTER TABLE enrollment_requests ADD CONSTRAINT fk_er_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id)',
    'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

SET @fk3 := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_er_offering');
SET @sql3 := IF(@fk3 = 0,
    'ALTER TABLE enrollment_requests ADD CONSTRAINT fk_er_offering FOREIGN KEY (offering_id) REFERENCES classofferings(offering_id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- -----------------------------------------------------------------
-- 2. "Entrepreneurship" subject (ABM elective referenced by the demo data)
-- -----------------------------------------------------------------
INSERT INTO subjects (subject_name, description)
SELECT 'Entrepreneurship', 'ABM strand elective'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE subject_name = 'Entrepreneurship');

-- -----------------------------------------------------------------
-- 3. Demo student accounts (users + students) matching the mockup
-- -----------------------------------------------------------------
INSERT INTO users (username, password, role, status)
SELECT * FROM (SELECT 'miguel.reyes' AS username, '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2' AS password, 'student' AS role, 'active' AS status) t
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'miguel.reyes');

INSERT INTO students (user_id, student_lrn, firstname, lastname, birthdate)
SELECT u.id, 100000000001, 'Miguel', 'Reyes', '2010-03-14'
FROM users u WHERE u.username = 'miguel.reyes'
  AND NOT EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id);

INSERT INTO users (username, password, role, status)
SELECT * FROM (SELECT 'ana.torres', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active') t
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'ana.torres');

INSERT INTO students (user_id, student_lrn, firstname, lastname, birthdate)
SELECT u.id, 100000000002, 'Ana', 'Torres', '2010-06-22'
FROM users u WHERE u.username = 'ana.torres'
  AND NOT EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id);

INSERT INTO users (username, password, role, status)
SELECT * FROM (SELECT 'carlo.bautista', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active') t
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'carlo.bautista');

INSERT INTO students (user_id, student_lrn, firstname, lastname, birthdate)
SELECT u.id, 100000000003, 'Carlo', 'Bautista', '2010-01-09'
FROM users u WHERE u.username = 'carlo.bautista'
  AND NOT EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id);

INSERT INTO users (username, password, role, status)
SELECT * FROM (SELECT 'liza.mendoza', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active') t
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'liza.mendoza');

INSERT INTO students (user_id, student_lrn, firstname, lastname, birthdate)
SELECT u.id, 100000000004, 'Liza', 'Mendoza', '2011-09-30'
FROM users u WHERE u.username = 'liza.mendoza'
  AND NOT EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id);

INSERT INTO users (username, password, role, status)
SELECT * FROM (SELECT 'jose.fernandez', '$2y$10$Ccr9pcZMEVhU1s9k7EBsy.OQdLDZX8G9LCyzYhlZUAuaCrgrL/EC2', 'student', 'active') t
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'jose.fernandez');

INSERT INTO students (user_id, student_lrn, firstname, lastname, birthdate)
SELECT u.id, 100000000005, 'Jose', 'Fernandez', '2009-11-02'
FROM users u WHERE u.username = 'jose.fernandez'
  AND NOT EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id);

-- -----------------------------------------------------------------
-- 4. Class offerings so Approve has a real section to match each
--    demo request against (subject + grade level -> section).
--    Grade 10 = Aguinaldo, Grade 9 = Mabini, Grade 11 ABM = ABM-A.
-- -----------------------------------------------------------------
INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, capacity, status)
SELECT s.subject_id, t.teacher_id, sec.section_id, 1, 50, 'active'
FROM subjects s, teachers t, sections sec
WHERE s.subject_name = 'Science' AND t.lastname = 'Villanueva' AND sec.section_name = 'Aguinaldo'
  AND NOT EXISTS (
    SELECT 1 FROM classofferings co
    WHERE co.subject_id = s.subject_id AND co.section_id = sec.section_id AND co.quarter = 1
  );

INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, capacity, status)
SELECT s.subject_id, t.teacher_id, sec.section_id, 1, 50, 'active'
FROM subjects s, teachers t, sections sec
WHERE s.subject_name = 'English' AND t.lastname = 'Aquino' AND sec.section_name = 'Aguinaldo'
  AND NOT EXISTS (
    SELECT 1 FROM classofferings co
    WHERE co.subject_id = s.subject_id AND co.section_id = sec.section_id AND co.quarter = 1
  );

INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, capacity, status)
SELECT s.subject_id, t.teacher_id, sec.section_id, 1, 50, 'active'
FROM subjects s, teachers t, sections sec
WHERE s.subject_name = 'Filipino' AND t.lastname = 'Ramos' AND sec.section_name = 'Aguinaldo'
  AND NOT EXISTS (
    SELECT 1 FROM classofferings co
    WHERE co.subject_id = s.subject_id AND co.section_id = sec.section_id AND co.quarter = 1
  );

INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, capacity, status)
SELECT s.subject_id, t.teacher_id, sec.section_id, 1, 50, 'active'
FROM subjects s, teachers t, sections sec
WHERE s.subject_name = 'Science' AND t.lastname = 'Villanueva' AND sec.section_name = 'Mabini'
  AND NOT EXISTS (
    SELECT 1 FROM classofferings co
    WHERE co.subject_id = s.subject_id AND co.section_id = sec.section_id AND co.quarter = 1
  );

INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, capacity, status)
SELECT s.subject_id, t.teacher_id, sec.section_id, 1, 50, 'active'
FROM subjects s, teachers t, sections sec
WHERE s.subject_name = 'Entrepreneurship' AND t.lastname = 'Soriano' AND sec.section_name = 'ABM-A'
  AND NOT EXISTS (
    SELECT 1 FROM classofferings co
    WHERE co.subject_id = s.subject_id AND co.section_id = sec.section_id AND co.quarter = 1
  );

-- -----------------------------------------------------------------
-- 5. The 5 pending requests shown in the mockup
-- -----------------------------------------------------------------
INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, status, submitted_at)
SELECT st.student_id, 10, subj.subject_id, NULL, 'pending', '2026-03-25 09:00:00'
FROM students st, subjects subj
WHERE st.firstname = 'Miguel' AND st.lastname = 'Reyes' AND subj.subject_name = 'Science'
  AND NOT EXISTS (SELECT 1 FROM enrollment_requests er WHERE er.student_id = st.student_id AND er.subject_id = subj.subject_id AND er.grade_level = 10);

INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, status, submitted_at)
SELECT st.student_id, 10, subj.subject_id, NULL, 'pending', '2026-03-25 10:30:00'
FROM students st, subjects subj
WHERE st.firstname = 'Ana' AND st.lastname = 'Torres' AND subj.subject_name = 'English'
  AND NOT EXISTS (SELECT 1 FROM enrollment_requests er WHERE er.student_id = st.student_id AND er.subject_id = subj.subject_id AND er.grade_level = 10);

INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, status, submitted_at)
SELECT st.student_id, 10, subj.subject_id, NULL, 'pending', '2026-03-26 08:15:00'
FROM students st, subjects subj
WHERE st.firstname = 'Carlo' AND st.lastname = 'Bautista' AND subj.subject_name = 'Filipino'
  AND NOT EXISTS (SELECT 1 FROM enrollment_requests er WHERE er.student_id = st.student_id AND er.subject_id = subj.subject_id AND er.grade_level = 10);

INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, status, submitted_at)
SELECT st.student_id, 9, subj.subject_id, NULL, 'pending', '2026-03-26 13:45:00'
FROM students st, subjects subj
WHERE st.firstname = 'Liza' AND st.lastname = 'Mendoza' AND subj.subject_name = 'Science'
  AND NOT EXISTS (SELECT 1 FROM enrollment_requests er WHERE er.student_id = st.student_id AND er.subject_id = subj.subject_id AND er.grade_level = 9);

INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, status, submitted_at)
SELECT st.student_id, 11, subj.subject_id, 'ABM', 'pending', '2026-03-27 11:00:00'
FROM students st, subjects subj
WHERE st.firstname = 'Jose' AND st.lastname = 'Fernandez' AND subj.subject_name = 'Entrepreneurship'
  AND NOT EXISTS (SELECT 1 FROM enrollment_requests er WHERE er.student_id = st.student_id AND er.subject_id = subj.subject_id AND er.grade_level = 11);
