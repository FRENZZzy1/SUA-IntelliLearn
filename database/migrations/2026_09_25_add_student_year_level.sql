-- SUA IntelliLearn: add persistent student year level
-- Run once against the existing production/local database.
ALTER TABLE students
    ADD COLUMN year_level TINYINT UNSIGNED NULL AFTER guardian_contact;

ALTER TABLE students
    ADD CONSTRAINT chk_students_year_level
    CHECK (year_level IS NULL OR year_level BETWEEN 7 AND 12);
