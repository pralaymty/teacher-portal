<?php

declare(strict_types=1);

class AttendanceNoteMigration
{
    /** Add optional notes while preserving existing attendance records. */
    public static function run(DatabaseService $db): void
    {
        $columns = array_column($db->fetchAll('SHOW COLUMNS FROM teacher_attendance'), 'Field');
        if (in_array('note', $columns, true)) {
            return;
        }
        $lock = $db->fetchOne("SELECT GET_LOCK(CONCAT(DATABASE(), '_leave_roles_v1'), 10) AS acquired");
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not lock attendance schema upgrade.');
        }
        try {
            $columns = array_column($db->fetchAll('SHOW COLUMNS FROM teacher_attendance'), 'Field');
            if (!in_array('note', $columns, true)
                && !$db->execute('ALTER TABLE teacher_attendance ADD COLUMN note VARCHAR(500) NULL')) {
                throw new RuntimeException('Could not add attendance note column.');
            }
        } finally {
            $db->fetchOne("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '_leave_roles_v1')) AS released");
        }
    }
}
