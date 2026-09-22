<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$config = appConfig()['db'];
$conn = new mysqli($config['host'], $config['user'], $config['pass'], '', (int) $config['port']);
$sql = file_get_contents(__DIR__ . '/../database/all_updates.sql');
// DELIMITER is a phpMyAdmin/mysql-client directive, not server SQL.
$serverSql = str_replace(['DELIMITER $$', 'DELIMITER ;', 'END$$'], ['', '', 'END;'], $sql);
$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function runSql(mysqli $conn, string $sql): void
{
    $conn->multi_query($sql);
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

foreach (['fresh', 'legacy'] as $scenario) {
    $testDatabase = 'teacher_portal_upgrade_test_' . bin2hex(random_bytes(6));
    $conn->query('CREATE DATABASE `' . $testDatabase . '`');
    try {
        $conn->select_db($testDatabase);
        $conn->query('CREATE TABLE user (id INT PRIMARY KEY, user_type INT) ENGINE=InnoDB');
        $conn->query('CREATE TABLE user_type (id INT PRIMARY KEY) ENGINE=InnoDB');
        $conn->query('INSERT INTO user_type VALUES (1), (3), (4), (5), (6), (10), (11), (12)');
        $conn->query('INSERT INTO user VALUES (1, 3), (2, 6)');
        if ($scenario === 'legacy') {
            runSql($conn, substr($sql, 0, strpos($sql, 'DELIMITER $$')));
            $conn->query("ALTER TABLE user ADD COLUMN gender ENUM('Male','Female','Other') NULL");
            $conn->query("UPDATE user SET gender = 'Female' WHERE id = 1");
            $conn->query('ALTER TABLE teacher_leave_types DROP INDEX uq_leave_type_role_code, DROP INDEX uq_leave_type_role_name, DROP COLUMN user_type_id, ADD UNIQUE KEY uq_leave_type_code (code), ADD UNIQUE KEY uq_leave_type_name (name)');
            $conn->query('ALTER TABLE teacher_attendance DROP COLUMN latitude, DROP COLUMN longitude, DROP COLUMN logoff_time, DROP COLUMN logoff_latitude, DROP COLUMN logoff_longitude');
            $conn->query("INSERT INTO teacher_leave_types (id, name, code, quota, gender_restriction, is_active) VALUES (1, 'Casual', 'CL', 12, 'All', 1), (2, 'Study', 'ST', 0, 'All', 1)");
            $conn->query("INSERT INTO teacher_settings (setting_key, setting_value, setting_group) VALUES ('leave_quota_3_2', '4', 'leave'), ('attendance_entry_start_time', '09:15', 'attendance'), ('settings_user_type_ids', '[3,6]', 'general'), ('school_location', '{\"latitude\":22,\"longitude\":88,\"radius_m\":100}', 'attendance')");
            $conn->query("INSERT INTO teacher_leave_applications (user_id, leave_type_id, start_date, end_date, days_count, reason, status) VALUES (1, 1, '2026-01-01', '2026-01-01', 1, 'Test', 'Approved')");
            $conn->query("INSERT INTO teacher_leave_history (user_id, leave_type_id, days_count, status) VALUES (1, 1, 1, 'Approved')");
        }

        runSql($conn, $serverSql);
        check((int) $conn->query("SELECT COUNT(*) FROM user WHERE gender = 'M'")->fetch_row()[0] === 2, 'Existing users not initialized to M.');
        check($conn->query("SHOW COLUMNS FROM user LIKE 'gender'")->fetch_assoc()['Type'] === "enum('M','F')", 'Gender schema incorrect.');
        check($conn->query("SHOW COLUMNS FROM teacher_attendance LIKE 'latitude'")->num_rows === 1, 'Latitude column missing.');
        check($conn->query("SHOW COLUMNS FROM teacher_attendance LIKE 'longitude'")->num_rows === 1, 'Longitude column missing.');
        foreach (['logoff_time', 'logoff_latitude', 'logoff_longitude'] as $column) {
            check($conn->query("SHOW COLUMNS FROM teacher_attendance LIKE '" . $column . "'")->num_rows === 1, 'Logoff column missing.');
        }
        check((int) $conn->query("SELECT COUNT(*) FROM teacher_settings WHERE setting_key = 'leave_user_type_records_v1' AND setting_group = 'migration'")->fetch_row()[0] === 1, 'Migration marker missing.');

        if ($scenario === 'legacy') {
            check((float) $conn->query("SELECT quota FROM teacher_leave_types WHERE user_type_id = 3 AND code = 'ST'")->fetch_row()[0] === 4.0, 'Role quota lost.');
            check((int) $conn->query("SELECT is_active FROM teacher_leave_types WHERE user_type_id = 6 AND code = 'ST'")->fetch_row()[0] === 0, 'Unassigned role activated.');
            check($conn->query("SELECT setting_value FROM teacher_settings WHERE setting_key = 'attendance_entry_start_time'")->fetch_row()[0] === '09:15', 'Saved entry window overwritten.');
            check($conn->query("SELECT setting_value FROM teacher_settings WHERE setting_key = 'settings_user_type_ids'")->fetch_row()[0] === '[3,6]', 'Saved dropdown selection overwritten.');
            check($conn->query("SELECT setting_value FROM teacher_settings WHERE setting_key = 'school_location'")->fetch_row()[0] === '{"latitude":22,"longitude":88,"radius_m":100}', 'School location overwritten.');
            foreach (['teacher_leave_applications', 'teacher_leave_history'] as $table) {
                check((int) $conn->query('SELECT t.user_type_id FROM ' . $table . ' l JOIN teacher_leave_types t ON t.id = l.leave_type_id')->fetch_row()[0] === 3, 'Historical reference not migrated.');
            }
        } else {
            check((int) $conn->query('SELECT COUNT(*) FROM teacher_leave_types WHERE user_type_id = 3')->fetch_row()[0] === 5, 'Fresh default leave entries missing.');
        }

        $conn->query("UPDATE user SET gender = 'F' WHERE id = 1");
        $conn->query("UPDATE teacher_leave_types SET quota = 7, is_active = 0 WHERE user_type_id = 3 AND code = 'CL'");
        $before = $conn->query('SELECT * FROM teacher_leave_types ORDER BY id')->fetch_all(MYSQLI_ASSOC);
        $conn->query("INSERT INTO teacher_attendance (user_id, attendance_date, attendance_time, logoff_time, logoff_latitude, logoff_longitude) VALUES (1, '2026-09-22', '10:30', '16:15', 22.5, 88.4)");
        runSql($conn, $serverSql);
        check($conn->query('SELECT logoff_time FROM teacher_attendance WHERE user_id = 1')->fetch_row()[0] === '16:15:00', 'Import changed logoff records.');
        check($conn->query('SELECT gender FROM user WHERE id = 1')->fetch_row()[0] === 'F', 'Reimport reset gender.');
        check($conn->query('SELECT * FROM teacher_leave_types ORDER BY id')->fetch_all(MYSQLI_ASSOC) === $before, 'Reimport changed leave data.');
        check((int) $conn->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'teacher_portal_apply_all_updates'")->fetch_row()[0] === 0, 'Temporary upgrade procedure not removed.');
    } finally {
        // Only the freshly generated test database is eligible for cleanup.
        if (!preg_match('/^teacher_portal_upgrade_test_[a-f0-9]{12}$/', $testDatabase)) {
            throw new RuntimeException('Unexpected test database name.');
        }
        $conn->query('DROP DATABASE `' . $testDatabase . '`');
    }
}
echo $checks . " consolidated SQL checks passed in isolated test databases.\n";
