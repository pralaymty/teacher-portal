<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

$db = new DatabaseService();
$userId = (int) $_SESSION['user_id'];
$userType = (int) ($_SESSION['user_type'] ?? 0);
$currentDate = date('Y-m-d');

$user = $db->fetchOne('SELECT * FROM user WHERE id = ? LIMIT 1', [$userId]);
$teacherName = getUserFullName($user ?? []);

$attendanceToday = $db->fetchOne(
    'SELECT * FROM teacher_attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1',
    [$userId, $currentDate]
);
$attendanceOpenNow = isAttendanceOpenNow();
$attendanceWindowMessage = getAttendanceWindowMessage();
$hasLogoff = $attendanceToday && $attendanceToday['logoff_time'] !== null;
$showLogoff = $attendanceToday && !$hasLogoff;
$logoffOpenNow = isAttendanceLogoffOpenNow();
$attendanceActionEnabled = $attendanceToday ? ($showLogoff && $logoffOpenNow) : $attendanceOpenNow;

$presentThisMonth = (int) $db->fetchOne(
    'SELECT COUNT(*) AS total FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?',
    [$userId, date('Y-m-01'), date('Y-m-t')]
)['total'];

$leaveQuota = (new LeaveSettingsService($db))->getTypes($userType);
$leaveUsed = 0.0;
$leavePending = 0.0;
$leaveApproved = 0.0;
$leaveBalance = 0.0;

foreach ($leaveQuota as $type) {
    $typeId = (int) $type['id'];
    $quota = (float) $type['quota'];
    $approved = (float) $db->fetchOne(
        'SELECT COALESCE(SUM(days_count),0) AS total FROM teacher_leave_applications WHERE user_id = ? AND leave_type_id = ? AND status = ? ',
        [$userId, $typeId, 'Approved']
    )['total'];
    $leaveUsed += $approved;
    $leaveApproved += $approved;
    $pending = (float) $db->fetchOne(
        'SELECT COALESCE(SUM(days_count),0) AS total FROM teacher_leave_applications WHERE user_id = ? AND leave_type_id = ? AND status = ? ',
        [$userId, $typeId, 'Approval Pending']
    )['total'];
    $leavePending += $pending;
    $leaveBalance += max(0, $quota - $approved);
}

$notifications = $db->fetchAll(
    'SELECT * FROM teacher_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NNV-Teachers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
    <main id="portal-content" class="container py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-1">Welcome, <?= e($teacherName) ?></h2>
                            <p class="text-muted mb-0">Your employee portal overview</p>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">Today</div>
                            <div class="fw-semibold"><?= date('d M Y') ?></div>
                        </div>
                    </div>

                    <?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                    <?php endif; ?>

                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card card-stat h-100">
                                <div class="card-body">
                                    <small class="text-muted">Today</small>
                                    <div class="d-flex align-items-center justify-content-between mt-2">
                                        <div id="todayStatusText" class="fw-bold fs-4"><?= $attendanceToday ? 'Present' : 'Not Marked' ?></div>
                                        <span id="todayStatusBadge" class="badge bg-<?= $attendanceToday ? 'success' : 'secondary' ?> status-pill"><i class="bi bi-check-circle"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-stat h-100">
                                <div class="card-body">
                                    <small class="text-muted">Present Days</small>
                                    <div id="presentCount" class="fw-bold fs-4 mt-2"><?= $presentThisMonth ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-stat h-100">
                                <div class="card-body">
                                    <small class="text-muted">Leave Balance</small>
                                    <div class="fw-bold fs-4 mt-2"><?= number_format($leaveBalance, 1) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-stat h-100">
                                <div class="card-body">
                                    <small class="text-muted">Pending Leave</small>
                                    <div class="fw-bold fs-4 mt-2"><?= number_format($leavePending, 1) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-8">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="fw-bold mb-0">Attendance</h5>
                                        <span id="attendanceSectionBadge" class="badge bg-<?= $attendanceToday ? 'success' : 'warning' ?> status-pill"><?= $attendanceToday ? 'Present' : 'Not Marked' ?></span>
                                    </div>
                                    <div class="text-center py-4">
                                        <button type="button" id="markAttendanceBtn" class="btn btn-<?= $showLogoff ? 'outline-danger' : 'success' ?> btn-lg present-button w-100" data-action="<?= $showLogoff ? 'logoff' : 'mark' ?>" <?= !$attendanceActionEnabled ? 'disabled' : '' ?>>
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                            <i class="bi <?= $showLogoff ? 'bi-box-arrow-right' : 'bi-check2-circle' ?> me-2 icon-check"></i>
                                            <span class="btn-text"><?= $hasLogoff ? 'Logged Off Today' : ($showLogoff ? 'Logoff' : ($attendanceOpenNow ? 'Mark Present' : 'Attendance Closed')) ?></span>
                                        </button>
                                        <?php if ($attendanceToday): ?>
                                            <div class="small text-muted mt-2">Login: <?= e(formatTime($attendanceToday['attendance_time'])) ?><?php if ($hasLogoff): ?> &middot; Logoff: <?= e(formatTime($attendanceToday['logoff_time'])) ?><?php endif; ?></div>
                                        <?php endif; ?>
                                        <?php if ($showLogoff && !$logoffOpenNow): ?>
                                            <div class="text-muted small mt-3"><?= e(getAttendanceLogoffWindowMessage()) ?></div>
                                        <?php endif; ?>
                                        <?php if (!$attendanceToday && !$attendanceOpenNow): ?>
                                            <div class="text-muted small mt-3"><?= e($attendanceWindowMessage) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body p-4">
                                    <h5 class="fw-bold mb-3">Quick Actions</h5>
                                    <div class="d-grid gap-2">
                                        <a href="attendance.php" class="btn btn-outline-primary"><i class="bi bi-calendar-check me-2"></i>Mark Attendance</a>
                                        <a href="attendance-calendar.php" class="btn btn-outline-primary"><i class="bi bi-calendar3 me-2"></i>Attendance Calendar</a>
                                        <a href="leave.php" class="btn btn-outline-primary"><i class="bi bi-file-earmark-plus me-2"></i>Apply Leave</a>
                                        <a href="leave-history.php" class="btn btn-outline-primary"><i class="bi bi-clock-history me-2"></i>Leave History</a>
                                        <a href="profile.php" class="btn btn-outline-primary"><i class="bi bi-person-circle me-2"></i>My Profile</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Notifications</h5>
                            <?php if (empty($notifications)): ?>
                                <div class="text-muted">No notifications found.</div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="border rounded p-3 mb-2">
                                        <div class="fw-semibold"><?= e($notification['title']) ?></div>
                                        <div class="small text-muted"><?= e($notification['message']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php require __DIR__ . '/app/views/attendance-action-script.php'; ?>
</body>
</html>
