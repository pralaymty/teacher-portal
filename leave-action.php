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
action:
$action = trim((string) ($_POST['action'] ?? ''));
if ($id <= 0 || ($action !== 'approve' && $action !== 'reject')) {
    jsonResponse(false, 'Invalid parameters.');
}

$db = new DatabaseService();
$app = $db->fetchOne('SELECT * FROM teacher_leave_applications WHERE id = ? LIMIT 1', [$id]);
if (!$app) jsonResponse(false, 'Application not found.');

if ($action === 'approve') {
    $db->execute('UPDATE teacher_leave_applications SET status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?', ['Approved', (int) ($_SESSION['user_id'] ?? 0), $id]);
    jsonResponse(true, 'Leave approved.');
}

$db->execute('UPDATE teacher_leave_applications SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', ['Rejected', $id]);
jsonResponse(true, 'Leave rejected.');
