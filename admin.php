<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();
if (!isAdminUser()) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$db = new DatabaseService();
$teacherType = (int) (appConfig()['auth']['teacher_user_type'] ?? 3);
$teacherCount = (int) ($db->fetchOne('SELECT COUNT(*) AS total FROM user WHERE user_type = ?', [$teacherType])['total'] ?? 0);
$presentToday = (int) ($db->fetchOne('SELECT COUNT(*) AS total FROM teacher_attendance WHERE attendance_date = ?', [date('Y-m-d')])['total'] ?? 0);
$pendingLeave = (int) ($db->fetchOne('SELECT COUNT(*) AS total FROM teacher_leave_applications WHERE status = ?', ['Approval Pending'])['total'] ?? 0);
$approvedLeaves = (int) ($db->fetchOne('SELECT COUNT(*) AS total FROM teacher_leave_applications WHERE status = ?', ['Approved'])['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Teachers Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Admin Dashboard</h2>
            <p class="text-muted mb-0">Employee and leave overview</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Teacher Dashboard</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Total Teachers</small>
                    <div class="fw-bold fs-3 mt-2"><?= $teacherCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Today's Present</small>
                    <div class="fw-bold fs-3 mt-2"><?= $presentToday ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Pending Leaves</small>
                    <div class="fw-bold fs-3 mt-2"><?= $pendingLeave ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Approved Leaves</small>
                    <div class="fw-bold fs-3 mt-2"><?= $approvedLeaves ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3">Quick Links</h4>
            <div class="d-grid gap-2 d-md-flex">
                <a href="attendance.php" class="btn btn-outline-primary">Attendance</a>
                <a href="leave-admin.php" class="btn btn-outline-primary">Leave Applications</a>
                <a href="teachers.php" class="btn btn-outline-primary">Teachers</a>
                <a href="settings.php" class="btn btn-outline-primary">Settings</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
