<?php
require_once __DIR__ . '/app/bootstrap.php';
requireAdmin();

$db = new DatabaseService();
$teacherType = (int) (appConfig()['auth']['teacher_user_type'] ?? 3);
$teachers = $db->fetchAll('SELECT id, fname, lname, email FROM user WHERE user_type = ? ORDER BY lname, fname', [$teacherType]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
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
                    $leaveTaken = (float) ($db->fetchOne('SELECT COALESCE(SUM(days_count),0) AS total FROM teacher_leave_applications WHERE user_id = ? AND status = ?', [$id, 'Approved'])['total'] ?? 0);
                    // compute total quota from active leave types
                    $quota = (float) ($db->fetchOne('SELECT COALESCE(SUM(quota),0) AS total FROM teacher_leave_types WHERE is_active = 1')['total'] ?? 0);
                    $balance = max(0, $quota - $leaveTaken);
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
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
