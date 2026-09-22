<?php
$attendanceEvents = ['Login' => $attendanceEntry];
if (!empty($attendanceEntry['logoff'])) {
    $attendanceEvents['Logoff'] = $attendanceEntry['logoff'];
}
foreach ($attendanceEvents as $eventLabel => $event):
?>
<div class="attendance-event <?= $eventLabel === 'Logoff' ? 'mt-2' : '' ?>">
<div class="attendance-label <?= e($event['location']['class']) ?>"><?= e($eventLabel) ?></div>
<div class="attendance-time small <?= e($event['location']['class']) ?>"><?= e(formatTime($event['time'])) ?></div>
<div class="attendance-location small <?= e($event['location']['class']) ?>">
    <?= e($event['location']['label']) ?>
    <?php if ($event['location']['distance_m'] !== null): ?>
        <?php $distanceMetres = $event['location']['distance_m']; ?>
        (<?= $distanceMetres > 999 ? number_format($distanceMetres / 1000, 1) . ' KM' : number_format($distanceMetres, 0) . ' M' ?>)
    <?php endif; ?>
</div>
</div>
<?php endforeach; ?>
