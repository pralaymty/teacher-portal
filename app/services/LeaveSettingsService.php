<?php

declare(strict_types=1);

class LeaveSettingsService
{
    private DatabaseService $db;

    public function __construct(DatabaseService $db)
    {
        $this->db = $db;
    }

    /** Return only the selected role's active leave entries. */
    public function getTypes(int $userType): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, code, gender_restriction, is_active, quota
             FROM teacher_leave_types WHERE user_type_id = ? AND is_active = 1 ORDER BY name ASC',
            [$userType]
        );
    }

    /** Return active leave entries matching the user's role and gender. */
    public function getEligibleTypes(int $userType, string $gender): array
    {
        $gender = strtoupper(trim($gender));
        $gender = ['M' => 'MALE', 'F' => 'FEMALE'][$gender] ?? $gender;

        return array_filter($this->getTypes($userType), static function (array $type) use ($gender): bool {
            return in_array(strtoupper(trim($type['gender_restriction'])), ['ALL', $gender], true);
        });
    }

    /** Add, edit, or restore a leave entry within one user type. */
    public function save(int $id, int $userType, array $input): void
    {
        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $code = is_string($input['code'] ?? null) ? trim($input['code']) : '';
        $gender = $input['gender'] ?? '';
        $quota = filter_var($input['quota'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($name === '' || mb_strlen($name) > 100 || $code === '' || mb_strlen($code) > 30) {
            throw new InvalidArgumentException('Enter a leave name (up to 100 characters) and code (up to 30 characters).');
        }
        if ($quota === false || !is_finite($quota) || $quota < 0 || $quota > 999.99
            || abs($quota * 100 - round($quota * 100)) > 0.000001) {
            throw new InvalidArgumentException('Quota must be between 0 and 999.99 days, with at most two decimal places.');
        }
        if (!in_array($gender, ['All', 'Female', 'Male'], true)) {
            throw new InvalidArgumentException('Select a valid gender restriction.');
        }
        if (!$this->db->fetchOne('SELECT id FROM user_type WHERE id = ?', [$userType])) {
            throw new InvalidArgumentException('Select a valid user type.');
        }

        $this->db->transaction(function () use ($id, $userType, $name, $code, $gender, $quota): void {
            if ($id !== 0 && !$this->db->fetchOne('SELECT id FROM teacher_leave_types WHERE id = ? AND user_type_id = ? AND is_active = 1 FOR UPDATE', [$id, $userType])) {
                throw new InvalidArgumentException('This leave type is no longer available.');
            }
            $matches = $this->db->fetchAll(
                'SELECT id, is_active FROM teacher_leave_types WHERE user_type_id = ? AND (name = ? OR code = ?) AND id <> ? FOR UPDATE',
                [$userType, $name, $code, $id]
            );
            if ($id === 0 && count($matches) === 1 && (int) $matches[0]['is_active'] === 0) {
                // Reuse this role's original ID to preserve its past applications.
                $id = (int) $matches[0]['id'];
            } elseif ($matches !== []) {
                throw new InvalidArgumentException('This leave name or code already exists for the selected user type.');
            }
            if ($id === 0) {
                $this->write('INSERT INTO teacher_leave_types (user_type_id, name, code, quota, gender_restriction, is_active) VALUES (?, ?, ?, ?, ?, 1)', [$userType, $name, $code, $quota, $gender]);
            } else {
                $this->write('UPDATE teacher_leave_types SET name = ?, code = ?, quota = ?, gender_restriction = ?, is_active = 1 WHERE id = ? AND user_type_id = ?', [$name, $code, $quota, $gender, $id, $userType]);
            }
        });
    }

    /** Retain the leave type so historical applications keep their reference. */
    public function delete(int $id, int $userType): void
    {
        if (!$this->db->fetchOne('SELECT id FROM teacher_leave_types WHERE id = ? AND user_type_id = ? AND is_active = 1', [$id, $userType])) {
            throw new InvalidArgumentException('This leave type is no longer available.');
        }
        $this->write('UPDATE teacher_leave_types SET is_active = 0 WHERE id = ? AND user_type_id = ?', [$id, $userType]);
    }

    private function write(string $sql, array $params = []): void
    {
        if (!$this->db->execute($sql, $params)) {
            throw new RuntimeException('Could not save leave settings.');
        }
    }
}
