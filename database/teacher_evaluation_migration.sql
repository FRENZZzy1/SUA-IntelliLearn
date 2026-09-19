-- SUA IntelliLearn: Teacher Evaluation
-- Student-submitted evaluation for each active class offering.
CREATE TABLE IF NOT EXISTS teacher_evaluations (
    evaluation_id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    offering_id INT NOT NULL,
    rating_teaching_quality TINYINT NOT NULL,
    rating_communication TINYINT NOT NULL,
    rating_preparation TINYINT NOT NULL,
    rating_fairness TINYINT NOT NULL,
    rating_support TINYINT NOT NULL,
    comments TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (evaluation_id),
    UNIQUE KEY uq_teacher_evaluation_student_offering (student_id, offering_id),
    KEY idx_teacher_evaluations_teacher (teacher_id),
    KEY idx_teacher_evaluations_offering (offering_id),
    CONSTRAINT fk_teval_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teval_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teval_offering FOREIGN KEY (offering_id) REFERENCES classofferings(offering_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
