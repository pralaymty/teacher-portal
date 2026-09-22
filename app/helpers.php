<?php

declare(strict_types=1);

function appConfig(): array
{
    static $config;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function jsonResponse(bool $success, string $message, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function getClientIpAddress(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $value = $_SERVER[$key];
            if (strpos($value, ',') !== false) {
                $value = trim(explode(',', $value)[0]);
            }
            return $value;
        }
    }

    return 'unknown';
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

function requireLogin(): void
{
    if (empty($_SESSION['is_logged_in']) || empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function isTeacherUser(): bool
{
    $userType = (int) ($_SESSION['user_type'] ?? 0);
    $teacherType = (int) (appConfig()['auth']['teacher_user_type'] ?? 3);
    return $userType === $teacherType;
}

function isAdminUser(): bool
{
    $userType = (int) ($_SESSION['user_type'] ?? 0);
    return in_array($userType, [1, 6], true);
}

function requireTeacher(): void
{
    requireLogin();
    // Allow teacher users as well as admin roles to access teacher pages
    if (!isTeacherUser() && !isAdminUser()) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdminUser()) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function isSuperAdminUser(): bool
{
    $userType = (int) ($_SESSION['user_type'] ?? 0);
    $master = (int) (appConfig()['auth']['master_admin_user_type'] ?? 1);
    return $userType === $master;
}

function getUserFullName(array $user): string
{
    $parts = [
        trim((string) ($user['fname'] ?? '')),
        trim((string) ($user['lname'] ?? '')),
    ];
    $name = trim(implode(' ', array_filter($parts, static fn ($value) => $value !== '')));

    return $name !== '' ? $name : (string) ($user['email'] ?? 'User');
}

function getSetting(string $key, string $group = 'general', $default = null)
{
    $db = new DatabaseService();
    $row = $db->fetchOne(
        'SELECT setting_value FROM teacher_settings WHERE setting_key = :key AND setting_group = :group LIMIT 1',
        ['key' => $key, 'group' => $group]
    );

    if ($row === null) {
        return $default;
    }

    if ($row['setting_value'] === null) {
        return $default;
    }

    return $row['setting_value'];
}

function setSetting(string $key, $value, string $group = 'general'): void
{
    $db = new DatabaseService();
    // Use upsert into teacher_settings to keep all settings in a single table
    $db->execute(
        'INSERT INTO teacher_settings (setting_key, setting_value, setting_group, description, updated_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP',
        [
            $key,
            (string) $value,
            $group,
            $key,
            (int) ($_SESSION['user_id'] ?? 0),
        ]
    );
}

function normalizeTimeValue(string $value, string $default): string
{
    $value = trim($value);
    $time = DateTime::createFromFormat('H:i', $value);
    $errors = DateTime::getLastErrors();
    if ($time instanceof DateTime && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
        return $time->format('H:i');
    }

    $time = DateTime::createFromFormat('H:i:s', $value);
    $errors = DateTime::getLastErrors();
    if ($time instanceof DateTime && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
        return $time->format('H:i');
    }

    return $default;
}

function getAttendanceTimeRanges(?int $userType = null): array
{
    $userType = $userType ?? (int) ($_SESSION['user_type'] ?? 0);
    $storedRanges = json_decode((string) getSetting('attendance_user_type_' . $userType, 'attendance', ''), true);
    if (is_array($storedRanges)) {
        $ranges = [];
        foreach (['entry_start', 'entry_end', 'exit_start', 'exit_end'] as $field) {
            $ranges[$field] = is_string($storedRanges[$field] ?? null)
                ? normalizeTimeValue($storedRanges[$field], '') : '';
        }
        if (!in_array('', $ranges, true)
            && $ranges['entry_start'] <= $ranges['entry_end']
            && $ranges['exit_start'] <= $ranges['exit_end']) {
            return $ranges;
        }
    }

    // Preserve the existing schedule until this user type has its own settings.
    return [
        'entry_start' => normalizeTimeValue((string) getSetting('attendance_entry_start_time', 'attendance', '10:30'), '10:30'),
        'entry_end' => normalizeTimeValue((string) getSetting('attendance_entry_end_time', 'attendance', '11:00'), '11:00'),
        'exit_start' => normalizeTimeValue((string) getSetting('attendance_exit_start_time', 'attendance', '16:00'), '16:00'),
        'exit_end' => normalizeTimeValue((string) getSetting('attendance_exit_end_time', 'attendance', '16:30'), '16:30'),
    ];
}

function isTimeWithinRange(string $time, string $start, string $end): bool
{
    return $time >= $start && $time <= $end;
}

function isAttendanceOpenNow(?DateTimeInterface $now = null): bool
{
    if (isSuperAdminUser()) {
        return true;
    }

    $ranges = getAttendanceTimeRanges();
    $currentTime = ($now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('H:i');

    return isTimeWithinRange($currentTime, $ranges['entry_start'], $ranges['entry_end'])
        || isTimeWithinRange($currentTime, $ranges['exit_start'], $ranges['exit_end']);
}

function getAttendanceWindowMessage(): string
{
    if (isSuperAdminUser()) {
        return 'The super admin can mark attendance at any time.';
    }

    $ranges = getAttendanceTimeRanges();

    return 'Attendance can be marked only between '
        . formatTime($ranges['entry_start']) . ' - ' . formatTime($ranges['entry_end'])
        . ' or '
        . formatTime($ranges['exit_start']) . ' - ' . formatTime($ranges['exit_end']) . '.';
}

function isAttendanceLogoffOpenNow(?DateTimeInterface $now = null): bool
{
    if (isSuperAdminUser()) {
        return true;
    }
    $ranges = getAttendanceTimeRanges();
    $time = ($now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('H:i');
    return isTimeWithinRange($time, $ranges['exit_start'], $ranges['exit_end']);
}

function getAttendanceLogoffWindowMessage(): string
{
    $ranges = getAttendanceTimeRanges();
    return 'Logoff is available between ' . formatTime($ranges['exit_start']) . ' and ' . formatTime($ranges['exit_end']) . '.';
}

function formatDate(string $date): string
{
    if ($date === '' || $date === '0000-00-00') {
        return '-';
    }

    $d = new DateTimeImmutable($date, new DateTimeZone('Asia/Kolkata'));
    return $d->format('d M, Y');
}

function formatTime(string $time): string
{
    if ($time === '' || $time === null) {
        return '-';
    }

    $t = DateTime::createFromFormat('H:i:s', $time);
    if ($t === false) {
        $t = DateTime::createFromFormat('H:i', $time);
    }

    if ($t !== false) {
        return $t->format('h:i A');
    }

    return $time;
}

function sanitizeText(?string $value, int $maxLength = 255): string
{
    $value = trim((string) $value);
    if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function isValidEmail(string $value): bool
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidLatitude(string $value): bool
{
    $lat = (float) $value;
    return is_numeric($value) && is_finite($lat) && $lat >= -90 && $lat <= 90;
}

function isValidLongitude(string $value): bool
{
    $lng = (float) $value;
    return is_numeric($value) && is_finite($lng) && $lng >= -180 && $lng <= 180;
}

function ensurePortalSchema(): void
{
    try {
        $db = new DatabaseService();
    } catch (Throwable $e) {
        trigger_error('Database unavailable in ensurePortalSchema: ' . $e->getMessage(), E_USER_WARNING);
        return;
    }

    $tables = $db->fetchColumnList('SHOW TABLES');

    if (!in_array('teacher_attendance', $tables, true)) {
        $sql = file_get_contents(__DIR__ . '/../database/teacher_portal_tables.sql');
        if ($sql === false) {
            trigger_error('Failed to read database schema file', E_USER_WARNING);
            return;
        }

        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }

            try {
                $db->execute($stmt);
            } catch (Throwable $e) {
                trigger_error('Error creating schema: ' . $e->getMessage(), E_USER_WARNING);
            }
        }
    }
    LeaveSettingsMigration::run($db);
    AttendanceLogoffMigration::run($db);
}
