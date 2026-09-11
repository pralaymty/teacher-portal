<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    jsonResponse(false, 'Security validation failed.');
}

if (!isAdminUser()) {
    jsonResponse(false, 'Admin required.');
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$date = trim((string) ($_POST['date'] ?? ''));
$time = trim((string) ($_POST['time'] ?? date('H:i:s')));

if ($userId <= 0 || $date === '') {
    jsonResponse(false, 'Invalid parameters.');
}

$db = new DatabaseService();
// Prevent duplicate entry for the same user/date
$exists = $db->fetchOne('SELECT id FROM teacher_attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1', [$userId, $date]);
if ($exists) {
    jsonResponse(false, 'Attendance already exists for this date.');
}

try {
    $db->execute('INSERT INTO teacher_attendance (user_id, attendance_date, attendance_time, created_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$userId, $date, $time]);
    jsonResponse(true, 'Attendance added successfully.');
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        jsonResponse(false, 'Attendance already exists for this date.');
    }
    jsonResponse(false, 'Database error: ' . $e->getMessage());
} catch (RuntimeException $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
