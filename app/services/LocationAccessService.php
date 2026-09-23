<?php

declare(strict_types=1);

class LocationAccessService
{
    /** A recent browser location check is required for portal requests. */
    public static function isVerified(array $session): bool
    {
        $verifiedAt = (int) ($session['location_verified_at'] ?? 0);
        return $verifiedAt > 0 && $verifiedAt <= time() && time() - $verifiedAt < 1800;
    }

    /** Only allow redirects to local page scripts, never external URLs or actions. */
    public static function returnPage(string $page): string
    {
        $pages = ['index.php', 'dashboard.php', 'teachers.php', 'teacher-attendance.php',
            'attendance.php', 'attendance-calendar.php', 'leave.php', 'leave-history.php',
            'leave-admin.php', 'leave-export.php', 'admin.php', 'settings.php', 'profile.php'];
        $path = explode('?', $page, 2)[0];
        return in_array($path, $pages, true) && !preg_match('/[\r\n#]/', $page) ? $page : 'index.php';
    }

    /** Validate the browser reading without retaining coordinates in the session. */
    public static function validReading(array $input): bool
    {
        foreach (['latitude' => 90, 'longitude' => 180] as $key => $limit) {
            $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_FLOAT);
            if ($value === false || !is_finite($value) || abs($value) > $limit) {
                return false;
            }
        }
        return true;
    }
}
