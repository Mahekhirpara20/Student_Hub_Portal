<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
requireAuthenticatedSession();

$isAdmin = $_SESSION['role'] === 'Admin';
$csrf = csrfToken();
$logs = [];
$databaseError = false;

if (!$isAdmin) {
    http_response_code(403);
} else {
    try {
        require_once __DIR__ . '/db.php';
        $statement = $pdo->prepare(
            'SELECT a.id, a.user_id, u.full_name, a.action, a.details,
                    a.ip_address, a.created_at
             FROM audit_logs AS a
             LEFT JOIN users AS u ON u.user_id = a.user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 100'
        );
        $statement->execute();
        $logs = $statement->fetchAll();
    } catch (RuntimeException $exception) {
        error_log('StudentHub audit log query failed: ' . $exception->getMessage());
        $databaseError = true;
        http_response_code(503);
    }
}

function auditLogsEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Logs | StudentHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">StudentHub</a>
        <div class="d-flex align-items-center gap-3">
            <?php if ($isAdmin): ?>
                <a class="link-light" href="dashboard.php">Admin dashboard</a>
            <?php endif; ?>
            <form action="logout.php" method="post" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= auditLogsEscape($csrf) ?>">
                <button class="btn btn-outline-light btn-sm" type="submit">Log out</button>
            </form>
        </div>
    </div>
</nav>
<main class="container py-4 py-lg-5">
    <?php if (!$isAdmin): ?>
        <section class="mx-auto rounded border bg-white p-4" style="max-width: 640px" role="alert">
            <h1 class="h3 text-danger">Access denied</h1>
            <p class="mb-0">Audit logs are available to administrators only.</p>
        </section>
    <?php else: ?>
        <header class="mb-4">
            <p class="text-primary text-uppercase fw-bold small mb-2">Administration</p>
            <h1 class="h2">Audit logs</h1>
            <p class="text-secondary mb-0">The 100 most recent recorded administrative changes.</p>
        </header>
        <?php if ($databaseError): ?>
            <div class="alert alert-warning" role="alert">
                Could not load audit records from <code>studenthub_db</code>.
                Confirm MySQL is running and that
                <code>migrations/004_create_audit_logs.sql</code> has been applied.
                The database error details are in the PHP error log.
            </div>
        <?php elseif ($logs === []): ?>
            <div class="alert alert-info" role="status">
                No administrative actions have been recorded yet.
            </div>
        <?php else: ?>
            <div class="table-responsive rounded border bg-white">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Administrator</th>
                        <th scope="col">Action</th>
                        <th scope="col">Details</th>
                        <th scope="col">IP address</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= auditLogsEscape($log['created_at']) ?></td>
                            <td><?= auditLogsEscape($log['full_name'] ?? ('User #' . $log['user_id'])) ?></td>
                            <td><?= auditLogsEscape($log['action']) ?></td>
                            <td><?= auditLogsEscape($log['details']) ?></td>
                            <td><?= auditLogsEscape($log['ip_address'] ?? 'Not available') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
