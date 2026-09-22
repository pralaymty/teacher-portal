const { spawnSync } = require('node:child_process');

const cases = [
    { name: 'inside exit window', expected: true },
    { name: 'outside school', inside: false, expected: false },
    { name: 'outside exit window', open: false, expected: false },
    { name: 'no attendance today', present: false, expected: false },
    { name: 'already logged off', duplicate: true, expected: false },
    { name: 'super admin exempt', role: 1, inside: false, open: false, expected: true },
    { name: 'super admin still needs login', role: 1, present: false, expected: false },
    { name: 'school not configured', configured: false, expected: false },
    { name: 'developer not exempt', role: 6, inside: false, expected: false },
    { name: 'CSRF required', csrf: false, expected: false },
    { name: 'coordinates required', coordinates: false, expected: false },
];

for (const scenario of cases) {
    const test = { role: 3, open: true, inside: true, present: true, duplicate: false, configured: true, csrf: true, coordinates: true, ...scenario };
    const php = `<?php
    require 'app/helpers.php';
    require 'app/services/SchoolLocationService.php';
    date_default_timezone_set('Asia/Kolkata');
    $_SESSION = ['is_logged_in'=>true, 'user_id'=>1, 'user_type'=>${test.role}, 'csrf_token'=>'test'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token'=>'${test.csrf ? 'test' : 'wrong'}', 'latitude'=>'${test.coordinates ? '0' : ''}', 'longitude'=>'${test.inside ? '0' : '1'}', 'user_id'=>'999', 'date'=>'2000-01-01'];
    $closed = (new DateTimeImmutable('now'))->modify('+12 hours')->format('H:i');
    $settings = ['attendance_exit_start_time'=>${test.open ? "'00:00'" : '$closed'}, 'attendance_exit_end_time'=>${test.open ? "'23:59'" : '$closed'}];
    ${test.configured ? "$settings['school_location'] = json_encode(['latitude'=>0,'longitude'=>0,'radius_m'=>50]);" : ''}
    class DatabaseService {
        public static int $writes = 0;
        public function fetchOne(string $sql, array $params = []): ?array {
            global $settings;
            if (isset($params['key'])) {
                return array_key_exists($params['key'], $settings) ? ['setting_value'=>$settings[$params['key']]] : null;
            }
            if ($params !== [1, date('Y-m-d')] || strpos($sql, 'FOR UPDATE') === false) {
                throw new LogicException('Logoff query did not lock the current user/date.');
            }
            return ${test.present ? "['id'=>7, 'attendance_time'=>'00:00:00', 'logoff_time'=>" + (test.duplicate ? "'16:00:00'" : 'null') + ']' : 'null'};
        }
        public function transaction(callable $operation): void { $operation(); }
        public function execute(string $sql, array $params = []): bool {
            if (strpos($sql, 'UPDATE teacher_attendance') !== 0 || $params[3] !== 7 || $params[4] !== 1 || strpos($sql, 'logoff_time IS NULL') === false) {
                throw new LogicException('Incorrect logoff write.');
            }
            self::$writes++;
            return true;
        }
    }
    ob_start();
    register_shutdown_function(function () {
        $response = json_decode(ob_get_clean(), true);
        if (($response['success'] ?? null) !== ${test.expected ? 'true' : 'false'} || DatabaseService::$writes !== ${test.expected ? 1 : 0}) {
            echo json_encode($response); exit(1);
        }
        echo 'PASS';
    });
    // Isolate the endpoint's dependencies so these policy checks never write live attendance.
    $source = str_replace("require_once __DIR__ . '/app/bootstrap.php';", '', file_get_contents('attendance-logoff.php'));
    eval('?>' . $source);
    `;
    const result = spawnSync('php', [], { input: php, encoding: 'utf8' });
    if (result.status !== 0 || result.stdout !== 'PASS' || result.stderr) {
        throw new Error(`${test.name}: ${result.stdout} ${result.stderr}`);
    }
}
console.log(`${cases.length} attendance logoff endpoint checks passed.`);
