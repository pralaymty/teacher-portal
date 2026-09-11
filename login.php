<?php
require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
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
