<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
sendNoCacheHeaders();
startSecureSession();

if (isset($_SESSION['user_id'])) {
    $lastActivity = $_SESSION['last_activity'] ?? null;
    $role = $_SESSION['role'] ?? null;

    if (is_int($lastActivity) && time() - $lastActivity < SESSION_TIMEOUT_SECONDS) {
        if ($role === 'Admin') {
            header('Location: admin_dashboard.php');
            exit;
        }
        if ($role === 'Student') {
            header('Location: student_dashboard.php');
            exit;
        }
    }

    destroyCurrentSession();
    startSecureSession();
}

$errorMessage = isset($_GET['error']) ? 'Invalid email or password.' : null;
$timeoutMessage = isset($_GET['timeout']) ? 'Your session expired due to inactivity. Please sign in again.' : null;
$logoutMessage = isset($_GET['logged_out']) ? 'You have been logged out successfully.' : null;
$unauthorizedMessage = isset($_GET['unauthorized']) ? 'You are not authorized to access that page.' : null;
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | StudentHub</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="auth-brand" href="index.html">StudentHub</a>
    <h1>Welcome back</h1>
    <p class="auth-intro">Sign in to continue to your StudentHub account.</p>

    <?php if ($errorMessage !== null): ?>
        <p class="notice notice-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($timeoutMessage !== null): ?>
        <p class="notice notice-warning" role="status"><?= htmlspecialchars($timeoutMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($logoutMessage !== null): ?>
        <p class="notice notice-success" role="status"><?= htmlspecialchars($logoutMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($unauthorizedMessage !== null): ?>
        <p class="notice notice-error" role="alert"><?= htmlspecialchars($unauthorizedMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form action="authenticate.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" autocomplete="username" required maxlength="254">
        </div>
        <div class="form-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="button button-primary button-full" type="submit">Sign in</button>
    </form>
    <p class="auth-footer">New to StudentHub? <a href="register.php">Create an account</a></p>
</main>
</body>
</html>
