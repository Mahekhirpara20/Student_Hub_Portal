<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
requireAuthenticatedSession();

$authorized = $_SESSION['role'] === 'Admin';
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
    <title>Admin Dashboard | StudentHub</title>
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
        <nav class="dashboard-role-menu" aria-label="Admin navigation">
            <a class="button button-secondary" href="dashboard.php">Dashboard</a>
            <a class="button button-secondary" href="student_management.php">Student records</a>
            <a class="button button-secondary" href="audit_logs.php">Audit logs</a>
            <a class="button button-secondary" href="profile.php">Profile</a>
        </nav>
    <?php endif; ?>
    <?php if (!$authorized): ?>
        <section class="notice notice-error" role="alert">
            <h1>Unauthorized Access</h1>
            <p>This page is available to Admin accounts only.</p>
            <a href="student_dashboard.php">Go to your Student dashboard</a>
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
        <p class="eyebrow">Admin portal</p>
        <h1>Welcome, <?= htmlspecialchars((string) ($_SESSION['full_name'] ?? $_SESSION['email']), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="dashboard-copy">Signed in as <strong>Admin</strong>. Administrative access is verified on the server for every request.</p>
        <a class="button button-primary" href="dashboard.php">Open admin dashboard</a>
        <a class="button button-primary" href="student_management.php">Manage student records</a>
        <a class="button button-secondary" href="audit_logs.php">View audit logs</a>
    <?php endif; ?>
</main>
<script src="js/profile-sync.js"></script>
</body>
</html>
