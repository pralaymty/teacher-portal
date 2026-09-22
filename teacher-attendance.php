<?php
require_once __DIR__ . '/app/bootstrap.php';
requireAdmin();

$db = new DatabaseService();
$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($userId <= 0) {
    redirect('teachers.php');
}

$user = $db->fetchOne('SELECT * FROM user WHERE id = ? LIMIT 1', [$userId]);
if (!$user) redirect('teachers.php');

$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$monthDate = new DateTimeImmutable($currentMonth . '-01', new DateTimeZone('Asia/Kolkata'));
$startOfMonth = $monthDate->modify('first day of this month');
$startDay = $startOfMonth->format('Y-m-01');
$endDay = $startOfMonth->format('Y-m-t');

$attendanceMap = [];
$schoolLocation = SchoolLocationService::settings();
foreach ($db->fetchAll('SELECT id, attendance_date, attendance_time, latitude, longitude, logoff_time, logoff_latitude, logoff_longitude FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?', [$userId, $startDay, $endDay]) as $row) {
    $attendanceMap[$row['attendance_date']] = ['id' => $row['id'], 'time' => $row['attendance_time'],
        'location' => SchoolLocationService::assess($row['latitude'], $row['longitude'], $schoolLocation),
        'logoff' => $row['logoff_time'] === null ? null : ['time' => $row['logoff_time'],
            'location' => SchoolLocationService::assess($row['logoff_latitude'], $row['logoff_longitude'], $schoolLocation)]];
}

$monthLabel = $monthDate->format('F Y');
$prevMonth = date('Y-m', strtotime($monthDate->format('Y-m-01') . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthDate->format('Y-m-01') . ' +1 month'));
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
// load holidays for the year (include name) if table exists
$holidayTable = 'holiday_' . date('Y');
$holidayRows = [];
if ($db->fetchColumnList('SHOW TABLES') && in_array($holidayTable, $db->fetchColumnList('SHOW TABLES'), true)) {
    $holidayRows = $db->fetchAll('SELECT date, name FROM `' . $holidayTable . '`');
}
$holidayDates = [];
foreach ($holidayRows as $holiday) {
    $holidayDates[$holiday['date']] = isset($holiday['name']) && $holiday['name'] !== '' ? $holiday['name'] : 'Holiday';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Attendance | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
<main id="portal-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Attendance for <?= e(getUserFullName($user)) ?></h2>
            <p class="text-muted mb-0"><?= e($monthLabel) ?></p>
        </div>
        <div>
            <a href="teachers.php" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="btn-group">
                <a class="btn btn-outline-secondary" href="teacher-attendance.php?user_id=<?= $userId ?>&month=<?= $prevMonth ?>">&larr; Prev</a>
                <a class="btn btn-outline-secondary" href="teacher-attendance.php?user_id=<?= $userId ?>&month=<?= date('Y-m') ?>">Current</a>
                <a class="btn btn-outline-secondary" href="teacher-attendance.php?user_id=<?= $userId ?>&month=<?= $nextMonth ?>">Next &rarr;</a>
            </div>
            <div class="fw-semibold"><?= e($monthLabel) ?></div>
        </div>

        <?php if (isSuperAdminUser()): ?>
            <form id="addAttendanceForm" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="user_id" value="<?= $userId ?>">
                <div class="col-md-3"><input type="date" class="form-control" name="date" required></div>
                <div class="col-md-3"><input type="time" class="form-control" name="time" value="<?= date('H:i') ?>" required></div>
                <div class="col-md-3"><button class="btn btn-primary" id="addAttendanceBtn">Add Attendance</button></div>
            </form>
        <?php else: ?>
            <div class="alert alert-info mb-0">Only the super admin can add attendance manually.</div>
        <?php endif; ?>
    </div>

    <div class="calendar-scroll" tabindex="0" role="region" aria-label="Attendance calendar">
<div class="row text-center text-uppercase small text-muted mb-2 weekday-headers">
        <div class="col">Sun</div><div class="col">Mon</div><div class="col">Tue</div><div class="col">Wed</div><div class="col">Thu</div><div class="col">Fri</div><div class="col">Sat</div>
    </div>
    <div class="calendar-grid">
        <?php
            $current = new DateTimeImmutable($calendarStart->format('Y-m-d'));
            $end = new DateTimeImmutable($calendarEnd->format('Y-m-d'));
            while ($current <= $end):
                $dayKey = $current->format('Y-m-d');
                $isCurrentMonth = $current->format('n') === $monthDate->format('n');
                $isFuture = $current > new DateTimeImmutable(date('Y-m-d'));
                $hasAttendance = isset($attendanceMap[$dayKey]);
                $isSunday = $current->format('w') === '0';
        ?>
        <div class="day-item">
            <div class="calendar-day <?= $hasAttendance ? 'present' : '' ?> <?= $isFuture ? 'future' : '' ?> <?= $isSunday ? 'sunday' : '' ?><?= (isset($holidayDates[$dayKey]) ? ' holiday' : '') ?>" data-date="<?= $dayKey ?>">
                <?php if (isset($holidayDates[$dayKey])): ?><span class="holiday-icon" title="<?= e($holidayDates[$dayKey]) ?>"><i class="bi bi-calendar-event-fill"></i></span><?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="day-number <?= $isCurrentMonth ? '' : 'text-muted' ?>"><?= $current->format('d') ?></span>
                </div>
                <?php if ($hasAttendance): ?>
                    <?php $attendanceEntry = $attendanceMap[$dayKey]; require __DIR__ . '/app/views/attendance-location.php'; ?>
                    <?php if ($userId === (int) $_SESSION['user_id'] && $dayKey === date('Y-m-d') && $attendanceEntry['logoff'] === null): ?>
                        <button type="button" id="markAttendanceBtn" data-action="logoff" class="btn btn-sm btn-outline-danger mt-2" <?= !isAttendanceLogoffOpenNow() ? 'disabled' : '' ?> title="<?= e(getAttendanceLogoffWindowMessage()) ?>">
                            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span><span class="btn-text">Logoff</span>
                        </button>
                    <?php endif; ?>
                    <div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger delete-attendance-btn" data-id="<?= (int) $attendanceMap[$dayKey]['id'] ?>">Delete</button></div>
                <?php else: ?>
                    <div class="text-muted attendance-label">No attendance</div>
                <?php endif; ?>
            </div>
        </div>
        <?php $current = $current->modify('+1 day'); endwhile; ?>
    </div>
    </div>
</main>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php require __DIR__ . '/app/views/attendance-action-script.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Modal for messages -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Notification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"><p id="messageModalBody"></p></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button></div>
    </div>
  </div>
</div>

<script>
    const msgModalEl = document.getElementById('messageModal');
    const msgModal = new bootstrap.Modal(msgModalEl);
    function showMessage(message) {
        $('#messageModalBody').text(message);
        msgModal.show();
    }

    $('#addAttendanceForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#addAttendanceBtn');
        if (btn.prop('disabled')) return;
        const data = $(this).serializeArray();
        const selectedDate = data.find(field => field.name === 'date').value;
        const locationError = function (message) {
            showMessage(message);
            btn.prop('disabled', false).text('Add Attendance');
        };
        if (!navigator.geolocation) {
            locationError('Geolocation is not supported by this browser.');
            return;
        }
        btn.prop('disabled', true).text('Getting location...');
        navigator.geolocation.getCurrentPosition(function (position) {
            data.push({ name: 'latitude', value: position.coords.latitude });
            data.push({ name: 'longitude', value: position.coords.longitude });
            btn.text('Adding...');
            $.ajax({
                url: 'attendance-add.php',
                method: 'POST',
                data: data,
                dataType: 'json'
            }).done(function (resp) {
                if (resp && resp.success) {
                    const calendarUrl = new URL(window.location.href);
                    calendarUrl.searchParams.set('month', selectedDate.slice(0, 7));
                    window.location.href = calendarUrl.toString();
                } else {
                    locationError((resp && resp.message) ? resp.message : 'Failed to add attendance');
                }
            }).fail(function (jqXHR) {
                let msg = 'Request failed';
                try {
                    const parsed = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                    if (parsed && parsed.message) msg = parsed.message;
                } catch (e) {}
                locationError(msg);
            });
        }, function (error) {
            let message = 'Your current location is required to add attendance.';
            if (error && error.code === 1) message = 'Location permission denied. Please allow location access to add attendance.';
            if (error && error.code === 2) message = 'Location information is unavailable. Please try again.';
            if (error && error.code === 3) message = 'Location request timed out. Please try again.';
            locationError(message);
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    });

    $(document).on('click', '.delete-attendance-btn', function () {
        if (!confirm('Delete this attendance record?')) return;
        const btn = $(this);
        btn.prop('disabled', true).text('Deleting...');
        $.ajax({
            url: 'attendance-delete.php',
            method: 'POST',
            data: { id: btn.data('id'), csrf_token: '<?= e($_SESSION['csrf_token'] ?? '') ?>' },
            dataType: 'json'
        }).done(function (resp) {
            if (resp && resp.success) {
                showMessage(resp.message);
                const cell = btn.closest('.calendar-day');
                cell.removeClass('present');
                cell.find('.attendance-event').remove();
                cell.find('#markAttendanceBtn').remove();
                cell.append('<div class="text-muted attendance-label">No attendance</div>');
                btn.remove();
                const pc = $('#presentCountMonth');
                const current = parseInt(pc.text() || '0', 10) || 0;
                pc.text(Math.max(0, current - 1));
            } else {
                showMessage((resp && resp.message) ? resp.message : 'Failed');
                btn.prop('disabled', false).text('Delete');
            }
        }).fail(function (jqXHR) {
            let msg = 'Request failed';
            try {
                const parsed = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                if (parsed && parsed.message) msg = parsed.message;
            } catch (e) {}
            showMessage(msg);
            btn.prop('disabled', false).text('Delete');
        });
    });
</script>
</body>
</html>
