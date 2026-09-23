<?php
require_once __DIR__ . '/app/bootstrap.php';
unset($_SESSION['location_verified_at']);
$locationNext = LocationAccessService::returnPage(is_string($_GET['next'] ?? null) ? $_GET['next'] : 'index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable Location | NNV-Teachers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/app/views/portal-head.php'; ?>
</head>
<body class="login-page">
    <main class="container py-5" id="location-gate" data-next="<?= e($locationNext) ?>">
        <div class="card login-card"><div class="card-body p-4 p-md-5">
            <img src="assets/nnv-logo.jpg" class="brand-logo mb-3" width="80" height="80" alt="NNV school logo">
            <h1 class="brand-title">Location must be enabled</h1>
            <p>Allow location access to open the Teachers Portal. This is required for all users, including administrators.</p>
            <p id="location-status" role="status" aria-live="polite">Checking your location…</p>
            <p class="small text-muted">If access was blocked, open your browser’s site permissions, set Location to Allow, and enable your device’s location services. Then try again.</p>
            <button id="location-retry" class="btn btn-primary w-100" type="button">Enable location / Try again</button>
            <noscript><p class="alert alert-warning mt-3">JavaScript must be enabled to check your location and enter the portal.</p></noscript>
        </div></div>
    </main>
</body>
</html>
