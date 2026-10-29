<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
requireAuthenticatedSession();

$authorized = $_SESSION['role'] === 'Student';
$csrf = csrfToken();
$sessionDiagnostic = null;
if ($authorized) {
    require_once __DIR__ . '/session_regeneration_diagnostic.php';
    $sessionDiagnostic = consumeSessionRegenerationDiagnostic();
}
if (!$authorized) {
    http_response_code(403);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | StudentHub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/practical14.css">
</head>
<body class="dashboard-page">
<main class="dashboard-card">
    <header class="dashboard-header">
        <a class="auth-brand" href="index.html">StudentHub</a>
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button class="button button-secondary" type="submit">Log out</button>
        </form>
    </header>
    <?php if ($authorized): ?>
        <nav class="dashboard-role-menu" aria-label="Student navigation">
            <a class="button button-secondary" href="student_dashboard.php">Student home</a>
            <a class="button button-secondary" href="events.html">Events</a>
            <a class="button button-secondary" href="profile.php">Profile</a>
        </nav>
    <?php endif; ?>
    <?php if (!$authorized): ?>
        <section class="notice notice-error" role="alert">
            <h1>Unauthorized Access</h1>
            <p>This page is available to Student accounts only.</p>
            <a href="admin_dashboard.php">Go to your Admin dashboard</a>
        </section>
    <?php else: ?>
        <?php if ($sessionDiagnostic !== null): ?>
            <section class="notice notice-success" role="status">
                <h2>Practical 10 Session Check</h2>
                <p>Authentication successful: <strong><?= $sessionDiagnostic['authentication_successful'] ? 'Yes' : 'No' ?></strong></p>
                <p>Previous session ID recorded: <strong><?= $sessionDiagnostic['previous_session_id_recorded'] ? 'Yes' : 'No' ?></strong></p>
                <p>Session ID regenerated: <strong><?= $sessionDiagnostic['session_id_regenerated'] ? 'Yes' : 'No' ?></strong></p>
            </section>
        <?php endif; ?>
        <p class="eyebrow">Student portal</p>
        <h1>Welcome, <?= htmlspecialchars((string) ($_SESSION['full_name'] ?? $_SESSION['email']), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="dashboard-copy">Your Student account is signed in and verified. Administrative tools are restricted to Admin accounts.</p>
        <a class="button button-primary" href="events.html">Browse events</a>
    <?php endif; ?>
</main>
<script src="js/profile-sync.js"></script>
</body>
</html>
