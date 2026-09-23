<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_name('teacher_portal_session');
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

foreach (glob(__DIR__ . '/services/*.php') as $file) {
    require_once $file;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (PHP_SAPI !== 'cli') {
    header('Cache-Control: no-store');
    $locationScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($locationScript, ['location-required.php', 'location-access.php'], true)) {
        return;
    }
    if ($locationScript !== 'logout.php' && !LocationAccessService::isVerified($_SESSION)) {
        $locationReturn = $_SERVER['REQUEST_METHOD'] === 'GET'
            ? $locationScript . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')
            : 'index.php';
        redirect('location-required.php?next=' . rawurlencode(LocationAccessService::returnPage($locationReturn)));
    }
}

try {
    ensurePortalSchema();
} catch (Throwable $e) {
    trigger_error('Error during schema ensure: ' . $e->getMessage(), E_USER_WARNING);
}
