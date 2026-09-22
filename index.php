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
    <title>NNV-Teachers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body class="login-page">
    <div class="container py-5">
        <div class="card login-card">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="assets/nnv-logo.jpg" class="brand-logo mb-3" width="80" height="80" alt="Namkhana Narayan Vidyamandir logo">
                    <h1 class="brand-title fw-bold mb-0">NNV-Teachers Portal</h1>
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
