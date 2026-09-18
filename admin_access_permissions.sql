-- SUA IntelliLearn: granular admin access levels
-- Run this migration against the existing LMS database.
CREATE TABLE IF NOT EXISTS admin_permissions (
  permission_id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  permission ENUM('read','write') NOT NULL DEFAULT 'read',
  can_approve_enrollment TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (permission_id),
  UNIQUE KEY uq_admin_module (user_id, module_key),
  KEY idx_admin_permissions_user (user_id),
  CONSTRAINT fk_admin_permissions_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
