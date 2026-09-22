<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    jsonResponse(false, 'Security validation failed.');
}
if (!isTeacherUser() && !isAdminUser()) {
    jsonResponse(false, 'Teacher access required.');
}
$latitude = is_string($_POST['latitude'] ?? null) ? trim($_POST['latitude']) : '';
$longitude = is_string($_POST['longitude'] ?? null) ? trim($_POST['longitude']) : '';
if (!isValidLatitude($latitude) || !isValidLongitude($longitude)) {
    jsonResponse(false, 'Your current location is required to log off attendance.');
}
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
if (!isAttendanceLogoffOpenNow($now)) {
    jsonResponse(false, getAttendanceLogoffWindowMessage());
}
$school = SchoolLocationService::settings();
$location = SchoolLocationService::assess($latitude, $longitude, $school);
if (!isSuperAdminUser() && $location['outside'] !== false) {
    jsonResponse(false, $school === null
        ? 'School location is not configured. Please contact the super admin.'
        : 'You must be within the school\'s allowed distance to log off attendance.');
}

$db = new DatabaseService();
$userId = (int) $_SESSION['user_id'];
try {
    $db->transaction(function () use ($db, $userId, $now, $latitude, $longitude): void {
        $attendance = $db->fetchOne(
            'SELECT id, attendance_time, logoff_time FROM teacher_attendance WHERE user_id = ? AND attendance_date = ? FOR UPDATE',
            [$userId, $now->format('Y-m-d')]
        );
        if (!$attendance) {
            throw new InvalidArgumentException('Mark attendance for today before logging off.');
        }
        if ($attendance['logoff_time'] !== null) {
            throw new InvalidArgumentException('Attendance has already been logged off for today.');
        }
        if ($now->format('H:i:s') < $attendance['attendance_time']) {
            throw new InvalidArgumentException('Logoff time cannot be earlier than today\'s login time.');
        }
        if (!$db->execute(
            'UPDATE teacher_attendance SET logoff_time = ?, logoff_latitude = ?, logoff_longitude = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND logoff_time IS NULL',
            [$now->format('H:i:s'), $latitude, $longitude, (int) $attendance['id'], $userId]
        )) {
            throw new RuntimeException('Could not save attendance logoff.');
        }
    });
} catch (InvalidArgumentException $error) {
    jsonResponse(false, $error->getMessage());
} catch (Throwable $error) {
    error_log('Attendance logoff failed: ' . $error->getMessage());
    jsonResponse(false, 'Unable to save logoff. Please try again.');
}
jsonResponse(true, 'Attendance logged off successfully.', ['location' => $location]);
