<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();
if (!isAdminUser()) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$db = new DatabaseService();
$allUserTypes = $db->fetchAll('SELECT id, user_type FROM user_type ORDER BY id ASC');
$allUserTypeIds = array_map('intval', array_column($allUserTypes, 'id'));
$listedUserTypeIds = json_decode((string) getSetting('settings_user_type_ids', 'general', '[3,4,5,10,11,12]'), true);
if (!is_array($listedUserTypeIds)) {
    $listedUserTypeIds = [3, 4, 5, 10, 11, 12];
}
$userTypes = array_values(array_filter($allUserTypes, static function (array $type) use ($listedUserTypeIds): bool {
    return in_array((int) $type['id'], $listedUserTypeIds, true);
}));
$leaveSettings = new LeaveSettingsService($db);
$availableUserTypeIds = array_map('intval', array_column($userTypes, 'id'));
$selectedLeaveUserType = filter_var($_GET['leave_user_type_id'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($selectedLeaveUserType, $availableUserTypeIds, true)) {
    $defaultLeaveUserType = (int) appConfig()['auth']['teacher_user_type'];
    $selectedLeaveUserType = in_array($defaultLeaveUserType, $availableUserTypeIds, true)
        ? $defaultLeaveUserType : ($availableUserTypeIds[0] ?? 0);
}
$attendanceFields = [
    'entry_start' => 'Official Entry Start',
    'entry_end' => 'Official Entry End',
    'exit_start' => 'Official Exit Start',
    'exit_end' => 'Official Exit End',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security validation failed.'];
        redirect('settings.php');
    }

    $action = $_POST['action'] ?? 'attendance';
    if ($action === 'school_location') {
        try {
            $schoolLocation = SchoolLocationService::validate([
                'latitude' => $_POST['school_latitude'] ?? null,
                'longitude' => $_POST['school_longitude'] ?? null,
                'radius_m' => $_POST['school_radius_m'] ?? null,
            ]);
            setSetting('school_location', json_encode($schoolLocation), 'attendance');
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'School location and allowed distance saved.'];
        } catch (InvalidArgumentException $error) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error->getMessage()];
        }
        redirect('settings.php#school-location');
    }
    if ($action === 'settings_user_types') {
        $submittedTypes = $_POST['listed_user_type_ids'] ?? [];
        $validatedTypes = [];
        $valid = is_array($submittedTypes);
        if ($valid) {
            foreach ($submittedTypes as $value) {
                $typeId = filter_var($value, FILTER_VALIDATE_INT);
                if (!in_array($typeId, $allUserTypeIds, true)) {
                    $valid = false;
                    break;
                }
                $validatedTypes[] = $typeId;
            }
        }
        if (!$valid) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Select valid user types.'];
        } else {
            setSetting('settings_user_type_ids', json_encode(array_values(array_unique($validatedTypes))), 'general');
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User types updated for Attendance Settings and Leave Settings.'];
        }
        redirect('settings.php');
    }
    if ($action === 'attendance') {
        $userTypeId = filter_var($_POST['user_type_id'] ?? null, FILTER_VALIDATE_INT);
        $selectedType = null;
        foreach ($userTypes as $userType) {
            if ((int) $userType['id'] === $userTypeId) {
                $selectedType = $userType;
                break;
            }
        }
        if ($selectedType === null || $userTypeId === (int) appConfig()['auth']['master_admin_user_type']) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Select a valid user type with attendance time restrictions.'];
            redirect('settings.php');
        }
        $attendanceSettingsUrl = 'settings.php?user_type_id=' . $userTypeId;

        $ranges = [];
        foreach ($attendanceFields as $field => $label) {
            $value = $_POST['attendance_' . $field . '_time'] ?? '';
            $ranges[$field] = is_string($value) ? normalizeTimeValue($value, '') : '';
        }
        if (in_array('', $ranges, true)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Enter a valid time for all four attendance fields.'];
            redirect($attendanceSettingsUrl);
        }

        if ($ranges['entry_start'] > $ranges['entry_end'] || $ranges['exit_start'] > $ranges['exit_end']) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Start time must be before end time for each attendance range.'];
            redirect($attendanceSettingsUrl);
        }

        setSetting('attendance_user_type_' . $userTypeId, json_encode($ranges), 'attendance');
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Attendance settings updated for ' . $selectedType['user_type'] . '.'];
        redirect($attendanceSettingsUrl);
    }

    if (in_array($action, ['save_leave_type', 'delete_leave_type'], true)) {
        $leaveUserType = filter_var($_POST['leave_user_type_id'] ?? null, FILTER_VALIDATE_INT);
        $leaveTypeId = filter_var($_POST['leave_type_id'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($leaveUserType, $availableUserTypeIds, true) || $leaveTypeId === false || $leaveTypeId < 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Select a valid user type and leave type.'];
            redirect('settings.php#leave-settings');
        }
        $leaveSettingsUrl = 'settings.php?leave_user_type_id=' . $leaveUserType;
        try {
            if ($action === 'delete_leave_type') {
                $leaveSettings->delete($leaveTypeId, $leaveUserType);
                $message = 'Leave type deleted for the selected user type only. Existing leave records have been preserved.';
            } else {
                $leaveSettings->save($leaveTypeId, $leaveUserType, $_POST);
                $message = 'Leave details and quota saved for the selected user type only.';
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
        } catch (InvalidArgumentException $error) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error->getMessage()];
            if ($action === 'save_leave_type') {
                $_SESSION['leave_form'] = ['user_type' => $leaveUserType, 'id' => $leaveTypeId, 'input' => array_intersect_key($_POST, array_flip(['name', 'code', 'quota', 'gender']))];
                $leaveSettingsUrl .= '&edit_leave_type=' . $leaveTypeId;
            }
        } catch (Throwable $error) {
            error_log('Leave settings error: ' . $error->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Unable to save leave settings. Please try again.'];
        }
        redirect($leaveSettingsUrl . '#leave-settings');
    }
}

$leaveTypes = $leaveSettings->getTypes($selectedLeaveUserType);
$editLeaveTypeId = filter_var($_GET['edit_leave_type'] ?? 0, FILTER_VALIDATE_INT);
$leaveForm = ['id' => 0, 'name' => '', 'code' => '', 'quota' => '0', 'gender_restriction' => 'All'];
foreach ($leaveTypes as $leaveType) {
    if ((int) $leaveType['id'] === $editLeaveTypeId) {
        $leaveForm = $leaveType;
        break;
    }
}
$failedLeaveForm = $_SESSION['leave_form'] ?? null;
unset($_SESSION['leave_form']);
if ($failedLeaveForm && $failedLeaveForm['user_type'] === $selectedLeaveUserType && $failedLeaveForm['id'] === (int) $leaveForm['id']) {
    foreach (['name' => 'name', 'code' => 'code', 'quota' => 'quota', 'gender' => 'gender_restriction'] as $input => $field) {
        if (is_string($failedLeaveForm['input'][$input] ?? null)) {
            $leaveForm[$field] = $failedLeaveForm['input'][$input];
        }
    }
}
$masterAdminType = (int) appConfig()['auth']['master_admin_user_type'];
$selectedUserTypeId = filter_var($_GET['user_type_id'] ?? null, FILTER_VALIDATE_INT);
$availableUserTypeIds = array_map('intval', array_column($userTypes, 'id'));
if (!in_array($selectedUserTypeId, $availableUserTypeIds, true)) {
    $teacherType = (int) appConfig()['auth']['teacher_user_type'];
    $selectedUserTypeId = in_array($teacherType, $availableUserTypeIds, true)
        ? $teacherType : ($availableUserTypeIds[0] ?? 0);
}
$attendanceRanges = getAttendanceTimeRanges($selectedUserTypeId);
$attendanceUnrestricted = $selectedUserTypeId === $masterAdminType;
$schoolLocation = SchoolLocationService::settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | NNV-Teachers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
<main id="portal-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Settings</h2>
            <p class="text-muted mb-0">Attendance and portal configuration</p>
        </div>
        <a href="admin.php" class="btn btn-outline-secondary">Back</a>
    </div>
    <?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <section class="border-top border-bottom py-3 mb-4" id="school-location">
        <h4 class="fs-5 mb-3">School Location</h4>
        <form method="POST" action="settings.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="school_location">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="school-latitude">School Latitude</label>
                    <input class="form-control" id="school-latitude" name="school_latitude" type="number" min="-90" max="90" step="any" value="<?= e((string) ($schoolLocation['latitude'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="school-longitude">School Longitude</label>
                    <input class="form-control" id="school-longitude" name="school_longitude" type="number" min="-180" max="180" step="any" value="<?= e((string) ($schoolLocation['longitude'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="school-radius">Allowed Distance (metres)</label>
                    <input class="form-control" id="school-radius" name="school_radius_m" type="number" min="0" step="any" value="<?= e((string) ($schoolLocation['radius_m'] ?? '50')) ?>" required>
                </div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Save School Location</button>
        </form>
    </section>
    <details class="border-top border-bottom py-3 mb-4" id="settings-user-types">
        <summary class="fw-semibold"><i class="bi bi-sliders me-2" aria-hidden="true"></i>Manage User Types</summary>
        <form method="POST" action="settings.php" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="settings_user_types">
            <p class="text-muted small">Selected user types appear in both settings lists. Removing a type keeps its users and saved settings.</p>
            <div class="row g-2">
                <?php foreach ($allUserTypes as $type): ?>
                    <div class="col-sm-6 col-lg-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="listed-type-<?= (int) $type['id'] ?>" name="listed_user_type_ids[]" value="<?= (int) $type['id'] ?>" <?= in_array((int) $type['id'], $availableUserTypeIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="listed-type-<?= (int) $type['id'] ?>"><?= e($type['user_type']) ?> (<?= (int) $type['id'] ?>)</label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Save User Types</button>
        </form>
    </details>
    <?php if (!$userTypes): ?>
        <div class="alert alert-info">No user types selected.</div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Attendance Settings</h4>
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="attendance">
                        <div class="mb-3">
                            <label class="form-label" for="attendance-user-type">User Type</label>
                            <select class="form-select" id="attendance-user-type" name="user_type_id" required <?= !$userTypes ? 'disabled' : '' ?>>
                                <?php foreach ($userTypes as $userType): ?>
                                <?php
                                $userTypeId = (int) $userType['id'];
                                $roleRanges = $userTypeId === $masterAdminType ? [] : getAttendanceTimeRanges($userTypeId);
                                ?>
                                <option value="<?= $userTypeId ?>" data-ranges="<?= e(json_encode($roleRanges)) ?>" <?= $selectedUserTypeId === $userTypeId ? 'selected' : '' ?>><?= e($userType['user_type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-muted mb-0" id="attendance-unrestricted" <?= $attendanceUnrestricted ? '' : 'hidden' ?>>Attendance allowed at any time.</p>
                        <fieldset id="attendance-time-fields" <?= $attendanceUnrestricted || !$userTypes ? 'hidden disabled' : '' ?>>
                        <div class="row g-3">
                            <?php foreach ($attendanceFields as $field => $label): ?>
                            <div class="col-md-6">
                                <label class="form-label" for="attendance-<?= e($field) ?>"><?= e($label) ?></label>
                                <input type="time" class="form-control" id="attendance-<?= e($field) ?>" data-field="<?= e($field) ?>" name="attendance_<?= e($field) ?>_time" value="<?= e($attendanceRanges[$field]) ?>" required>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Save Settings</button>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3" id="leave-settings">Leave Settings</h4>
                    <form method="GET" action="settings.php#leave-settings" class="mb-3">
                        <input type="hidden" name="user_type_id" value="<?= $selectedUserTypeId ?>">
                        <label class="form-label" for="leave-user-type">User Type</label>
                        <select class="form-select" id="leave-user-type" name="leave_user_type_id" onchange="this.form.submit()" <?= !$userTypes ? 'disabled' : '' ?>>
                            <?php foreach ($userTypes as $userType): ?>
                                <option value="<?= (int) $userType['id'] ?>" <?= (int) $userType['id'] === $selectedLeaveUserType ? 'selected' : '' ?>><?= e($userType['user_type']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button class="btn btn-secondary mt-2">Select User Type</button></noscript>
                    </form>
                    <p class="text-muted small">Leave entries and quotas apply only to the selected user type.</p>

                    <div class="mb-3">
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="action" value="save_leave_type">
                            <input type="hidden" name="leave_user_type_id" value="<?= $selectedLeaveUserType ?>">
                            <input type="hidden" name="leave_type_id" value="<?= (int) $leaveForm['id'] ?>">
                            <fieldset <?= !$userTypes ? 'disabled' : '' ?>>
                            <h5 class="fs-6"><?= $leaveForm['id'] ? 'Edit Leave Type' : 'Add Leave Type' ?></h5>
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-6"><label class="form-label" for="leave-name">Name</label><input class="form-control" id="leave-name" name="name" maxlength="100" value="<?= e($leaveForm['name']) ?>" required></div>
                                <div class="col-sm-6"><label class="form-label" for="leave-code">Code</label><input class="form-control" id="leave-code" name="code" maxlength="30" value="<?= e($leaveForm['code']) ?>" required></div>
                                <div class="col-sm-6"><label class="form-label" for="leave-quota">Quota (days)</label><input class="form-control" id="leave-quota" name="quota" type="number" min="0" max="999.99" step="0.01" value="<?= e((string) $leaveForm['quota']) ?>" required></div>
                                <div class="col-sm-6"><label class="form-label" for="leave-gender">Gender</label><select class="form-select" id="leave-gender" name="gender"><?php foreach (['All', 'Female', 'Male'] as $gender): ?><option <?= $leaveForm['gender_restriction'] === $gender ? 'selected' : '' ?>><?= e($gender) ?></option><?php endforeach; ?></select></div>
                            </div>
                            <div class="mt-3 d-flex justify-content-end gap-2">
                                <?php if ($leaveForm['id']): ?><a class="btn btn-outline-secondary" href="settings.php?leave_user_type_id=<?= $selectedLeaveUserType ?>#leave-settings">Cancel</a><?php endif; ?>
                                <button class="btn btn-primary"><?= $leaveForm['id'] ? 'Save Changes' : 'Add Leave Type' ?></button>
                            </div>
                            </fieldset>
                        </form>
                    </div>

                    <?php if (!empty($leaveTypes)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Name</th><th>Code</th><th>Quota</th><th>Gender</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($leaveTypes as $lt): ?>
                                        <tr>
                                            <td><?= e($lt['name']) ?></td>
                                            <td><?= e($lt['code']) ?></td>
                                            <td><?= e((string) $lt['quota']) ?></td>
                                            <td><?= e($lt['gender_restriction']) ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a class="btn btn-sm btn-outline-primary" href="settings.php?leave_user_type_id=<?= $selectedLeaveUserType ?>&amp;edit_leave_type=<?= (int) $lt['id'] ?>#leave-settings" title="Edit <?= e($lt['name']) ?>" aria-label="Edit <?= e($lt['name']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                                    <form method="POST" action="settings.php" onsubmit="return confirm('Delete this leave type for the selected user type only? Existing leave records will be preserved.');">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                        <input type="hidden" name="action" value="delete_leave_type">
                                                        <input type="hidden" name="leave_type_id" value="<?= (int) $lt['id'] ?>">
                                                        <input type="hidden" name="leave_user_type_id" value="<?= $selectedLeaveUserType ?>">
                                                        <button class="btn btn-sm btn-outline-danger" title="Delete <?= e($lt['name']) ?>" aria-label="Delete <?= e($lt['name']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">No leave types defined.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(() => {
    const userType = document.getElementById('attendance-user-type');
    const timeFields = document.getElementById('attendance-time-fields');
    const unrestricted = document.getElementById('attendance-unrestricted');
    const inputs = timeFields.querySelectorAll('input[data-field]');
    const drafts = {};
    let previousType = userType.value;

    userType.addEventListener('change', () => {
        drafts[previousType] = {};
        inputs.forEach(input => { drafts[previousType][input.dataset.field] = input.value; });
        const option = userType.selectedOptions[0];
        const isUnrestricted = Number(userType.value) === <?= $masterAdminType ?>;
        timeFields.hidden = isUnrestricted || !option;
        timeFields.disabled = isUnrestricted || !option;
        unrestricted.hidden = !isUnrestricted;
        const ranges = drafts[userType.value] || JSON.parse(option ? option.dataset.ranges : '{}');
        inputs.forEach(input => { input.value = ranges[input.dataset.field] || ''; });
        previousType = userType.value;
    });
})();
</script>
</body>
</html>
