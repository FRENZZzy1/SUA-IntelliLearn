-- SUA IntelliLearn: Teacher Evaluation
CREATE TABLE IF NOT EXISTS teacher_evaluations (
  evaluation_id INT NOT NULL AUTO_INCREMENT,
  school_year_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  start_date DATETIME NOT NULL,
  end_date DATETIME NOT NULL,
  status ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  anonymous TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (evaluation_id),
  KEY idx_te_school_year (school_year_id),
  KEY idx_te_status_dates (status,start_date,end_date),
  CONSTRAINT fk_te_school_year FOREIGN KEY (school_year_id) REFERENCES schoolyears(school_year_id) ON DELETE CASCADE,
  CONSTRAINT fk_te_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS evaluation_questions (
  question_id INT NOT NULL AUTO_INCREMENT,
  evaluation_id INT NOT NULL,
  question_text VARCHAR(500) NOT NULL,
  category VARCHAR(100) NOT NULL,
  question_type ENUM('rating','text') NOT NULL DEFAULT 'rating',
  sort_order INT NOT NULL DEFAULT 0,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (question_id),
  KEY idx_eq_evaluation (evaluation_id),
  CONSTRAINT fk_eq_evaluation FOREIGN KEY (evaluation_id) REFERENCES teacher_evaluations(evaluation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS evaluation_responses (
  response_id INT NOT NULL AUTO_INCREMENT,
  evaluation_id INT NOT NULL,
  student_id INT NOT NULL,
  offering_id INT NOT NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (response_id),
  UNIQUE KEY uq_er_student_offering (evaluation_id,student_id,offering_id),
  KEY idx_er_evaluation (evaluation_id),
  KEY idx_er_offering (offering_id),
  CONSTRAINT fk_er_evaluation FOREIGN KEY (evaluation_id) REFERENCES teacher_evaluations(evaluation_id) ON DELETE CASCADE,
  CONSTRAINT fk_er_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  CONSTRAINT fk_er_offering FOREIGN KEY (offering_id) REFERENCES classofferings(offering_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS evaluation_answers (
  answer_id INT NOT NULL AUTO_INCREMENT,
  response_id INT NOT NULL,
  question_id INT NOT NULL,
  rating TINYINT UNSIGNED NULL,
  text_answer TEXT NULL,
  PRIMARY KEY (answer_id),
  UNIQUE KEY uq_ea_response_question (response_id,question_id),
  KEY idx_ea_question (question_id),
  CONSTRAINT fk_ea_response FOREIGN KEY (response_id) REFERENCES evaluation_responses(response_id) ON DELETE CASCADE,
  CONSTRAINT fk_ea_question FOREIGN KEY (question_id) REFERENCES evaluation_questions(question_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO system_settings (setting_key, setting_value)
VALUES ('teacher_evaluation_enabled','0')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
