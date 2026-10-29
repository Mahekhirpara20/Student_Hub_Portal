<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
requireAuthenticatedSession();

$csrf = csrfToken();
$fullName = $_SESSION['full_name'] ?? 'your account';
if (!is_string($fullName) || $fullName === '') {
    $fullName = 'your account';
}
$dashboardUrl = $_SESSION['role'] === 'Admin'
    ? 'admin_dashboard.php'
    : 'student_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign out | StudentHub</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="auth-brand" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>">StudentHub</a>
    <h1>Sign out?</h1>
    <p class="auth-intro">
        Are you sure you want to sign out, <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>?
    </p>
    <form action="logout.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <button class="button button-primary button-full" type="submit">Sign out</button>
    </form>
    <p class="auth-footer"><a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>">Cancel and return to dashboard</a></p>
</main>
</body>
</html>
