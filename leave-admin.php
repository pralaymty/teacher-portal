<?php
require_once __DIR__ . '/app/bootstrap.php';
requireAdmin();

$db = new DatabaseService();
$applications = $db->fetchAll('SELECT l.*, t.name AS leave_type, u.fname, u.lname FROM teacher_leave_applications l LEFT JOIN teacher_leave_types t ON t.id = l.leave_type_id LEFT JOIN user u ON u.id = l.user_id ORDER BY l.created_at DESC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Applications | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
<main id="portal-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Leave Applications</h2>
            <p class="text-muted mb-0">Approve or reject leave applications</p>
        </div>
        <a href="admin.php" class="btn btn-outline-secondary">Back</a>
    </div>

    <?php if (empty($applications)): ?>
        <div class="text-muted">No applications found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Teacher</th><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th><th>Applied At</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= e(getUserFullName(['fname' => $app['fname'] ?? '', 'lname' => $app['lname'] ?? ''])) ?></td>
                            <td><?= e($app['leave_type'] ?? 'N/A') ?></td>
                            <td><?= e($app['start_date']) ?></td>
                            <td><?= e($app['end_date']) ?></td>
                            <td><?= (float) $app['days_count'] ?></td>
                            <td><span class="badge bg-<?= $app['status'] === 'Approved' ? 'success' : ($app['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= e($app['status']) ?></span></td>
                            <td><?= e($app['created_at']) ?></td>
                            <td>
                                <?php if ($app['status'] === 'Approval Pending'): ?>
                                    <button class="btn btn-sm btn-success action-leave" data-id="<?= (int) $app['id'] ?>" data-action="approve">Approve</button>
                                    <button class="btn btn-sm btn-danger action-leave" data-id="<?= (int) $app['id'] ?>" data-action="reject">Reject</button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $('.action-leave').on('click', function () {
        const btn = $(this);
        const id = btn.data('id');
        const action = btn.data('action');
        if (!confirm('Are you sure?')) return;
        btn.prop('disabled', true).text(action === 'approve' ? 'Approving...' : 'Rejecting...');
        $.post('leave-action.php', { id: id, action: action, csrf_token: '<?= e($_SESSION['csrf_token'] ?? '') ?>' }, function (resp) {
            if (resp && resp.success) {
                alert(resp.message);
                location.reload();
            } else {
                alert(resp && resp.message ? resp.message : 'Failed');
                btn.prop('disabled', false).text(action === 'approve' ? 'Approve' : 'Reject');
            }
        }, 'json').fail(function () { alert('Request failed'); btn.prop('disabled', false); });
    });
</script>
</body>
</html>
