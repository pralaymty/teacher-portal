<?php
require_once __DIR__ . '/app/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('index.php');
    }

    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security validation failed.'];
        redirect('index.php');
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email and password are required.'];
        redirect('index.php');
    }

    $db = new DatabaseService();
    $auth = new AuthService($db);
    $result = $auth->login($email, $password);

    if (!$result['success']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
        redirect('index.php');
    }

    $user = $result['user'];
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Welcome back!'];
    redirect('dashboard.php');
} catch (Throwable $e) {
    // write detailed error info to a local log for live-server debugging (avoid logging passwords)
    $logDir = __DIR__ . '/app/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $logFile = $logDir . '/login_errors.log';
    $loggedEmail = isset($email) && $email !== '' ? $email : (string) ($_POST['email'] ?? '');
    $entry = sprintf(
        "%s | IP=%s | URI=%s | Email=%s | Exception=%s in %s:%d\n%s\n\n",
        date('Y-m-d H:i:s'),
        getClientIpAddress(),
        $_SERVER['REQUEST_URI'] ?? '',
        $loggedEmail,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );

    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    error_log('Login error logged to ' . $logFile . ': ' . $e->getMessage());

    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'An unexpected error occurred. Please try again later.'];
    redirect('index.php');
}
