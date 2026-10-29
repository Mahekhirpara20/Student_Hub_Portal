<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
startSecureSession();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
if (!hasValidCsrfToken()) {
    http_response_code(403);
    exit('Invalid request.');
}

destroyCurrentSession();
header('Location: login.php?logged_out=1', true, 303);
exit;