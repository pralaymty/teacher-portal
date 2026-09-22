<?php

declare(strict_types=1);

// An in-memory settings store keeps these checks independent of the live database.
class DatabaseService
{
    public static array $settings = [];

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $key = $params['key'];
        return array_key_exists($key, self::$settings)
            ? ['setting_value' => self::$settings[$key]] : null;
    }
}

require __DIR__ . '/../app/helpers.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$_SESSION = ['user_type' => 3];
check(getAttendanceTimeRanges()['entry_start'] === '10:30', 'Default schedule missing.');
DatabaseService::$settings['attendance_entry_start_time'] = '09:45';
check(getAttendanceTimeRanges()['entry_start'] === '09:45', 'Existing general schedule must be preserved.');

DatabaseService::$settings['attendance_user_type_3'] = json_encode([
    'entry_start' => '10:30', 'entry_end' => '11:00',
    'exit_start' => '16:00', 'exit_end' => '16:30',
]);
DatabaseService::$settings['attendance_user_type_6'] = json_encode([
    'entry_start' => '08:00', 'entry_end' => '09:00',
    'exit_start' => '17:00', 'exit_end' => '18:00',
]);

foreach ([
    '00:00:00' => false, '10:29:59' => false, '10:30:00' => true,
    '11:00:59' => true, '11:01:00' => false, '15:59:59' => false,
    '16:00:00' => true, '16:30:59' => true, '16:31:00' => false,
] as $time => $expected) {
    $now = new DateTimeImmutable('2026-09-22 ' . $time, new DateTimeZone('Asia/Kolkata'));
    check(isAttendanceOpenNow($now) === $expected, 'Incorrect teacher boundary: ' . $time);
}
check(strpos(getAttendanceWindowMessage(), '10:30 AM') !== false, 'Teacher message uses the wrong schedule.');
$_SESSION['user_type'] = 6;
$morning = new DateTimeImmutable('2026-09-22 08:30', new DateTimeZone('Asia/Kolkata'));
check(isAttendanceOpenNow($morning), 'Developer schedule was not applied.');
check(strpos(getAttendanceWindowMessage(), '08:00 AM') !== false, 'Developer message uses the wrong schedule.');
$_SESSION['user_type'] = 3;
check(!isAttendanceOpenNow($morning), 'Schedules leaked across user types.');
check(getAttendanceTimeRanges(6)['entry_start'] === '08:00', 'Settings cannot load a different role.');
DatabaseService::$settings['attendance_user_type_3'] = '{invalid';
check(getAttendanceTimeRanges()['entry_start'] === '09:45', 'Malformed settings must fall back.');
foreach (['25:00', '12:60', '', 'invalid'] as $value) {
    check(normalizeTimeValue($value, '') === '', 'Invalid time accepted: ' . $value);
}
$_SESSION['user_type'] = 1;
foreach (['00:00', '08:30', '12:00', '23:59'] as $time) {
    check(isAttendanceOpenNow(new DateTimeImmutable('2026-09-22 ' . $time)), 'Super admin blocked at ' . $time);
}
echo $checks . " attendance schedule checks passed.\n";
$_SESSION['user_type'] = 3;
foreach (['10:30' => false, '15:59' => false, '16:00' => true, '16:30' => true, '16:31' => false] as $time => $expected) {
    check(isAttendanceLogoffOpenNow(new DateTimeImmutable('2026-09-22 ' . $time)) === $expected, 'Incorrect exit boundary: ' . $time);
}
$_SESSION['user_type'] = 1;
check(isAttendanceLogoffOpenNow(new DateTimeImmutable('2026-09-22 02:00')), 'Super admin logoff was restricted.');
echo "6 logoff schedule checks passed.\n";
