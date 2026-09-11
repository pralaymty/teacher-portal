<?php
require_once __DIR__ . '/app/bootstrap.php';

if (!empty($_SESSION['is_logged_in'])) {
    redirect('dashboard.php');
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#f5f7ff,#eef7ff); min-height:100vh; }
        .login-card { max-width: 460px; margin: 8vh auto; border:0; border-radius: 24px; box-shadow: 0 20px 60px rgba(15,23,42,.10); }
        .brand-badge { width:64px; height:64px; border-radius:18px; background: linear-gradient(135deg,#0d6efd,#2ec4b6); }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card login-card">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center mb-4">
                    <div class="brand-badge text-white d-flex align-items-center justify-content-center me-3">
                        <i class="bi bi-mortarboard-fill fs-3"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0">Teacher Portal</h2>
                        <p class="text-muted mb-0">Teachers Employee Portal</p>
                    </div>
                </div>

                <?php $flash = $_SESSION['flash'] ?? null; if ($flash): unset($_SESSION['flash']); ?>
                    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</body>
<script>
    // Ask for location permission on first visit to the site
    (function () {
        try {
            if (typeof window !== 'undefined' && navigator && navigator.geolocation) {
                const key = 'tp_loc_permission_asked';
                if (!localStorage.getItem(key)) {
                    // trigger permission prompt; we don't need the result here
                    navigator.geolocation.getCurrentPosition(function () {}, function () {}, { timeout: 5000 });
                    localStorage.setItem(key, '1');
                }
            }
        } catch (e) {
            // ignore
        }
    })();
</script>
</html>
