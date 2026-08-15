-- ============================================================
-- SUA IntelliLearn — Settings feature migration
-- Run this once against the `lms` database before using settings.php
-- ============================================================

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by`    int(11) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sensible defaults so settings.php has something to render on first load.
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
  ('school_name', 'St. Uriel Academy'),
  ('default_class_capacity', '50'),
  ('auto_approve_enrollment', '0'),
  ('enrollment_open', '1'),
  ('passing_grade', '75')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
