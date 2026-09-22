<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/bootstrap.php';
register_shutdown_function(static function (): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
});
$previewDb = new DatabaseService();
$previewUser = $previewDb->fetchOne('SELECT id FROM user WHERE user_type = 3 ORDER BY id LIMIT 1');
if (!$previewUser) {
    throw new RuntimeException('An existing teacher is required for the read-only page previews.');
}
$previewDirectory = sys_get_temp_dir() . '/nnv-portal-preview';
if (!is_dir($previewDirectory)) {
    mkdir($previewDirectory, 0700, true);
}
$previewPages = ['index', 'dashboard', 'admin', 'attendance', 'teacher-attendance', 'teachers', 'settings', 'profile', 'leave', 'leave-admin', 'leave-history'];
foreach ($previewPages as $previewPage) {
    $_SESSION['is_logged_in'] = $previewPage !== 'index';
    $_SESSION['user_id'] = (int) $previewUser['id'];
    $_SESSION['user_type'] = $previewPage === 'leave' ? 3 : 1;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = '/' . $previewPage . '.php';
    $_GET = ['user_id' => (int) $previewUser['id'], 'month' => date('Y-m')];
    ob_start();
    require __DIR__ . '/../' . $previewPage . '.php';
    $previewHtml = ob_get_clean();
    $previewHtml = str_replace('<head>', '<head><base href="http://localhost/teacher-portal/">', $previewHtml);
    // Static previews cannot submit authenticated actions or request geolocation.
    $previewHtml = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $previewHtml);
    $previewHtml = str_replace('</body>', '<script>window.addEventListener("load",()=>{document.body.dataset.viewportOverflow=String(document.documentElement.scrollWidth>innerWidth);document.body.dataset.logoLoaded=String([...document.images].every(i=>i.complete&&i.naturalWidth>0));});</script></body>', $previewHtml);
    $previewDom = new DOMDocument();
    libxml_use_internal_errors(true);
    $previewDom->loadHTML($previewHtml);
    $previewXpath = new DOMXPath($previewDom);
    if ($previewPage === 'leave') {
        if ($previewXpath->query('//section[@aria-labelledby="leave-overview-heading"]')->length !== 1) {
            throw new RuntimeException('Personal leave overview missing for a teacher.');
        }
        $previewLeaveTotals = $leaveOverview['totals'];
    }
    if ($previewPage === 'leave-history' && $totals !== $previewLeaveTotals) {
        throw new RuntimeException('Leave form and leave history totals differ for the same user.');
    }
    if ($previewPage !== 'index' && ($previewXpath->query('//main[@id="portal-content"]')->length !== 1 || $previewXpath->query('//nav[@aria-label="Main navigation"]')->length !== 1)) {
        throw new RuntimeException('Invalid shared layout on ' . $previewPage);
    }
    file_put_contents($previewDirectory . '/' . $previewPage . '.html', $previewHtml);
    echo 'Rendered ' . $previewPage . PHP_EOL;
}
echo 'Previews: ' . $previewDirectory . PHP_EOL;
