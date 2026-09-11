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
foreach ($db->fetchAll('SELECT id, attendance_date, attendance_time FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?', [$userId, $startDay, $endDay]) as $row) {
    $attendanceMap[$row['attendance_date']] = ['id' => $row['id'], 'time' => $row['attendance_time']];
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Calendar styling to match attendance view */
        .calendar-day {
            min-height: 110px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(16,24,40,0.04);
            transition: transform .08s ease, box-shadow .08s ease;
            padding: 12px;
        }
        .calendar-day:hover { transform: translateY(-4px); box-shadow: 0 6px 20px rgba(16,24,40,0.08); }
        .calendar-day.future { opacity: 0.5; }
        .calendar-day.present { background: #ecfff2; border-color: #a3e0b2; }
        .calendar-day.sunday { background: linear-gradient(180deg,#fff6f6,#fff); border-color: #f5c6cb; }
        .calendar-day.holiday { background: #fff3f3; border-color: #f8c7c7; position:relative; }
        .calendar-day.holiday .day-number { background:#c82333; color:#fff; }
        .holiday-icon { position:absolute; top:8px; right:8px; width:22px; height:22px; border-radius:50%; background:#dc3545; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; }
        .day-number { font-weight: 700; display:inline-block; padding:6px 10px; border-radius:6px; }
        .calendar-day.sunday .day-number { background:#dc3545; color:#fff; }
        .weekday-headers { gap: .5rem; }
        .weekday-headers .col { padding: .35rem .5rem; border-radius: 6px; }
        .weekday-headers .col:first-child { color: #dc3545; font-weight:700; }
        /* Calendar grid: responsive columns */
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.6rem; }
        .day-item { }
        @media (max-width: 992px) {
            .calendar-grid { grid-template-columns: repeat(5, 1fr); }
            .calendar-day { min-height: 100px; }
        }
        @media (max-width: 768px) {
            .calendar-grid { grid-template-columns: repeat(3, 1fr); }
            .calendar-day { min-height: 90px; }
        }
        @media (max-width: 576px) {
            .calendar-grid { grid-template-columns: repeat(2, 1fr); }
            .calendar-day { min-height: 80px; }
        }
    </style>
</head>
<body>
<div class="container py-4">
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

        <form id="addAttendanceForm" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <div class="col-md-3"><input type="date" class="form-control" name="date" required></div>
            <div class="col-md-3"><input type="time" class="form-control" name="time" value="<?= date('H:i') ?>" required></div>
            <div class="col-md-3"><button class="btn btn-primary" id="addAttendanceBtn">Add Attendance</button></div>
        </form>
    </div>

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
                    <div class="text-success fw-semibold attendance-label">Present</div>
                    <div class="text-success small"><?= date('h:i A', strtotime($attendanceMap[$dayKey]['time'])) ?></div>
                    <div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger delete-attendance-btn" data-id="<?= (int) $attendanceMap[$dayKey]['id'] ?>">Delete</button></div>
                <?php else: ?>
                    <div class="text-muted attendance-label">No attendance</div>
                <?php endif; ?>
            </div>
        </div>
        <?php $current = $current->modify('+1 day'); endwhile; ?>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        btn.prop('disabled', true).text('Adding...');
        $.ajax({
            url: 'attendance-add.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (resp) {
            if (resp && resp.success) {
                showMessage(resp.message);
                // attempt in-place update if the date is visible
                const formDate = $('[name=date]').val();
                const formTime = $('[name=time]').val();
                const cell = $('.calendar-day[data-date="' + formDate + '"]');
                if (cell.length) {
                    cell.addClass('present');
                    cell.find('.attendance-label').removeClass('text-muted').addClass('text-success').text('Present');
                    if (cell.find('.text-success.small').length) {
                        cell.find('.text-success.small').text(new Date('1970-01-01T' + formTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
                    } else {
                        cell.append('<div class="text-success small">' + new Date('1970-01-01T' + formTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) + '</div>');
                    }
                    // add delete button
                    if (!cell.find('.delete-attendance-btn').length) {
                        cell.append('<div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger delete-attendance-btn" data-id="">Delete</button></div>');
                    }
                }
                // clear form
                btn.prop('disabled', false).text('Add Attendance');
            } else {
                showMessage((resp && resp.message) ? resp.message : 'Failed to add attendance');
                btn.prop('disabled', false).text('Add Attendance');
            }
        }).fail(function (jqXHR) {
            let msg = 'Request failed';
            try {
                const parsed = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                if (parsed && parsed.message) msg = parsed.message;
            } catch (e) {}
            showMessage(msg);
            btn.prop('disabled', false).text('Add Attendance');
        });
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
                cell.find('.attendance-label').removeClass('text-success').addClass('text-muted').text('No attendance');
                cell.find('.text-success.small').remove();
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
