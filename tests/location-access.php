<?php
declare(strict_types=1);

require __DIR__ . '/../app/services/LocationAccessService.php';

$checks = [
    !LocationAccessService::isVerified([]),
    LocationAccessService::isVerified(['location_verified_at' => time()]),
    !LocationAccessService::isVerified(['location_verified_at' => time() - 1800]),
    !LocationAccessService::isVerified(['location_verified_at' => time() + 100]),
    LocationAccessService::validReading(['latitude' => 0, 'longitude' => 0]),
    !LocationAccessService::validReading(['latitude' => 91, 'longitude' => 0]),
    !LocationAccessService::validReading(['latitude' => 0, 'longitude' => 181]),
    !LocationAccessService::validReading([]),
    !LocationAccessService::validReading(['latitude' => [], 'longitude' => 0]),
    LocationAccessService::returnPage('teachers.php') === 'teachers.php',
    LocationAccessService::returnPage('leave-export.php?user_id=12') === 'leave-export.php?user_id=12',
    LocationAccessService::returnPage('https://example.com') === 'index.php',
    LocationAccessService::returnPage('//example.com') === 'index.php',
    LocationAccessService::returnPage('logout.php') === 'index.php',
    LocationAccessService::returnPage("index.php?x=\r\nLocation: bad") === 'index.php',
];
if (in_array(false, $checks, true)) throw new RuntimeException('Location access check failed');
echo count($checks) . " location access checks passed.\n";
