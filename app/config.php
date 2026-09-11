<?php

declare(strict_types=1);

return [
    'app_name' => 'Teachers Employee Portal',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost/teacher-portal',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'nnv_admin',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'teacher_portal_session',
        'lifetime' => 3600,
    ],
    'auth' => [
        'allowed_user_types' => [1, 3, 6],
        'teacher_user_type' => 3,
        'master_admin_user_type' => 1,
        'developer_admin_user_type' => 6,
    ],
    'attendance' => [
        'entry_time' => '09:30',
    ],
    'timezone' => 'Asia/Kolkata',
];
