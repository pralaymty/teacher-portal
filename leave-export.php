<?php

require_once __DIR__ . '/app/bootstrap.php';
requireAdmin();

$userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false || $userId === null) {
    http_response_code(400);
    exit('Invalid user ID.');
}
$db = new DatabaseService();
$user = $db->fetchOne(
    'SELECT id, fname, lname, email, designation FROM user WHERE id = ? AND user_type IN (3, 4, 5, 10, 11, 12)',
    [$userId]
);
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}
$applications = $db->fetchAll(
    'SELECT l.start_date, l.end_date, l.days_count, l.status, t.name AS leave_type
     FROM teacher_leave_applications l LEFT JOIN teacher_leave_types t ON t.id = l.leave_type_id
     WHERE l.user_id = ? AND l.status = ? ORDER BY l.start_date, l.end_date, l.id',
    [$userId, 'Approved']
);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="leave-register-' . $userId . '.csv"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$stream = fopen('php://output', 'wb');
(new LeaveCsvService())->write($stream, $user, $applications);
fclose($stream);
exit;
