<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

$db = new DatabaseService();
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (isAdminUser()) {
    $applications = $db->fetchAll(
        'SELECT l.*, t.name AS leave_type, u.fname, u.lname FROM teacher_leave_applications l LEFT JOIN teacher_leave_types t ON t.id = l.leave_type_id LEFT JOIN user u ON u.id = l.user_id ORDER BY l.created_at DESC'
    );
} else {
    $applications = $db->fetchAll(
        'SELECT l.*, t.name AS leave_type FROM teacher_leave_applications l LEFT JOIN teacher_leave_types t ON t.id = l.leave_type_id WHERE l.user_id = ? ORDER BY l.created_at DESC',
        [$userId]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave History | Teachers Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Leave History</h2>
            <p class="text-muted mb-0">All leave applications</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
    </div>

    <?php if (empty($applications)): ?>
        <div class="text-muted">No leave records found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <?php if (isAdminUser()): ?><th>Teacher</th><?php endif; ?>
                        <th>Type</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Applied At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <?php if (isAdminUser()): ?><td><?= e(getUserFullName(['fname' => $app['fname'] ?? '', 'lname' => $app['lname'] ?? ''])) ?></td><?php endif; ?>
                            <td><?= e($app['leave_type'] ?? 'N/A') ?></td>
                            <td><?= e($app['start_date']) ?></td>
                            <td><?= e($app['end_date']) ?></td>
                            <td><?= (float) $app['days_count'] ?></td>
                            <td><span class="badge bg-<?= $app['status'] === 'Approved' ? 'success' : ($app['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= e($app['status']) ?></span></td>
                            <td><?= e($app['reason']) ?></td>
                            <td><?= e($app['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
