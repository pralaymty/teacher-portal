<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

$db = new DatabaseService();
$userId = (int) $_SESSION['user_id'];
$user = $db->fetchOne('SELECT * FROM user WHERE id = ? LIMIT 1', [$userId]);

if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'User profile not found.'];
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security validation failed.'];
        redirect('profile.php');
    }

    $dob = trim((string) ($_POST['dob'] ?? ''));
    $designation = trim((string) ($_POST['designation'] ?? ''));
    $qualification = trim((string) ($_POST['academic_qualification'] ?? ''));
    $dateOfJoining = trim((string) ($_POST['date_of_joining'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $emailId = trim((string) ($_POST['email_id'] ?? ''));
    $mobile = trim((string) ($_POST['mobile_number'] ?? ''));

    if ($dob === '' || $designation === '' || $qualification === '' || $dateOfJoining === '' || $subject === '' || $emailId === '' || $mobile === '') {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'All employee information fields are required.'];
        redirect('profile.php');
    }

    $db->execute(
        'UPDATE user SET dob = ?, designation = ?, academic_qualification = ?, date_of_joining = ?, subject = ?, email = ?, phone = ? WHERE id = ?',
        [$dob, $designation, $qualification, $dateOfJoining, $subject, $emailId, $mobile, $userId]
    );

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully.'];
    redirect('profile.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | NNV-Teachers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/app/views/portal-header.php'; ?>
<main id="portal-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">My Profile</h2>
            <p class="text-muted mb-0">Employee information</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
    </div>
    <?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="dob" value="<?= e((string) ($user['dob'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Designation</label><input type="text" class="form-control" name="designation" value="<?= e((string) ($user['designation'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Academic Qualification</label><input type="text" class="form-control" name="academic_qualification" value="<?= e((string) ($user['academic_qualification'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Date of Joining</label><input type="date" class="form-control" name="date_of_joining" value="<?= e((string) ($user['date_of_joining'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Subject</label><input type="text" class="form-control" name="subject" value="<?= e((string) ($user['subject'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Email ID</label><input type="email" class="form-control" name="email_id" value="<?= e((string) ($user['email'] ?? '')) ?>" required></div>
                    <div class="col-md-12"><label class="form-label">Mobile Number</label><input type="tel" class="form-control" name="mobile_number" value="<?= e((string) ($user['phone'] ?? '')) ?>" required></div>
                </div>
                <div class="mt-4 text-end"><button type="submit" class="btn btn-primary">Save Profile</button></div>
            </form>
        </div>
    </div>
</main>
</body>
</html>
