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
foreach ($db->fetchAll(
    'SELECT id, attendance_date, attendance_time FROM teacher_attendance WHERE user_id = ? AND attendance_date >= ? AND attendance_date <= ?',
    [$userId, $startDay, $endDay]
) as $row) {
    $attendanceMap[$row['attendance_date']] = ['id' => $row['id'], 'time' => $row['attendance_time']];
}

$holidayTable = 'holiday_' . date('Y');
$holidayRows = [];
if ($db->fetchColumnList('SHOW TABLES') && in_array($holidayTable, $db->fetchColumnList('SHOW TABLES'), true)) {
    $holidayRows = $db->fetchAll('SELECT date FROM `' . $holidayTable . '`');
}
$holidayDates = [];
foreach ($holidayRows as $holiday) {
    $holidayDates[$holiday['date']] = true;
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
    <title>Attendance | Teachers Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; }
        /* Classic, eye-catching calendar */
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
        .calendar-day.holiday { background: #fff3f3; border-color: #f8c7c7; position:relative; }
        .calendar-day.holiday .day-number { background:#c82333; color:#fff; }
        .holiday-icon { position:absolute; top:8px; right:8px; width:22px; height:22px; border-radius:50%; background:#dc3545; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; }
        .calendar-day.present { background: #ecfff2; border-color: #a3e0b2; }
        .calendar-day.sunday { background: linear-gradient(180deg,#fff6f6,#fff); border-color: #f5c6cb; }
        .day-number { font-weight: 700; display:inline-block; padding:6px 10px; border-radius:6px; }
        .calendar-day.sunday .day-number { background:#dc3545; color:#fff; }
        .weekday-headers { gap: .5rem; }
        .weekday-headers .col { padding: .35rem .5rem; border-radius: 6px; }
        .weekday-headers .col:first-child { color: #dc3545; font-weight:700; }
        .attendance-label { font-size: 0.76rem; }
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
            <h2 class="fw-bold mb-1">Attendance</h2>
            <p class="text-muted mb-0">Teacher attendance tracker</p>
        </div>
        <div class="btn-group">
            <a href="attendance.php?month=<?= date('Y-m', strtotime('-1 month', strtotime($currentMonth . '-01'))) ?>" class="btn btn-outline-secondary">Previous</a>
            <a href="attendance.php?month=<?= date('Y-m') ?>" class="btn btn-outline-secondary">Current</a>
            <a href="attendance.php?month=<?= date('Y-m', strtotime('+1 month', strtotime($currentMonth . '-01'))) ?>" class="btn btn-outline-secondary">Next</a>
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
                        <?php if ($isHoliday): ?><span class="holiday-icon" title="Holiday"><i class="bi bi-calendar-event-fill"></i></span><?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="day-number <?= $isCurrentMonth ? '' : 'text-muted' ?>"><?= $current->format('d') ?></span>
                            <?php if ($isHoliday): ?><span class="badge bg-danger">Holiday</span><?php endif; ?>
                        </div>
                        <?php if ($isHoliday): ?>
                            <div class="text-danger fw-semibold attendance-label">Holiday</div>
                        <?php elseif ($hasAttendance): ?>
                            <div class="text-success fw-semibold attendance-label">Present</div>
                            <div class="text-success small"><?= date('h:i A', strtotime($attendanceMap[$dayKey]['time'])) ?></div>
                            <?php if (isAdminUser()): ?>
                                <div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger delete-attendance-btn" data-id="<?= (int) $attendanceMap[$dayKey]['id'] ?>">Delete</button></div>
                            <?php endif; ?>
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
</body>
</html>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                dayCell.find('.attendance-label').removeClass('text-success').addClass('text-muted').text('No attendance');
                dayCell.find('.text-success.small').remove();
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
