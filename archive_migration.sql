-- Optional: the app adds these columns automatically on first page load (config/config.php).
-- Run this by hand only if you prefer to migrate the database yourself.
ALTER TABLE `classofferings` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `sections`       ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `subjects`       ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL;
