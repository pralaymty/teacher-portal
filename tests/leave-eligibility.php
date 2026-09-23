<?php

declare(strict_types=1);

require __DIR__ . '/../app/services/DatabaseService.php';
require __DIR__ . '/../app/services/LeaveSettingsService.php';

class EligibilityTestDatabase extends DatabaseService
{
    public function __construct()
    {
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return [
            ['name' => 'Casual Leave', 'gender_restriction' => 'All', 'quota' => 10],
            ['name' => 'Maternity Leave', 'gender_restriction' => 'Female', 'quota' => 180],
            ['name' => 'Paternity Leave', 'gender_restriction' => 'Male', 'quota' => 15],
        ];
    }
}

$service = new LeaveSettingsService(new EligibilityTestDatabase());
$checks = 0;
foreach (['M' => 25, 'Male' => 25, ' m ' => 25, 'F' => 190, 'Female' => 190, '' => 10] as $gender => $expectedQuota) {
    $types = $service->getEligibleTypes(3, $gender);
    if (array_sum(array_column($types, 'quota')) !== $expectedQuota) {
        throw new RuntimeException('Incorrect eligible quota for gender: ' . $gender);
    }
    if ($expectedQuota === 25 && in_array('Maternity Leave', array_column($types, 'name'), true)) {
        throw new RuntimeException('Maternity Leave is visible to a male user.');
    }
    $checks++;
}

echo "$checks leave eligibility checks passed.\n";
