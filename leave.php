<?php
require_once __DIR__ . '/app/bootstrap.php';
requireTeacher();

$db = new DatabaseService();
$userId = (int) $_SESSION['user_id'];
$applications = $db->fetchAll(
    'SELECT l.*, t.name AS leave_type FROM teacher_leave_applications l LEFT JOIN teacher_leave_types t ON t.id = l.leave_type_id WHERE l.user_id = ? ORDER BY l.created_at DESC',
    [$userId]
);
$leaveTypes = $db->fetchAll('SELECT * FROM teacher_leave_types WHERE is_active = 1 ORDER BY name ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security validation failed.'];
        redirect('leave.php');
    }

    $leaveTypeId = (int) ($_POST['leave_type_id'] ?? 0);
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate = trim((string) ($_POST['end_date'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $specialApproval = trim((string) ($_POST['special_approval_reason'] ?? ''));

    if ($leaveTypeId <= 0 || $startDate === '' || $endDate === '' || $reason === '') {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'All leave fields are required.'];
        redirect('leave.php');
    }

    $leaveType = $db->fetchOne('SELECT * FROM teacher_leave_types WHERE id = ? LIMIT 1', [$leaveTypeId]);
    if (!$leaveType) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Selected leave type is invalid.'];
        redirect('leave.php');
    }

    $gender = (string) ($db->fetchOne('SELECT gender FROM user WHERE id = ? LIMIT 1', [$userId])['gender'] ?? '');
    if ($leaveType['gender_restriction'] === 'Female' && strtolower($gender) !== 'female') {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Maternity leave is available only for female teachers.'];
        redirect('leave.php');
    }

    $days = (new DateTime($endDate))->diff(new DateTime($startDate))->days + 1;
    if ($days <= 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'End date must be after start date.'];
        redirect('leave.php');
    }

    if ($specialApproval !== '' && mb_strlen($specialApproval) > 200) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Special approval note must be 200 characters or less.'];
        redirect('leave.php');
    }

    $db->execute(
        'INSERT INTO teacher_leave_applications (user_id, leave_type_id, start_date, end_date, days_count, reason, special_approval_reason, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        [$userId, $leaveTypeId, $startDate, $endDate, $days, $reason, $specialApproval !== '' ? $specialApproval : null, 'Approval Pending']
    );

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Leave application submitted successfully.'];
    redirect('leave.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management | Teachers Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Leave Management</h2>
            <p class="text-muted mb-0">Apply and track leave applications</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
    </div>
    <?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Apply Leave</h4>
                    <form method="POST" action="leave.php" id="leaveForm">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Leave Type</label>
                                <select class="form-select" name="leave_type_id" required>
                                    <option value="">Select leave type</option>
                                    <?php foreach ($leaveTypes as $type): ?>
                                        <option value="<?= (int) $type['id'] ?>"><?= e($type['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" required></div>
                            <div class="col-md-4"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date" required></div>
                            <div class="col-md-12"><label class="form-label">Reason</label><textarea class="form-control" name="reason" rows="3" required></textarea></div>
                            <div class="col-md-12"><label class="form-label">Special Approval Explanation (max 200 chars)</label><textarea class="form-control" name="special_approval_reason" rows="2" maxlength="200"></textarea></div>
                        </div>
                        <div class="mt-4 text-end"><button type="submit" class="btn btn-primary">Submit Leave Application</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3">Leave History</h4>
            <?php if (empty($applications)): ?>
                <div class="text-muted">No leave applications found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>Leave Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th><th>Reason</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?= e($app['leave_type'] ?? 'N/A') ?></td>
                                    <td><?= e($app['start_date']) ?></td>
                                    <td><?= e($app['end_date']) ?></td>
                                    <td><?= (float) $app['days_count'] ?></td>
                                    <td><span class="badge bg-<?= $app['status'] === 'Approved' ? 'success' : ($app['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= e($app['status']) ?></span></td>
                                    <td><?= e($app['reason']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
