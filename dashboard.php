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

$presentThisMonth = (int) $db->fetchOne(
    'SELECT COUNT(*) AS total FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?',
    [$userId, date('Y-m-01'), date('Y-m-t')]
)['total'];

$leaveQuota = $db->fetchAll('SELECT * FROM teacher_leave_types WHERE is_active = 1 ORDER BY name ASC');
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
    <title>Dashboard | Teachers Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg,#0b1f3a,#132f55); }
        .nav-link { color: rgba(255,255,255,.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.08); }
        .card-stat { border:0; border-radius:18px; box-shadow: 0 8px 28px rgba(15,23,42,.06); }
        .present-button { font-size: 1.2rem; padding: 1rem 2rem; border-radius: 16px; }
        .status-pill { border-radius: 50px; }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <aside class="col-lg-2 sidebar text-white p-3">
                <div class="d-flex align-items-center mb-4">
                    <div class="rounded-3 bg-primary bg-gradient p-2 me-2"><i class="bi bi-mortarboard-fill fs-4"></i></div>
                    <div>
                        <h5 class="mb-0">Teacher Portal</h5>
                    </div>
                </div>
                <nav class="nav flex-column gap-1">
                    <a class="nav-link active rounded" href="dashboard.php"><i class="bi bi-house-door me-2"></i>Dashboard</a>
                    <a class="nav-link rounded" href="attendance.php"><i class="bi bi-calendar-check me-2"></i>Attendance</a>
                    <a class="nav-link rounded" href="leave.php"><i class="bi bi-file-earmark-text me-2"></i>Leave</a>
                    <a class="nav-link rounded" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a>
                    <?php if (isAdminUser()): ?>
                        <a class="nav-link rounded" href="admin.php"><i class="bi bi-speedometer2 me-2"></i>Admin</a>
                        <a class="nav-link rounded" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
                    <?php endif; ?>
                    <a class="nav-link rounded" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                </nav>
            </aside>
            <main class="col-lg-10">
                <div class="container py-4">
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
                                        <button type="button" id="markAttendanceBtn" class="btn btn-success btn-lg present-button w-100" data-user-id="<?= $userId ?>" <?= $attendanceToday ? 'disabled' : '' ?>>
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                            <i class="bi bi-check2-circle me-2 icon-check"></i>
                                            <span class="btn-text"><?= $attendanceToday ? 'Present Today' : 'Mark Present' ?></span>
                                        </button>
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
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.12.12.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $('#markAttendanceBtn').on('click', function () {
            const btn = $(this);
            if (btn.is(':disabled')) return;

            // show loader
            btn.prop('disabled', true);
            btn.find('.spinner-border').removeClass('d-none');
            const originalText = btn.find('.btn-text').text();
            btn.find('.btn-text').text('Marking attendance...');

            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                btn.find('.spinner-border').addClass('d-none');
                btn.prop('disabled', false);
                btn.find('.btn-text').text(originalText);
                return;
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                $.ajax({
                    url: 'attendance-mark.php',
                    type: 'POST',
                    data: {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        csrf_token: '<?= e($_SESSION['csrf_token'] ?? '') ?>'
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response && response.success) {
                        alert(response.message);
                        btn.find('.spinner-border').addClass('d-none');
                        btn.prop('disabled', true);
                        btn.find('.btn-text').text('Present Today');
                        // update UI elements without reload
                        $('#todayStatusText').text('Present');
                        $('#todayStatusBadge').removeClass('bg-secondary').addClass('bg-success');
                        $('#attendanceSectionBadge').removeClass('bg-warning').addClass('bg-success').text('Present');
                        const pc = $('#presentCount');
                        const current = parseInt(pc.text() || '0', 10) || 0;
                        pc.text(current + 1);
                    } else {
                        alert((response && response.message) ? response.message : 'Unable to mark attendance.');
                        btn.find('.spinner-border').addClass('d-none');
                        btn.prop('disabled', false);
                        btn.find('.btn-text').text(originalText);
                    }
                }).fail(function (jqXHR) {
                    let msg = 'Unable to mark attendance right now. Please try again.';
                    try {
                        const parsed = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                        if (parsed && parsed.message) msg = parsed.message;
                    } catch (e) {
                        // ignore parse errors
                    }
                    alert(msg);
                    btn.find('.spinner-border').addClass('d-none');
                    btn.prop('disabled', false);
                    btn.find('.btn-text').text(originalText);
                });

            }, function (error) {
                let message = 'Location access is required to mark attendance.';
                if (error && error.code === 1) message = 'Location permission denied. Please allow location access to mark attendance.';
                if (error && error.code === 2) message = 'Location information is unavailable at the moment.';
                if (error && error.code === 3) message = 'Location request timed out. Please try again.';
                alert(message);
                btn.find('.spinner-border').addClass('d-none');
                btn.prop('disabled', false);
                btn.find('.btn-text').text(originalText);
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        });
    </script>
</body>
</html>
