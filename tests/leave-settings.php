<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/services/DatabaseService.php';
require __DIR__ . '/../app/services/LeaveSettingsService.php';
require __DIR__ . '/../app/services/LeaveSettingsMigration.php';

class LeaveTestDatabase extends DatabaseService
{
    public bool $failLeaveWrite = false;
    public bool $failMigration = false;

    public function execute(string $sql, array $params = []): bool
    {
        $result = parent::execute($sql, $params);
        if (($this->failLeaveWrite && strpos($sql, 'UPDATE teacher_leave_types SET') === 0)
            || ($this->failMigration && strpos($sql, 'INSERT INTO teacher_settings') === 0)) {
            throw new RuntimeException('Simulated write failure');
        }
        return $result;
    }
}

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function rejected(callable $operation): bool
{
    try {
        $operation();
        return false;
    } catch (InvalidArgumentException $error) {
        return true;
    }
}

$db = new LeaveTestDatabase();
// Temporary tables shadow live tables for this connection only; no live rows are changed.
foreach (['teacher_settings', 'teacher_leave_applications', 'teacher_leave_history'] as $table) {
    $definition = $db->fetchOne('SHOW CREATE TABLE ' . $table)['Create Table'];
    $db->execute(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $definition, 1));
}
$db->execute("CREATE TEMPORARY TABLE teacher_leave_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), code VARCHAR(30),
    quota DECIMAL(5,2), gender_restriction VARCHAR(10), is_active TINYINT,
    UNIQUE KEY uq_leave_type_name (name), UNIQUE KEY uq_leave_type_code (code)
) ENGINE=InnoDB");
$db->execute('CREATE TEMPORARY TABLE user_type (id INT PRIMARY KEY)');
$db->execute('CREATE TEMPORARY TABLE user (id INT PRIMARY KEY, user_type INT)');
$db->execute('INSERT INTO user_type (id) VALUES (1), (3), (6)');
$db->execute('INSERT INTO user (id, user_type) VALUES (1, 3), (2, 6)');
$db->execute("INSERT INTO teacher_leave_types VALUES (1, 'Casual', 'CL', 12, 'All', 1), (2, 'Study', 'ST', 0, 'All', 1)");
$db->execute("INSERT INTO teacher_settings (setting_key, setting_value, setting_group) VALUES ('leave_quota_3_2', '4', 'leave')");
$db->execute("INSERT INTO teacher_leave_applications (user_id, leave_type_id, start_date, end_date, days_count, reason, status) VALUES (1, 1, '2026-01-01', '2026-01-01', 1, 'Test', 'Approved'), (2, 1, '2026-01-01', '2026-01-01', 1, 'Test', 'Approved')");
$db->execute("INSERT INTO teacher_leave_history (user_id, leave_type_id, days_count, status) VALUES (1, 1, 1, 'Approved')");

$db->failMigration = true;
try {
    LeaveSettingsMigration::run($db);
    throw new LogicException('Expected migration failure.');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Simulated write failure', 'Unexpected migration error.');
}
$db->failMigration = false;
check((int) $db->fetchOne('SELECT COUNT(*) AS total FROM teacher_leave_types')['total'] === 2, 'Failed migration left copies behind.');
check((int) $db->fetchOne('SELECT leave_type_id FROM teacher_leave_applications WHERE user_id = 1')['leave_type_id'] === 1, 'Failed migration changed history.');
LeaveSettingsMigration::run($db);
$service = new LeaveSettingsService($db);
$teacherTypes = array_column($service->getTypes(3), null, 'code');
$developerTypes = array_column($service->getTypes(6), null, 'code');
$teacherId = (int) $teacherTypes['CL']['id'];
$developerId = (int) $developerTypes['CL']['id'];
check($teacherId !== $developerId, 'Roles still share the same leave record.');
check((float) $teacherTypes['CL']['quota'] === 12.0, 'Legacy quota lost.');
check((float) $teacherTypes['ST']['quota'] === 4.0, 'Explicit role quota lost.');
check(!isset($developerTypes['ST']), 'Old role-specific addition visible to other roles.');
check((int) $db->fetchOne('SELECT leave_type_id FROM teacher_leave_applications WHERE user_id = 1')['leave_type_id'] === $teacherId, 'Teacher history not migrated.');
check((int) $db->fetchOne('SELECT leave_type_id FROM teacher_leave_applications WHERE user_id = 2')['leave_type_id'] === $developerId, 'Developer history not migrated.');
check((int) $db->fetchOne('SELECT leave_type_id FROM teacher_leave_history WHERE user_id = 1')['leave_type_id'] === $teacherId, 'Leave ledger not migrated.');
LeaveSettingsMigration::run($db);
check((int) $db->fetchOne('SELECT COUNT(*) AS total FROM teacher_leave_types')['total'] === 8, 'Migration not idempotent.');

$input = ['name' => 'Casual', 'code' => 'CL', 'quota' => '8.5', 'gender' => 'All'];
$service->save($teacherId, 3, array_merge($input, ['name' => 'Teacher Casual', 'gender' => 'Male']));
$changed = array_column($service->getTypes(3), null, 'id')[$teacherId];
check((float) $changed['quota'] === 8.5 && $changed['name'] === 'Teacher Casual' && $changed['gender_restriction'] === 'Male', 'Role edit not saved.');
check(array_column($service->getTypes(6), null, 'code')['CL'] === $developerTypes['CL'], 'Editing changed another role.');
check(rejected(fn () => $service->save($teacherId, 6, $input)), 'Cross-role edit accepted.');
check(rejected(fn () => $service->delete($teacherId, 6)), 'Cross-role deletion accepted.');
check(rejected(fn () => $service->save(0, 3, $input)), 'Duplicate code within role accepted.');

$new = ['name' => 'Exam', 'code' => 'EX', 'quota' => '2', 'gender' => 'All'];
$service->save(0, 3, $new);
$newTeacherId = (int) array_column($service->getTypes(3), null, 'code')['EX']['id'];
check(!isset(array_column($service->getTypes(6), null, 'code')['EX']), 'Adding leaked to another role.');
$service->save(0, 6, array_merge($new, ['quota' => '5']));
$newDeveloper = array_column($service->getTypes(6), null, 'code')['EX'];
check((float) $newDeveloper['quota'] === 5.0 && (int) $newDeveloper['id'] !== $newTeacherId, 'Same name/code cannot have independent role records.');
$service->delete($newTeacherId, 3);
check(!isset(array_column($service->getTypes(3), null, 'code')['EX']), 'Deleted entry still visible.');
check(array_column($service->getTypes(6), null, 'code')['EX'] === $newDeveloper, 'Deletion changed another role.');
$service->save(0, 3, array_merge($new, ['quota' => '0']));
$restored = array_column($service->getTypes(3), null, 'code')['EX'];
check((int) $restored['id'] === $newTeacherId && (float) $restored['quota'] === 0.0, 'Restoration or zero quota failed.');
check(array_column($service->getTypes(6), null, 'code')['EX'] === $newDeveloper, 'Restoration changed another role.');

$db->failLeaveWrite = true;
try {
    $service->save($teacherId, 3, $input);
    throw new LogicException('Expected failed save.');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Simulated write failure', 'Unexpected save error.');
}
$db->failLeaveWrite = false;
check(array_column($service->getTypes(3), null, 'id')[$teacherId] === $changed, 'Failed save was not rolled back.');
foreach (['-1', '1000', 'abc', '1.234', '', []] as $quota) {
    check(rejected(fn () => $service->save($teacherId, 3, array_merge($input, ['quota' => $quota]))), 'Invalid quota accepted.');
}
check(rejected(fn () => $service->save(0, 999, $new)), 'Unknown role accepted.');
check(rejected(fn () => $service->save(9999, 3, $input)), 'Unknown leave entry accepted.');
check(rejected(fn () => $service->save($teacherId, 3, array_merge($input, ['gender' => 'invalid']))), 'Invalid gender accepted.');
$service->delete($teacherId, 3);
check((int) $db->fetchOne('SELECT leave_type_id FROM teacher_leave_applications WHERE user_id = 1')['leave_type_id'] === $teacherId, 'Deletion damaged past applications.');
check(isset(array_column($service->getTypes(6), null, 'id')[$developerId]), 'Deletion affected developer leave.');
check(rejected(fn () => $service->save($teacherId, 3, $input)), 'Deleted entry editable.');
echo $checks . " role-specific leave checks passed using temporary tables.\n";
