<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    jsonResponse(false, 'Security validation failed.');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0 || (!isTeacherUser() && !isAdminUser())) {
    jsonResponse(false, 'Teacher access required.');
}

$latitude = trim((string) ($_POST['latitude'] ?? ''));
$longitude = trim((string) ($_POST['longitude'] ?? ''));

if (!isValidLatitude($latitude) || !isValidLongitude($longitude)) {
    jsonResponse(false, 'Valid latitude and longitude are required.');
}

$db = new DatabaseService();
$currentDate = date('Y-m-d');

$exists = $db->fetchOne(
    'SELECT id FROM teacher_attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1',
    [$userId, $currentDate]
);

if ($exists) {
    jsonResponse(false, 'Attendance has already been marked for today.');
}

$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$db->execute(
    'INSERT INTO teacher_attendance (user_id, attendance_date, attendance_time, latitude, longitude, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
    [
        $userId,
        $currentDate,
        $now->format('H:i:s'),
        $latitude,
        $longitude,
    ]
);

jsonResponse(true, 'Attendance marked successfully.');
