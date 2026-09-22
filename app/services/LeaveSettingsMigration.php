<?php

declare(strict_types=1);

class LeaveSettingsMigration
{
    private const KEY = 'leave_user_type_records_v1';

    /** Convert shared leave types once, keeping legacy rows and historical references. */
    public static function run(DatabaseService $db): void
    {
        if (self::complete($db)) {
            return;
        }
        $lock = $db->fetchOne("SELECT GET_LOCK(CONCAT(DATABASE(), '_leave_roles_v1'), 10) AS acquired");
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not lock the leave settings migration.');
        }
        try {
            if (self::complete($db)) {
                return;
            }
            self::updateSchema($db);
            $db->transaction(function () use ($db): void {
                $roles = $db->fetchAll('SELECT id FROM user_type');
                $legacyTypes = $db->fetchAll('SELECT id, name, code, quota, gender_restriction, is_active FROM teacher_leave_types WHERE user_type_id IS NULL');
                foreach ($legacyTypes as $type) {
                    $overrides = [];
                    foreach ($roles as $role) {
                        $roleId = (int) $role['id'];
                        $setting = $db->fetchOne("SELECT setting_value FROM teacher_settings WHERE setting_group = 'leave' AND setting_key = ?", ['leave_quota_' . $roleId . '_' . $type['id']]);
                        if ($setting !== null) {
                            $overrides[$roleId] = $setting['setting_value'];
                        }
                    }
                    foreach ($roles as $role) {
                        $roleId = (int) $role['id'];
                        // Explicit old assignments identify the intended roles; unassigned copies stay inactive.
                        $active = (int) $type['is_active'] && ($overrides === [] || array_key_exists($roleId, $overrides));
                        self::write($db,
                            'INSERT INTO teacher_leave_types (user_type_id, name, code, quota, gender_restriction, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                            [$roleId, $type['name'], $type['code'], $overrides[$roleId] ?? $type['quota'], $type['gender_restriction'], $active]
                        );
                        $newId = $db->getLastInsertId();
                        foreach (['teacher_leave_applications', 'teacher_leave_history'] as $table) {
                            self::write($db,
                                'UPDATE ' . $table . ' l JOIN user u ON u.id = l.user_id SET l.leave_type_id = ? WHERE l.leave_type_id = ? AND u.user_type = ?',
                                [$newId, (int) $type['id'], $roleId]
                            );
                        }
                    }
                }
                self::write($db, "INSERT INTO teacher_settings (setting_key, setting_value, setting_group, description) VALUES (?, '1', 'migration', 'Leave entries scoped to user types')", [self::KEY]);
            });
        } finally {
            $db->fetchOne("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '_leave_roles_v1')) AS released");
        }
    }

    private static function complete(DatabaseService $db): bool
    {
        return $db->fetchOne("SELECT setting_value FROM teacher_settings WHERE setting_key = ? AND setting_group = 'migration'", [self::KEY]) !== null;
    }

    private static function updateSchema(DatabaseService $db): void
    {
        $columns = array_column($db->fetchAll('SHOW COLUMNS FROM teacher_leave_types'), 'Field');
        if (!in_array('user_type_id', $columns, true)) {
            self::write($db, 'ALTER TABLE teacher_leave_types ADD COLUMN user_type_id INT NULL AFTER id');
        }
        $indexes = array_column($db->fetchAll('SHOW INDEX FROM teacher_leave_types'), 'Key_name');
        foreach (['uq_leave_type_code', 'uq_leave_type_name'] as $index) {
            if (in_array($index, $indexes, true)) {
                self::write($db, 'ALTER TABLE teacher_leave_types DROP INDEX ' . $index);
            }
        }
        foreach (['code', 'name'] as $field) {
            if (!in_array('uq_leave_type_role_' . $field, $indexes, true)) {
                self::write($db, 'ALTER TABLE teacher_leave_types ADD UNIQUE KEY uq_leave_type_role_' . $field . ' (user_type_id, ' . $field . ')');
            }
        }
    }

    private static function write(DatabaseService $db, string $sql, array $params = []): void
    {
        if (!$db->execute($sql, $params)) {
            throw new RuntimeException('Could not migrate leave settings.');
        }
    }
}
