<?php
$portalPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$portalLinks = [
    ['dashboard.php', 'Overview', 'bi-grid-1x2'],
    ['attendance.php', 'Attendance', 'bi-calendar-check'],
    ['leave.php', 'My Leave', 'bi-calendar2-minus'],
    ['profile.php', 'Profile', 'bi-person'],
];
if (isAdminUser()) {
    $portalLinks = array_merge($portalLinks, [
        ['admin.php', 'Administration', 'bi-sliders'],
        ['teachers.php', 'Teachers', 'bi-people'],
        ['leave-admin.php', 'Approvals', 'bi-check2-square'],
        ['settings.php', 'Settings', 'bi-gear'],
    ]);
}
$portalActive = ['teacher-attendance.php' => 'teachers.php', 'leave-history.php' => isAdminUser() ? 'leave-admin.php' : 'leave.php'];
?>
<a class="skip-link" href="#portal-content">Skip to content</a>
<header class="portal-header">
    <div class="container portal-masthead">
        <a class="portal-brand" href="dashboard.php">
            <img src="assets/nnv-logo.jpg" width="44" height="44" alt="NNV school logo">
            <span>NNV-Teachers Portal<small>Namkhana Narayan Vidyamandir</small></span>
        </a>
        <div class="portal-utilities">
            <span class="portal-date"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('d M Y') ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-secondary" title="Sign out of the portal"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Sign out</span></a>
        </div>
    </div>
    <nav class="container portal-navigation" aria-label="Main navigation">
        <?php foreach ($portalLinks as [$href, $label, $icon]): $active = ($portalActive[$portalPage] ?? $portalPage) === $href; ?>
            <a href="<?= e($href) ?>" class="<?= $active ? 'active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?>><i class="bi <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</header>
