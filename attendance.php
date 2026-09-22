<?php
require_once __DIR__ . '/app/bootstrap.php';
requireTeacher();

$db = new DatabaseService();
$userId = (int) $_SESSION['user_id'];
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$monthDate = new DateTimeImmutable($currentMonth . '-01', new DateTimeZone('Asia/Kolkata'));
$startOfMonth = $monthDate->modify('first day of this month');
$startDay = $startOfMonth->format('Y-m-01');
$endDay = $startOfMonth->format('Y-m-t');

$attendanceMap = [];
$schoolLocation = SchoolLocationService::settings();
foreach ($db->fetchAll(
    'SELECT id, attendance_date, attendance_time, latitude, longitude, logoff_time, logoff_latitude, logoff_longitude FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?',
    [$userId, $startDay, $endDay]
) as $row) {
    $attendanceMap[$row['attendance_date']] = ['id' => $row['id'], 'time' => $row['attendance_time'],
        'location' => SchoolLocationService::assess($row['latitude'], $row['longitude'], $schoolLocation),
        'logoff' => $row['logoff_time'] === null ? null : ['time' => $row['logoff_time'],
            'location' => SchoolLocationService::assess($row['logoff_latitude'], $row['logoff_longitude'], $schoolLocation)]];
}

$holidayTable = 'holiday_' . date('Y');
$holidayRows = [];
if ($db->fetchColumnList('SHOW TABLES') && in_array($holidayTable, $db->fetchColumnList('SHOW TABLES'), true)) {
    $holidayRows = $db->fetchAll('SELECT date, name FROM `' . $holidayTable . '`');
}
$holidayDates = [];
foreach ($holidayRows as $holiday) {
    $holidayDates[$holiday['date']] = isset($holiday['name']) && $holiday['name'] !== '' ? $holiday['name'] : 'Holiday';
}

$monthLabel = $monthDate->format('F Y');
$calendarStart = (clone $monthDate)->modify('first day of this month');
// align to the previous Sunday so weeks render Sun-Sat
$startWeekday = (int) $calendarStart->format('w'); // 0 = Sunday
if ($startWeekday !== 0) {
    $calendarStart = $calendarStart->modify('-' . $startWeekday . ' days');
}
$calendarEnd = (clone $monthDate)->modify('last day of this month');
// extend to next Saturday so weeks end on Saturday
$endWeekday = (int) $calendarEnd->format('w'); // 6 = Saturday
if ($endWeekday !== 6) {
    $daysToAdd = 6 - $endWeekday;
    $calendarEnd = $calendarEnd->modify('+' . $daysToAdd . ' days');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | NNV-Teachers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
<main id="portal-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Attendance</h2>
            <p class="text-muted mb-0">Teacher attendance tracker</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= isAdminUser() ? 'admin.php' : 'dashboard.php' ?>" class="btn btn-outline-secondary">Back</a>
            <div class="btn-group">
                <a href="attendance.php?month=<?= date('Y-m', strtotime('-1 month', strtotime($currentMonth . '-01'))) ?>" class="btn btn-outline-secondary">Previous</a>
                <a href="attendance.php?month=<?= date('Y-m') ?>" class="btn btn-outline-secondary">Current</a>
                <a href="attendance.php?month=<?= date('Y-m', strtotime('+1 month', strtotime($currentMonth . '-01'))) ?>" class="btn btn-outline-secondary">Next</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Present Days</small><div id="presentCountMonth" class="fw-bold fs-3 mt-2"><?= $db->fetchOne('SELECT COUNT(*) AS total FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?', [$userId, $startDay, $endDay])['total'] ?? 0 ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Leave Days</small><div class="fw-bold fs-3 mt-2">0</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Holidays</small><div class="fw-bold fs-3 mt-2"><?= count($holidayRows) ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3"><?= e($monthLabel) ?></h4>
            <div class="calendar-scroll" tabindex="0" role="region" aria-label="Attendance calendar">
<div class="row text-center text-uppercase small text-muted mb-2 weekday-headers">
                <div class="col">Sun</div><div class="col">Mon</div><div class="col">Tue</div><div class="col">Wed</div><div class="col">Thu</div><div class="col">Fri</div><div class="col">Sat</div>
            </div>
            <div class="calendar-grid">
                <?php $current = new DateTimeImmutable($calendarStart->format('Y-m-d')); $end = new DateTimeImmutable($calendarEnd->format('Y-m-d')); while ($current <= $end):
                    $dayKey = $current->format('Y-m-d');
                    $isCurrentMonth = $current->format('n') === $monthDate->format('n');
                    $isFuture = $current > new DateTimeImmutable(date('Y-m-d'));
                    $isHoliday = isset($holidayDates[$dayKey]);
                    $hasAttendance = isset($attendanceMap[$dayKey]);
                    $isSunday = $current->format('w') === '0';
                ?>
                <div class="day-item">
                    <div class="calendar-day <?= $isHoliday ? 'holiday' : ($hasAttendance ? 'present' : '') ?> <?= $isFuture ? 'future' : '' ?> <?= $isSunday ? 'sunday' : '' ?>" data-date="<?= $dayKey ?>">
                        <?php if ($isHoliday): ?><span class="holiday-icon" title="<?= e($holidayDates[$dayKey]) ?>"><i class="bi bi-calendar-event-fill"></i></span><?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="day-number <?= $isCurrentMonth ? '' : 'text-muted' ?>"><?= $current->format('d') ?></span>
                            <?php if ($isHoliday): ?><span class="badge bg-danger">Holiday</span><?php endif; ?>
                        </div>
                        <?php if ($hasAttendance): ?>
                            <?php $attendanceEntry = $attendanceMap[$dayKey]; require __DIR__ . '/app/views/attendance-location.php'; ?>
                            <?php if ($dayKey === date('Y-m-d') && $attendanceEntry['logoff'] === null): ?>
                                <button type="button" id="markAttendanceBtn" data-action="logoff" class="btn btn-sm btn-outline-danger mt-2" <?= !isAttendanceLogoffOpenNow() ? 'disabled' : '' ?> title="<?= e(getAttendanceLogoffWindowMessage()) ?>">
                                    <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span><span class="btn-text">Logoff</span>
                                </button>
                            <?php endif; ?>
                            <?php if (isAdminUser()): ?>
                                <div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger delete-attendance-btn" data-id="<?= (int) $attendanceMap[$dayKey]['id'] ?>">Delete</button></div>
                            <?php endif; ?>
                        <?php elseif ($isHoliday): ?>
                            <div class="text-danger fw-semibold attendance-label">Holiday</div>
                        <?php elseif ($isFuture): ?>
                            <div class="text-muted attendance-label">Upcoming</div>
                        <?php else: ?>
                            <div class="text-muted attendance-label">No attendance</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $current = $current->modify('+1 day'); endwhile; ?>
            </div>
    </div>
        </div>
    </div>
</main>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php require __DIR__ . '/app/views/attendance-action-script.php'; ?>
<script>
    $(document).on('click', '.delete-attendance-btn', function () {
        if (!confirm('Delete this attendance record? This action cannot be undone.')) return;
        const btn = $(this);
        const id = btn.data('id');
        btn.prop('disabled', true).text('Deleting...');

        $.post('attendance-delete.php', { id: id, csrf_token: '<?= e($_SESSION['csrf_token'] ?? '') ?>' }, function (resp) {
            if (resp && resp.success) {
                // remove present class and update text
                const dayCell = btn.closest('.calendar-day');
                dayCell.removeClass('present');
                dayCell.find('.attendance-event').remove();
                dayCell.find('#markAttendanceBtn').remove();
                dayCell.append('<div class="text-muted attendance-label">No attendance</div>');
                btn.remove();
                // decrement present count on page
                const countEl = $('#presentCountMonth');
                const current = parseInt(countEl.text() || '0', 10);
                countEl.text(Math.max(0, current - 1));
                alert(resp.message);
            } else {
                alert((resp && resp.message) ? resp.message : 'Unable to delete attendance.');
                btn.prop('disabled', false).text('Delete');
            }
        }, 'json').fail(function () {
            alert('Request failed. Try again.');
            btn.prop('disabled', false).text('Delete');
        });
    });
</script>
</body>
</html>
