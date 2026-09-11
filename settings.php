<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();
if (!isAdminUser()) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$db = new DatabaseService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security validation failed.'];
        redirect('settings.php');
    }

    $action = $_POST['action'] ?? 'attendance';
    if ($action === 'attendance') {
        $entryTime = trim((string) ($_POST['attendance_entry_time'] ?? ''));
        setSetting('attendance_entry_time', $entryTime, 'attendance');
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Attendance settings updated successfully.'];
        redirect('settings.php');
    }

    if ($action === 'add_leave_type') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = trim((string) ($_POST['code'] ?? ''));
        $quota = (float) ($_POST['quota'] ?? 0);
        $gender = in_array($_POST['gender'] ?? 'All', ['All', 'Female', 'Male'], true) ? $_POST['gender'] : 'All';

        if ($name === '' || $code === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Leave name and code are required.'];
            redirect('settings.php');
        }

        $db->execute(
            'INSERT INTO teacher_leave_types (name, code, quota, gender_restriction, is_active) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quota = VALUES(quota), gender_restriction = VALUES(gender_restriction), is_active = VALUES(is_active)',
            [$name, $code, $quota, $gender, 1]
        );

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Leave type added/updated successfully.'];
        redirect('settings.php');
    }
}

$entryTime = (string) getSetting('attendance_entry_time', 'attendance', '09:30');
$leaveTypes = $db->fetchAll('SELECT * FROM teacher_leave_types ORDER BY name ASC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Teachers Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
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
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Attendance Settings</h4>
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                        <div class="mb-3">
                            <label class="form-label">Official Attendance Entry Time</label>
                            <input type="time" class="form-control" name="attendance_entry_time" value="<?= e($entryTime) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Leave Settings</h4>
                    <p class="text-muted">Manage leave types and quotas.</p>

                    <div class="mb-3">
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="action" value="add_leave_type">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
                                <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code" required></div>
                                <div class="col-md-2"><label class="form-label">Quota</label><input class="form-control" name="quota" type="number" step="0.5" value="0"></div>
                                <div class="col-md-2"><label class="form-label">Gender</label><select class="form-select" name="gender"><option>All</option><option>Female</option><option>Male</option></select></div>
                            </div>
                            <div class="mt-2 text-end"><button class="btn btn-primary">Add / Update Leave Type</button></div>
                        </form>
                    </div>

                    <?php if (!empty($leaveTypes)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Name</th><th>Code</th><th>Quota</th><th>Gender</th><th>Active</th></tr></thead>
                                <tbody>
                                    <?php foreach ($leaveTypes as $lt): ?>
                                        <tr>
                                            <td><?= e($lt['name']) ?></td>
                                            <td><?= e($lt['code']) ?></td>
                                            <td><?= e($lt['quota']) ?></td>
                                            <td><?= e($lt['gender_restriction']) ?></td>
                                            <td><?= $lt['is_active'] ? 'Yes' : 'No' ?></td>
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
</div>
</body>
</html>
