<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/services/SchoolLocationService.php';

class DatabaseService
{
    public static ?string $value = null;

    public function fetchOne(string $sql, array $params): ?array
    {
        return self::$value === null ? null : ['setting_value' => self::$value];
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

$school = SchoolLocationService::validate(['latitude' => '0', 'longitude' => '0', 'radius_m' => '50']);
check(SchoolLocationService::distance(0, 0, 0, 0) === 0.0, 'Same point must have zero distance.');
check(abs(SchoolLocationService::distance(0, 0, 0, 1) - 111195.08) < 0.01, 'Known equatorial distance incorrect.');
check(abs(SchoolLocationService::distance(0, 179.999, 0, -179.999) - 222.39016) < 0.01, 'Dateline crossing incorrect.');
check(is_finite(SchoolLocationService::distance(0, 0, 0, 180)), 'Antipodal distance invalid.');
$inside = SchoolLocationService::assess('0', '0.0004', $school);
$outside = SchoolLocationService::assess('0', '0.0005', $school);
check($inside['outside'] === false && $inside['class'] === 'text-success', 'Inside radius must be green.');
check($outside['outside'] === true && $outside['class'] === 'text-danger fw-bold', 'Outside radius must be bold red.');
$boundary = SchoolLocationService::distance(0, 0, 0, 0.0005);
check(SchoolLocationService::assess('0', '0.0005', array_merge($school, ['radius_m' => $boundary]))['outside'] === false, 'Exact boundary must be allowed.');
check(SchoolLocationService::assess('0', '0', array_merge($school, ['radius_m' => 0.0]))['outside'] === false, 'Zero radius should allow the same point.');
check(SchoolLocationService::assess(null, null, $school)['distance_m'] === null, 'Missing coordinates treated as zero.');
check(SchoolLocationService::assess('', '', $school)['outside'] === null, 'Empty coordinates classified.');
check(SchoolLocationService::assess('invalid', '0', $school)['outside'] === null, 'Invalid coordinates classified.');
check(SchoolLocationService::assess('0', '0', null)['label'] === 'School location not configured', 'Missing school configuration not identified.');
foreach (['abc', '91', '-91', 'INF', 'NaN', '1e999', ''] as $value) {
    check(!isValidLatitude($value), 'Invalid latitude accepted: ' . $value);
}
foreach (['abc', '181', '-181', 'INF', ''] as $value) {
    check(!isValidLongitude($value), 'Invalid longitude accepted: ' . $value);
}
foreach ([['latitude' => []], ['latitude' => 'abc'], ['latitude' => '91'], ['longitude' => '-181'], ['radius_m' => '-1'], ['radius_m' => 'NaN'], ['radius_m' => true]] as $invalid) {
    try {
        SchoolLocationService::validate(array_merge($school, $invalid));
        throw new RuntimeException('Invalid school settings accepted.');
    } catch (InvalidArgumentException $error) {
        $checks++;
    }
}
check(SchoolLocationService::settings() === null, 'Missing settings should remain unconfigured.');
DatabaseService::$value = json_encode($school);
check(SchoolLocationService::settings() === $school, 'Saved settings not loaded correctly.');
DatabaseService::$value = '{broken';
check(SchoolLocationService::settings() === null, 'Broken settings should remain unconfigured.');

foreach ([$inside, $outside, SchoolLocationService::assess(null, null, $school)] as $status) {
    $attendanceEntry = ['time' => '10:35:00', 'location' => $status];
    ob_start();
    require __DIR__ . '/../app/views/attendance-location.php';
    $html = ob_get_clean();
    check(strpos($html, 'attendance-time small ' . $status['class']) !== false, 'Entry time has wrong style.');
    check(strpos($html, '10:35 AM') !== false, 'Entry time missing.');
    check(strpos($html, $status['label']) !== false, 'Accessible status label missing.');
    check(strpos($html, 'from school') === false && strpos($html, '<br>') === false, 'Old verbose location display remains.');
}
foreach ([[60, false, 'Inside Range (60 M)'], [92700, true, 'Outside Range (92.7 KM)'], [999, true, 'Outside Range (999 M)'], [999.1, true, 'Outside Range (1.0 KM)']] as [$distance, $isOutside, $expected]) {
    $attendanceEntry = ['time' => '10:35:00', 'location' => [
        'class' => $isOutside ? 'text-danger fw-bold' : 'text-success',
        'label' => $isOutside ? 'Outside Range' : 'Inside Range',
        'distance_m' => $distance,
    ]];
    ob_start();
    require __DIR__ . '/../app/views/attendance-location.php';
    $html = ob_get_clean();
    check(strpos(preg_replace('/\s+/', ' ', strip_tags($html)), $expected) !== false, 'Incorrect distance format: ' . $expected);
}
echo $checks . " attendance location checks passed.\n";
$attendanceEntry = ['time' => '10:35:00', 'location' => $inside,
    'logoff' => ['time' => '16:15:00', 'location' => $outside]];
ob_start();
require __DIR__ . '/../app/views/attendance-location.php';
$html = ob_get_clean();
check(substr_count($html, 'attendance-event ') === 2, 'Login and logoff must both render.');
check(strpos($html, '>Login</div>') !== false && strpos($html, '>Logoff</div>') !== false, 'Event labels missing.');
check(strpos($html, '04:15 PM') !== false, 'Logoff time missing.');
check(strpos($html, 'text-danger fw-bold') !== false && strpos($html, 'text-success') !== false, 'Event locations must be assessed independently.');
echo "4 calendar logoff checks passed.\n";
