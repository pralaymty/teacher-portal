<?php

declare(strict_types=1);

require __DIR__ . '/../app/services/LeaveOverviewService.php';

$users = [
    ['id' => 1, 'user_type' => 3, 'gender' => 'M'],
    ['id' => 2, 'user_type' => 4, 'gender' => 'F'],
    ['id' => 3, 'user_type' => 3, 'gender' => 'F'],
    ['id' => 4, 'user_type' => 10, 'gender' => 'M'],
];
$types = [
    ['id' => 1, 'user_type_id' => 3, 'name' => 'SL', 'quota' => 10, 'is_active' => 1, 'gender_restriction' => 'All'],
    ['id' => 2, 'user_type_id' => 4, 'name' => 'SL', 'quota' => 6, 'is_active' => 1, 'gender_restriction' => 'All'],
    ['id' => 3, 'user_type_id' => 3, 'name' => 'ML', 'quota' => 20, 'is_active' => 1, 'gender_restriction' => 'Female'],
    ['id' => 4, 'user_type_id' => 3, 'name' => 'Old', 'quota' => 30, 'is_active' => 0, 'gender_restriction' => 'All'],
];
$applications = [
    ['user_id' => 1, 'leave_type_id' => 1, 'status' => 'Approved', 'days_count' => 2.5],
    ['user_id' => 1, 'leave_type_id' => 1, 'status' => 'Approval Pending', 'days_count' => 3],
    ['user_id' => 1, 'leave_type_id' => 1, 'status' => 'Rejected', 'days_count' => 4],
    ['user_id' => 1, 'leave_type_id' => 4, 'status' => 'Approved', 'days_count' => 1],
    ['user_id' => 2, 'leave_type_id' => 2, 'status' => 'Approved', 'days_count' => 8],
];
$service = new LeaveOverviewService();
$overview = $service->summarize($users, $types, $applications);
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
check(count($overview) === 4, 'Include users with no applications or quotas');
check($overview[0]['totals']['quota'] === 10.0, 'Only eligible active role quota');
check($overview[0]['totals']['taken'] === 3.5, 'Approved and historical usage, not rejected');
check($overview[0]['totals']['pending'] === 3.0, 'Pending separate');
check($overview[0]['totals']['remaining'] === 7.5, 'Pending does not reduce balance');
check($overview[0]['balances'][4]['historical'], 'Retired leave labelled historical');
check($overview[1]['totals']['quota'] === 6.0, 'Same-name role quotas remain separate');
check($overview[1]['totals']['remaining'] === 0.0, 'Over-quota balance cannot be negative');
check($overview[2]['totals']['remaining'] === 30.0, 'Eligible gender quota with no usage');
check($overview[3]['totals']['quota'] === 0.0, 'Unconfigured role has no quota');
$personal = $service->summarize([$users[0]], $types, $applications);
check(count($personal) === 1 && $personal[0]['user']['id'] === 1, 'Personal scope excludes other users');
check($personal[0]['totals'] === $overview[0]['totals'], 'Admin and personal counts match');
$teacherOverview = $service->summarize(
    [$users[0], $users[2]],
    $types,
    [['user_id' => 3, 'leave_type_id' => 1, 'status' => 'Approved', 'days_count' => 12]]
);
check($teacherOverview[0]['totals']['remaining'] === 10.0, 'Male teacher balance excludes maternity quota');
check($teacherOverview[1]['totals']['remaining'] === 20.0, 'Excess usage in one type does not consume another type balance');
echo "$checks leave overview checks passed.\n";
