<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/portal.css?v=<?= filemtime(__DIR__ . '/../../assets/portal.css') ?>" rel="stylesheet">
<meta name="location-csrf" content="<?= e(csrfToken()) ?>">
<?php if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'location-required.php'): ?>
<style>html:not(.location-ready) body { visibility: hidden; }</style>
<noscript><meta http-equiv="refresh" content="0;url=location-required.php"></noscript>
<?php endif; ?>
<script src="assets/location-access.js?v=<?= filemtime(__DIR__ . '/../../assets/location-access.js') ?>" defer></script>
