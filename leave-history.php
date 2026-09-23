<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

$db = new DatabaseService();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = isAdminUser();

if (isset($_GET['user_id'])) {
    $requestedUserId = filter_var($_GET['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($requestedUserId === false) {
        http_response_code(400);
        exit('Invalid user ID.');
    }
    if (!$isAdmin && $requestedUserId !== $userId) {
        http_response_code(403);
        exit('Access denied.');
    }
    $userId = $requestedUserId;
}
$selectedUser = $db->fetchOne(
    'SELECT id, fname, lname, user_type, gender FROM user WHERE id = ?',
    [$userId]
);
if (!$selectedUser) {
    http_response_code(404);
    exit('User not found.');
}
$applications = $db->fetchAll(
    'SELECT l.*, t.name AS leave_type FROM teacher_leave_applications l LEFT JOIN teacher_leave_types t ON t.id = l.leave_type_id WHERE l.user_id = ? ORDER BY l.created_at DESC',
    [$userId]
);
$types = $db->fetchAll(
    'SELECT id, name, user_type_id, quota, gender_restriction, is_active FROM teacher_leave_types ORDER BY name, id'
);
$overview = (new LeaveOverviewService())->summarize([$selectedUser], $types, $applications);
$totals = ['quota' => 0.0, 'taken' => 0.0, 'pending' => 0.0, 'remaining' => 0.0];
foreach ($overview as $entry) {
    foreach ($totals as $key => $value) {
        $totals[$key] += $entry['totals'][$key];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave History | NNV-Teachers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
<main id="portal-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Leave Application</h2>
            <p class="text-muted mb-0"><?= e(getUserFullName($selectedUser)) ?> &middot; User #<?= $userId ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
    </div>

    <section class="mb-4" aria-labelledby="leave-overview-heading">
        <h3 id="leave-overview-heading" class="h5 mb-3">Leave Overview <small class="text-muted fw-normal">(All time, days)</small></h3>
        <div class="row g-3 mb-4">
            <?php foreach (['quota' => 'Current Quota', 'taken' => 'Taken (Approved)', 'pending' => 'Pending', 'remaining' => 'Remaining'] as $key => $label): ?>
                <div class="col-md-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small"><?= e($label) ?></div>
                        <div class="fs-4 fw-bold mt-2"><?= number_format($totals[$key], 2) ?></div>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr>
                    <th scope="col">Leave Type</th><th scope="col">Quota</th>
                    <th scope="col">Taken (Approved)</th><th scope="col">Pending</th><th scope="col">Remaining</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($overview as $entry): ?>
                        <?php foreach ($entry['balances'] as $balance): ?>
                            <tr>
                                <td><?= e($balance['name']) ?><?php if ($balance['historical']): ?> <span class="badge bg-secondary">Historical</span><?php endif; ?></td>
                                <?php foreach (array_keys($totals) as $key): ?>
                                    <td class="<?= $key === 'remaining' ? 'fw-bold' : '' ?>"><?= number_format($balance[$key], 2) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($isAdmin || !$entry['balances']): ?>
                            <tr class="fw-bold">
                                <td><?= $entry['balances'] ? 'Total' : 'No leave quota assigned' ?></td>
                                <?php foreach ($entry['totals'] as $value): ?><td><?= number_format($value, 2) ?></td><?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <h3 class="h5 mb-3">Applications</h3>
    <?php if (empty($applications)): ?>
        <div class="text-muted">No leave records found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
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
</main>
</body>
</html>
