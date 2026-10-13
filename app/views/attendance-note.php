<?php if (trim((string) ($attendanceNote ?? '')) !== ''): ?>
<span class="attendance-note small" tabindex="0">
    <i class="bi bi-chat-left-text" aria-hidden="true"></i> Note
    <span class="attendance-note-text" role="tooltip"><?= e($attendanceNote) ?></span>
</span>
<?php endif; ?>
