<?php
require_once __DIR__ . '/app/bootstrap.php';

$auth = new AuthService();
$auth->logout();
redirect('index.php');
