-- School year archiving: adds 3 columns to `schoolyears`.
-- Safe to run more than once (MariaDB / XAMPP). The app also adds these
-- automatically on the first page load, so running this is optional.

ALTER TABLE `schoolyears`
  ADD COLUMN IF NOT EXISTS `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `archived_at` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `auto_archive_paused` TINYINT(1) NOT NULL DEFAULT 0;
