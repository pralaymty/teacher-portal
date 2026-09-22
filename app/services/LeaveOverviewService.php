<?php

declare(strict_types=1);

class LeaveOverviewService
{
    /** Build all-time balances without dropping historical leave from retired types. */
    public function summarize(array $users, array $types, array $applications): array
    {
        $usage = [];
        foreach ($applications as $application) {
            $key = $application['status'] === 'Approved' ? 'taken'
                : ($application['status'] === 'Approval Pending' ? 'pending' : null);
            if ($key === null) {
                continue;
            }
            $userId = (int) $application['user_id'];
            $typeId = (int) $application['leave_type_id'];
            $usage[$userId][$typeId][$key] = ($usage[$userId][$typeId][$key] ?? 0)
                + (float) $application['days_count'];
        }

        $typesById = array_column($types, null, 'id');
        $overview = [];
        foreach ($users as $user) {
            $userId = (int) $user['id'];
            $gender = strtoupper(trim((string) ($user['gender'] ?? '')));
            $gender = ['M' => 'MALE', 'F' => 'FEMALE'][$gender] ?? $gender;
            $balances = [];
            foreach ($types as $type) {
                if ((int) $type['user_type_id'] !== (int) $user['user_type']
                    || !(int) $type['is_active']
                    || !in_array(strtoupper($type['gender_restriction']), ['ALL', $gender], true)) {
                    continue;
                }
                $balances[(int) $type['id']] = [
                    'name' => $type['name'], 'quota' => (float) $type['quota'],
                    'taken' => 0.0, 'pending' => 0.0, 'remaining' => 0.0, 'historical' => false,
                ];
            }
            foreach ($usage[$userId] ?? [] as $typeId => $counts) {
                if (!isset($balances[$typeId])) {
                    $balances[$typeId] = [
                        'name' => $typesById[$typeId]['name'] ?? 'Deleted leave type',
                        'quota' => 0.0, 'taken' => 0.0, 'pending' => 0.0,
                        'remaining' => 0.0, 'historical' => true,
                    ];
                }
                $balances[$typeId]['taken'] = $counts['taken'] ?? 0.0;
                $balances[$typeId]['pending'] = $counts['pending'] ?? 0.0;
            }
            $totals = ['quota' => 0.0, 'taken' => 0.0, 'pending' => 0.0, 'remaining' => 0.0];
            foreach ($balances as &$balance) {
                $balance['remaining'] = max(0.0, $balance['quota'] - $balance['taken']);
                foreach ($totals as $key => $value) {
                    $totals[$key] += $balance[$key];
                }
            }
            unset($balance);
            $overview[] = ['user' => $user, 'balances' => $balances, 'totals' => $totals];
        }
        return $overview;
    }
}
