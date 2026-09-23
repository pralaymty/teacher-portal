<?php

require_once __DIR__ . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    jsonResponse(false, 'POST required.');
}
if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals(csrfToken(), $_POST['csrf_token'])) {
    http_response_code(403);
    jsonResponse(false, 'Please reload the page and try again.');
}
unset($_SESSION['location_verified_at']);
if (($_POST['action'] ?? '') === 'revoke') {
    jsonResponse(true, 'Location access cleared.');
}
if (($_POST['action'] ?? '') !== 'verify' || !LocationAccessService::validReading($_POST)) {
    http_response_code(422);
    jsonResponse(false, 'A valid location reading is required.');
}
$_SESSION['location_verified_at'] = time();
jsonResponse(true, 'Location enabled.');
