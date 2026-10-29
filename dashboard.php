<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
requireAuthenticatedSession();

$role = $_SESSION['role'];
$isAdmin = $role === 'Admin';
$name = $_SESSION['full_name'] ?? $_SESSION['email'];
$csrf = csrfToken();
$totals = ['users' => null, 'students' => null, 'events' => null];
$registrations = [];
$databaseError = false;

if (!$isAdmin) {
    http_response_code(403);
} else {
    try {
        require_once __DIR__ . '/db.php';

        foreach (['users', 'students', 'events'] as $table) {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM {$table}");
            $statement->execute();
            $totals[$table] = (int) $statement->fetchColumn();
        }

        $registrationStatement = $pdo->prepare(
            'SELECT r.registration_id, r.registration_date,
                    s.full_name, s.email, s.branch, s.semester, e.event_name
             FROM registrations AS r
             INNER JOIN students AS s ON s.student_id = r.student_id
             INNER JOIN events AS e ON e.event_id = r.event_id
             ORDER BY r.registration_date DESC, r.registration_id DESC
             LIMIT 10'
        );
        $registrationStatement->execute();
        $registrations = $registrationStatement->fetchAll();
    } catch (RuntimeException $exception) {
        error_log('StudentHub dashboard query failed: ' . $exception->getMessage());
        $databaseError = true;
        http_response_code(503);
    }
}

function dashboardEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isAdmin ? 'Admin Dashboard' : 'Access denied' ?> | StudentHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #f4f7fc; color: #172554; }
        .navbar-brand { font-weight: 800; letter-spacing: -.03em; }
        .page-shell { max-width: 1180px; }
        .welcome-panel, .stat-card, .data-panel {
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }
        .welcome-panel { padding: clamp(1.25rem, 4vw, 2rem); }
        .stat-card { height: 100%; padding: 1.4rem; }
        .stat-label { color: #64748b; font-size: .9rem; font-weight: 600; }
        .stat-value { display: block; margin-top: .35rem; color: #1d4ed8; font-size: 2.2rem; font-weight: 800; }
        .table th { color: #475569; font-size: .82rem; text-transform: uppercase; letter-spacing: .04em; }
        .table td, .table th { padding: .85rem .75rem; vertical-align: middle; }
        @media (max-width: 576px) {
            .table td, .table th { padding: .65rem .5rem; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container page-shell">
        <a class="navbar-brand" href="dashboard.php">StudentHub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation"
                aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($isAdmin): ?>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="student_management.php">Student records</a></li>
                    <li class="nav-item"><a class="nav-link" href="audit_logs.php">Audit logs</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Admin portal</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard.php">Account overview</a></li>
                    <li class="nav-item"><a class="nav-link" href="student_dashboard.php">Student portal</a></li>
                    <li class="nav-item"><a class="nav-link" href="events.html">Events</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3 text-white">
                <span><?= dashboardEscape($role) ?></span>
                <form action="logout.php" method="post" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit">Log out</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container page-shell py-4 py-lg-5">
    <section class="welcome-panel mb-4">
        <p class="text-primary text-uppercase fw-bold small mb-2"><?= $isAdmin ? 'Administration' : 'Restricted area' ?></p>
        <h1 class="h2 mb-2"><?= $isAdmin ? 'Admin Dashboard' : 'Access denied' ?></h1>
        <p class="text-secondary mb-0">Welcome, <?= dashboardEscape($name) ?>. Signed in as <?= dashboardEscape($role) ?>.</p>
    </section>

    <?php if (!$isAdmin): ?>
        <section class="data-panel p-4" role="alert">
            <h2 class="h4 text-danger">403 Forbidden</h2>
            <p class="text-secondary">This dashboard is available to Admin accounts only.</p>
            <a class="btn btn-primary" href="student_dashboard.php">Go to student portal</a>
        </section>
    <?php else: ?>
        <?php if ($databaseError): ?>
            <div class="alert alert-warning" role="alert">
                Dashboard data is temporarily unavailable. Please check the database service and try again.
            </div>
        <?php else: ?>
            <section class="row g-3 mb-4" aria-label="Dashboard statistics">
                <div class="col-12 col-md-4">
                    <article class="stat-card">
                        <span class="stat-label">Total users</span>
                        <strong class="stat-value"><?= dashboardEscape($totals['users']) ?></strong>
                    </article>
                </div>
                <div class="col-12 col-md-4">
                    <article class="stat-card">
                        <span class="stat-label">Total students</span>
                        <strong class="stat-value"><?= dashboardEscape($totals['students']) ?></strong>
                    </article>
                </div>
                <div class="col-12 col-md-4">
                    <article class="stat-card">
                        <span class="stat-label">Total events</span>
                        <strong class="stat-value"><?= dashboardEscape($totals['events']) ?></strong>
                    </article>
                </div>
            </section>

            <section class="data-panel p-3 p-lg-4" aria-labelledby="registrations-heading">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-1" id="registrations-heading">Latest student registrations</h2>
                        <p class="text-secondary small mb-0">Most recent event registrations, newest first.</p>
                    </div>
                    <span class="badge text-bg-primary"><?= count($registrations) ?> shown</span>
                </div>
                <?php if ($registrations === []): ?>
                    <p class="text-secondary mb-0 py-3">There are no student registrations to display yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th scope="col">Registered</th>
                                <th scope="col">Student</th>
                                <th scope="col">Email</th>
                                <th scope="col">Branch / semester</th>
                                <th scope="col">Event</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($registrations as $registration): ?>
                                <tr>
                                    <td><?= dashboardEscape($registration['registration_date']) ?></td>
                                    <td><?= dashboardEscape($registration['full_name']) ?></td>
                                    <td><?= dashboardEscape($registration['email']) ?></td>
                                    <td><?= dashboardEscape($registration['branch']) ?> / <?= dashboardEscape($registration['semester']) ?></td>
                                    <td><?= dashboardEscape($registration['event_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
