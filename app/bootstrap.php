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

ensurePortalSchema();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
