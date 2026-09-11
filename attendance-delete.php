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

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    // allow delete by user_id + date
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $date = isset($_POST['date']) ? trim((string) $_POST['date']): '';
    if ($userId <= 0 || $date === '') {
        jsonResponse(false, 'Invalid parameters.');
    }
}

$db = new DatabaseService();

if ($id > 0) {
    $exists = $db->fetchOne('SELECT * FROM teacher_attendance WHERE id = ? LIMIT 1', [$id]);
    if (!$exists) {
        jsonResponse(false, 'Attendance record not found.');
    }
    $db->execute('DELETE FROM teacher_attendance WHERE id = ?', [$id]);
    jsonResponse(true, 'Attendance deleted successfully.');
}

$exists = $db->fetchOne('SELECT * FROM teacher_attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1', [$userId, $date]);
if (!$exists) {
    jsonResponse(false, 'Attendance record not found.');
}
$db->execute('DELETE FROM teacher_attendance WHERE user_id = ? AND attendance_date = ?', [$userId, $date]);
jsonResponse(true, 'Attendance deleted successfully.');
