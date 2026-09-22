-- Base schema used by portal bootstrap.
-- For all live-server updates, import database/all_updates.sql instead.
CREATE TABLE IF NOT EXISTS `user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fname` VARCHAR(100) NOT NULL,
  `lname` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `user_type` INT NOT NULL DEFAULT 3,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `gender` ENUM('M','F') NOT NULL DEFAULT 'M',
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user` (`fname`, `lname`, `email`, `password`, `user_type`, `status`)
VALUES
  ('Admin', 'User', 'admin@localhost', '21232f297a57a5a743894a0e4a801fc3', 1, 1)
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

CREATE TABLE IF NOT EXISTS `teacher_attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `attendance_time` TIME NOT NULL,
  `latitude` DECIMAL(10,8) NULL,
  `longitude` DECIMAL(11,8) NULL,
  `logoff_time` TIME NULL,
  `logoff_latitude` DECIMAL(10,8) NULL,
  `logoff_longitude` DECIMAL(11,8) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_user_date` (`user_id`, `attendance_date`),
  KEY `idx_attendance_user_date` (`user_id`, `attendance_date`),
  KEY `idx_attendance_date` (`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_leave_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type_id` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `quota` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `gender_restriction` ENUM('All','Female','Male') NOT NULL DEFAULT 'All',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leave_type_role_code` (`user_type_id`, `code`),
  UNIQUE KEY `uq_leave_type_role_name` (`user_type_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_leave_applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `leave_type_id` BIGINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `days_count` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `reason` TEXT NOT NULL,
  `special_approval_reason` VARCHAR(200) NULL,
  `status` ENUM('Approval Pending','Approved','Rejected') NOT NULL DEFAULT 'Approval Pending',
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_user` (`user_id`),
  KEY `idx_leave_type` (`leave_type_id`),
  KEY `idx_leave_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_leave_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `leave_type_id` BIGINT UNSIGNED NOT NULL,
  `application_id` BIGINT UNSIGNED NULL,
  `days_count` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Approved','Rejected','Pending') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_history_user` (`user_id`),
  KEY `idx_leave_history_type` (`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notification_user` (`user_id`),
  KEY `idx_notification_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_settings_key_group` (`setting_key`, `setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teacher_leave_types` (`name`, `code`, `quota`, `gender_restriction`, `is_active`)
SELECT defaults.name, defaults.code, defaults.quota, defaults.gender_restriction, 1
FROM (
  SELECT 'CL' AS name, 'CL' AS code, 12.00 AS quota, 'All' AS gender_restriction
  UNION ALL SELECT 'PL', 'PL', 15.00, 'All'
  UNION ALL SELECT 'SL', 'SL', 10.00, 'All'
  UNION ALL SELECT 'Maternity Leave', 'Maternity', 0.00, 'Female'
  UNION ALL SELECT 'Other Leave', 'Other', 5.00, 'All'
) AS defaults
WHERE NOT EXISTS (SELECT 1 FROM teacher_leave_types existing WHERE existing.name = defaults.name OR existing.code = defaults.code);

INSERT INTO `teacher_settings` (`setting_key`, `setting_value`, `setting_group`, `description`)
VALUES
  ('attendance_entry_start_time', '10:30', 'attendance', 'Official attendance entry start time'),
  ('attendance_entry_end_time', '11:00', 'attendance', 'Official attendance entry end time'),
  ('attendance_exit_start_time', '16:00', 'attendance', 'Official attendance exit start time'),
  ('attendance_exit_end_time', '16:30', 'attendance', 'Official attendance exit end time'),
  ('email_notifications_enabled', '1', 'email', 'Enable email notifications')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `description` = VALUES(`description`);
