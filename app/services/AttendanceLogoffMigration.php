<?php

declare(strict_types=1);

class AttendanceLogoffMigration
{
    /** Add optional logoff data without changing existing attendance entries. */
    public static function run(DatabaseService $db): void
    {
        $definitions = ['logoff_time' => 'TIME', 'logoff_latitude' => 'DECIMAL(10,8)', 'logoff_longitude' => 'DECIMAL(11,8)'];
        $columns = array_column($db->fetchAll('SHOW COLUMNS FROM teacher_attendance'), 'Field');
        if (array_diff(array_keys($definitions), $columns) === []) {
            return;
        }
        $lock = $db->fetchOne("SELECT GET_LOCK(CONCAT(DATABASE(), '_leave_roles_v1'), 10) AS acquired");
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not lock attendance schema upgrade.');
        }
        try {
            $columns = array_column($db->fetchAll('SHOW COLUMNS FROM teacher_attendance'), 'Field');
            foreach ($definitions as $column => $type) {
                if (!in_array($column, $columns, true)
                    && !$db->execute('ALTER TABLE teacher_attendance ADD COLUMN ' . $column . ' ' . $type . ' NULL')) {
                    throw new RuntimeException('Could not add attendance logoff columns.');
                }
            }
        } finally {
            $db->fetchOne("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '_leave_roles_v1')) AS released");
        }
    }
}
