-- Select your existing portal database (for example nnv_admin) in phpMyAdmin,
-- then import this ONE file. Deploy the updated PHP files as well.
-- Requires the existing user and user_type tables. No demo users are added.
-- Re-imports preserve saved settings, role quotas, and later M/F changes.
-- School coordinates are entered through Settings; no location is invented.

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


DROP PROCEDURE IF EXISTS teacher_portal_apply_all_updates;
DELIMITER $$
CREATE PROCEDURE teacher_portal_apply_all_updates()
BEGIN
    DECLARE portal_lock INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        IF portal_lock = 1 THEN
            DO RELEASE_LOCK(CONCAT(DATABASE(), '_leave_roles_v1'));
        END IF;
        RESIGNAL;
    END;

    SELECT GET_LOCK(CONCAT(DATABASE(), '_leave_roles_v1'), 10) INTO portal_lock;
    IF portal_lock IS NULL OR portal_lock <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Could not acquire portal upgrade lock. Please retry.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user')
        OR NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_type') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Select the existing portal database containing user and user_type.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM user_type) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The user_type table must contain the existing user roles.';
    END IF;

    -- Only the initial gender upgrade resets existing users to M.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user' AND COLUMN_NAME = 'gender') THEN
        ALTER TABLE `user` ADD COLUMN gender ENUM('M','F') NOT NULL DEFAULT 'M';
    ELSEIF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user' AND COLUMN_NAME = 'gender'
        AND COLUMN_TYPE = 'enum(''M'',''F'')' AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT = 'M') THEN
        ALTER TABLE `user` MODIFY COLUMN gender VARCHAR(20) NULL DEFAULT 'M';
        UPDATE `user` SET gender = 'M';
        ALTER TABLE `user` MODIFY COLUMN gender ENUM('M','F') NOT NULL DEFAULT 'M';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_attendance' AND COLUMN_NAME = 'latitude') THEN
        ALTER TABLE teacher_attendance ADD COLUMN latitude DECIMAL(10,8) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_attendance' AND COLUMN_NAME = 'longitude') THEN
        ALTER TABLE teacher_attendance ADD COLUMN longitude DECIMAL(11,8) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_attendance' AND COLUMN_NAME = 'logoff_time') THEN
        ALTER TABLE teacher_attendance ADD COLUMN logoff_time TIME NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_attendance' AND COLUMN_NAME = 'logoff_latitude') THEN
        ALTER TABLE teacher_attendance ADD COLUMN logoff_latitude DECIMAL(10,8) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_attendance' AND COLUMN_NAME = 'logoff_longitude') THEN
        ALTER TABLE teacher_attendance ADD COLUMN logoff_longitude DECIMAL(11,8) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_leave_types' AND COLUMN_NAME = 'user_type_id') THEN
        ALTER TABLE teacher_leave_types ADD COLUMN user_type_id INT NULL AFTER id;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_leave_types' AND INDEX_NAME = 'uq_leave_type_code') THEN
        ALTER TABLE teacher_leave_types DROP INDEX uq_leave_type_code;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_leave_types' AND INDEX_NAME = 'uq_leave_type_name') THEN
        ALTER TABLE teacher_leave_types DROP INDEX uq_leave_type_name;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_leave_types' AND INDEX_NAME = 'uq_leave_type_role_code') THEN
        ALTER TABLE teacher_leave_types ADD UNIQUE KEY uq_leave_type_role_code (user_type_id, code);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'teacher_leave_types' AND INDEX_NAME = 'uq_leave_type_role_name') THEN
        ALTER TABLE teacher_leave_types ADD UNIQUE KEY uq_leave_type_role_name (user_type_id, name);
    END IF;

    START TRANSACTION;
    INSERT INTO teacher_settings (setting_key, setting_value, setting_group, description)
    VALUES
        ('attendance_entry_start_time', '10:30', 'attendance', 'Official attendance entry start time'),
        ('attendance_entry_end_time', '11:00', 'attendance', 'Official attendance entry end time'),
        ('attendance_exit_start_time', '16:00', 'attendance', 'Official attendance exit start time'),
        ('attendance_exit_end_time', '16:30', 'attendance', 'Official attendance exit end time'),
        ('settings_user_type_ids', '[3,4,5,10,11,12]', 'general', 'User types listed in attendance and leave settings'),
        ('email_notifications_enabled', '1', 'email', 'Enable email notifications')
    ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

    IF NOT EXISTS (SELECT 1 FROM teacher_settings
        WHERE setting_key = 'leave_user_type_records_v1' AND setting_group = 'migration') THEN
        -- Seed only an empty leave catalogue, never replace an existing catalogue.
        IF NOT EXISTS (SELECT 1 FROM teacher_leave_types) THEN
            INSERT INTO teacher_leave_types (name, code, quota, gender_restriction, is_active)
            VALUES ('CL', 'CL', 12, 'All', 1), ('PL', 'PL', 15, 'All', 1),
                ('SL', 'SL', 10, 'All', 1), ('Maternity Leave', 'Maternity', 0, 'Female', 1),
                ('Other Leave', 'Other', 5, 'All', 1);
        END IF;

        -- Keep the shared rows as legacy records. Create independent role records.
        INSERT INTO teacher_leave_types (user_type_id, name, code, quota, gender_restriction, is_active)
        SELECT roles.id, legacy.name, legacy.code,
            COALESCE(CAST(own_quota.setting_value AS DECIMAL(5,2)), legacy.quota),
            legacy.gender_restriction,
            legacy.is_active = 1 AND (
                own_quota.id IS NOT NULL OR NOT EXISTS (
                    SELECT 1 FROM teacher_settings assigned
                    JOIN user_type assigned_role
                        ON assigned.setting_key = CONCAT('leave_quota_', assigned_role.id, '_', legacy.id)
                    WHERE assigned.setting_group = 'leave'
                )
            )
        FROM teacher_leave_types legacy
        CROSS JOIN user_type roles
        LEFT JOIN teacher_settings own_quota
            ON own_quota.setting_key = CONCAT('leave_quota_', roles.id, '_', legacy.id)
            AND own_quota.setting_group = 'leave'
        WHERE legacy.user_type_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM teacher_leave_types existing
                WHERE existing.user_type_id = roles.id
                    AND existing.name = legacy.name AND existing.code = legacy.code);

        UPDATE teacher_leave_applications application
        JOIN `user` account ON account.id = application.user_id
        JOIN teacher_leave_types legacy ON legacy.id = application.leave_type_id AND legacy.user_type_id IS NULL
        JOIN teacher_leave_types scoped ON scoped.user_type_id = account.user_type
            AND scoped.name = legacy.name AND scoped.code = legacy.code
        SET application.leave_type_id = scoped.id;

        UPDATE teacher_leave_history history
        JOIN `user` account ON account.id = history.user_id
        JOIN teacher_leave_types legacy ON legacy.id = history.leave_type_id AND legacy.user_type_id IS NULL
        JOIN teacher_leave_types scoped ON scoped.user_type_id = account.user_type
            AND scoped.name = legacy.name AND scoped.code = legacy.code
        SET history.leave_type_id = scoped.id;

        INSERT INTO teacher_settings (setting_key, setting_value, setting_group, description)
        VALUES ('leave_user_type_records_v1', '1', 'migration', 'Leave entries scoped to user types');
    END IF;
    COMMIT;
    DO RELEASE_LOCK(CONCAT(DATABASE(), '_leave_roles_v1'));
END$$
DELIMITER ;

CALL teacher_portal_apply_all_updates();
DROP PROCEDURE teacher_portal_apply_all_updates;
