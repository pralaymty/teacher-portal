const { spawnSync } = require('node:child_process');

const cases = [
    { name: 'omitted', note: null, saved: null },
    { name: 'blank', note: '   ', saved: null },
    { name: 'past date', note: '  Approved correction  ', saved: 'Approved correction', date: '2020-01-01' },
    { name: 'today', note: 'Late arrival', saved: 'Late arrival' },
    { name: 'maximum length', note: 'a'.repeat(500), saved: 'a'.repeat(500) },
    { name: 'too long', note: 'a'.repeat(501), success: false },
    { name: 'array rejected', note: ['invalid'], success: false },
    { name: 'permission retained', note: 'test', role: 3, success: false },
    { name: 'CSRF retained', note: 'test', csrf: 'wrong', success: false },
];

for (const scenario of cases) {
    const test = { success: true, role: 1, csrf: 'test', ...scenario };
    const payload = Buffer.from(JSON.stringify(test)).toString('base64');
    const php = `<?php
    require 'app/helpers.php';
    $test = json_decode(base64_decode('${payload}'), true);
    $_SESSION = ['is_logged_in'=>true, 'user_id'=>1, 'user_type'=>$test['role'], 'csrf_token'=>'test'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token'=>$test['csrf'], 'user_id'=>2, 'date'=>$test['date'] ?? date('Y-m-d'), 'time'=>'10:00', 'latitude'=>'0', 'longitude'=>'0'];
    if ($test['note'] !== null) { $_POST['note'] = $test['note']; }
    class DatabaseService {
        public static array $writes = [];
        public function fetchOne(string $sql, array $params = []): ?array { return null; }
        public function execute(string $sql, array $params = []): bool {
            if (strpos($sql, 'longitude, note,') === false) { throw new LogicException('Note column missing'); }
            self::$writes[] = $params;
            return true;
        }
    }
    ob_start();
    register_shutdown_function(function () use ($test) {
        $response = json_decode(ob_get_clean(), true);
        $writes = DatabaseService::$writes;
        if (($response['success'] ?? null) !== $test['success'] || count($writes) !== ($test['success'] ? 1 : 0)) { exit(1); }
        if ($test['success'] && ($writes[0][5] !== $test['saved'] || $writes[0][0] !== 2 || $writes[0][1] !== $_POST['date'])) { exit(2); }
        echo 'PASS';
    });
    // Exercise the endpoint without connecting to the live database.
    eval('?>' . str_replace("require_once __DIR__ . '/app/bootstrap.php';", '', file_get_contents('attendance-add.php')));
    `;
    const result = spawnSync('php', [], { input: php, encoding: 'utf8' });
    if (result.status !== 0 || result.stdout !== 'PASS' || result.stderr) {
        throw new Error(`${test.name}: ${result.stdout} ${result.stderr}`);
    }
}
console.log(`${cases.length} attendance note endpoint checks passed.`);
