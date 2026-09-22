<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    jsonResponse(false, 'Security validation failed.');
}

if (!isSuperAdminUser()) {
    jsonResponse(false, 'Only the super admin can add attendance manually.');
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$date = trim((string) ($_POST['date'] ?? ''));
$time = trim((string) ($_POST['time'] ?? date('H:i:s')));

if ($userId <= 0 || $date === '') {
    jsonResponse(false, 'Invalid parameters.');
}

$latitude = is_string($_POST['latitude'] ?? null) ? trim($_POST['latitude']) : '';
$longitude = is_string($_POST['longitude'] ?? null) ? trim($_POST['longitude']) : '';
if (!isValidLatitude($latitude) || !isValidLongitude($longitude)) {
    jsonResponse(false, 'Your current location is required to add attendance. Please allow location access and try again.');
}

$db = new DatabaseService();
// Prevent duplicate entry for the same user/date
$exists = $db->fetchOne('SELECT id FROM teacher_attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1', [$userId, $date]);
if ($exists) {
    jsonResponse(false, 'Attendance already exists for this date.');
}

try {
    $db->execute('INSERT INTO teacher_attendance (user_id, attendance_date, attendance_time, latitude, longitude, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$userId, $date, $time, $latitude, $longitude]);
    jsonResponse(true, 'Attendance added successfully.');
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        jsonResponse(false, 'Attendance already exists for this date.');
    }
    jsonResponse(false, 'Database error: ' . $e->getMessage());
} catch (RuntimeException $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
