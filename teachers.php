<?php
require_once __DIR__ . '/app/bootstrap.php';
requireAdmin();

$db = new DatabaseService();
$includedUserTypes = [3, 4, 5, 10, 11, 12];
$userTypePlaceholders = implode(', ', array_fill(0, count($includedUserTypes), '?'));
$teachers = $db->fetchAll(
    "SELECT id, fname, lname, email, user_type, gender FROM user WHERE user_type IN ($userTypePlaceholders) ORDER BY lname, fname",
    $includedUserTypes
);
$leaveTypes = $db->fetchAll(
    'SELECT id, name, user_type_id, quota, gender_restriction, is_active FROM teacher_leave_types'
);
$leaveApplications = $db->fetchAll(
    "SELECT l.user_id, l.leave_type_id, l.status, SUM(l.days_count) AS days_count
     FROM teacher_leave_applications l
     INNER JOIN user u ON u.id = l.user_id
     WHERE u.user_type IN ($userTypePlaceholders) AND l.status = ?
     GROUP BY l.user_id, l.leave_type_id, l.status",
    array_merge($includedUserTypes, ['Approved'])
);
$teacherLeaveTotals = [];
foreach ((new LeaveOverviewService())->summarize($teachers, $leaveTypes, $leaveApplications) as $overview) {
    $teacherLeaveTotals[(int) $overview['user']['id']] = $overview['totals'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
<main id="portal-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Teachers</h2>
            <p class="text-muted mb-0">List of teachers and quick stats</p>
        </div>
        <a href="admin.php" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Present This Month</th><th>Leave Taken</th><th>Leave Balance</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($teachers as $t):
                    $id = (int) $t['id'];
                    $present = (int) ($db->fetchOne('SELECT COUNT(*) AS total FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?', [$id, date('Y-m-01'), date('Y-m-t')])['total'] ?? 0);
                    $leaveTaken = $teacherLeaveTotals[$id]['taken'];
                    $balance = $teacherLeaveTotals[$id]['remaining'];
                ?>
                    <tr>
                        <td><?= e(getUserFullName(['fname' => $t['fname'], 'lname' => $t['lname']])) ?></td>
                        <td><?= e($t['email']) ?></td>
                        <td><?= $present ?></td>
                        <td><?= number_format($leaveTaken, 1) ?></td>
                        <td><?= number_format($balance, 1) ?></td>
                        <td>
                            <a href="teacher-attendance.php?user_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">View Attendance</a>
                            <a href="leave-history.php?user_id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">View Leave</a>
                            <a href="leave-export.php?user_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">Download Leave CSV</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
