<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
p12RequireAdmin();

$counts = ['Upcoming' => 0, 'Completed' => 0, 'Cancelled' => 0];
try {
    $statement = $mysqli->prepare(
        'SELECT status, COUNT(*) AS event_count
         FROM practical12_events GROUP BY status'
    );
    $statement->execute();
    $result = $statement->get_result();
    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['status'], $counts)) {
            $counts[$row['status']] = (int) $row['event_count'];
        }
    }
    $statement->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Practical 12 dashboard query failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Event information is temporarily unavailable. Import database.sql and try again.');
}

$flash = p12ConsumeFlash();
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Admin Dashboard | StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="p12-page p12-dashboard-page">
<main class="p12-shell">
    <header class="p12-topbar">
        <a class="p12-brand" href="dashboard.php"><span class="p12-brand-mark">SH</span>StudentHub</a>
        <div class="p12-user-tools">
            <span><?= p12Escape($_SESSION['full_name'] ?? $_SESSION['email']) ?> · Admin</span>
            <form method="post" action="../logout.php">
                <input type="hidden" name="csrf_token" value="<?= p12Escape($csrf) ?>">
                <button class="p12-button p12-button-secondary" type="submit">Log out</button>
            </form>
        </div>
    </header>
    <div class="p12-page-heading">
        <div><p class="p12-eyebrow">Practical 12</p><h1>Event Management</h1><p class="p12-muted">Create, update, and manage college events.</p></div>
        <a class="p12-button p12-button-primary" href="add_event.php">Add event</a>
    </div>
    <?php if ($flash !== null): ?>
        <p class="p12-alert p12-alert-<?= p12Escape($flash['type']) ?>" role="status"><?= p12Escape($flash['message']) ?></p>
    <?php endif; ?>
    <section class="p12-stat-grid" aria-label="Event totals">
        <?php foreach ($counts as $status => $count): ?>
            <article class="p12-stat-card"><span><?= p12Escape($status) ?></span><strong><?= p12Escape($count) ?></strong></article>
        <?php endforeach; ?>
    </section>
    <section class="p12-panel p12-dashboard-actions">
        <div><h2>Event records</h2><p class="p12-muted">View events, search by name, filter by status, and manage posters.</p></div>
        <a class="p12-button p12-button-primary" href="events.php">Open event list</a>
    </section>
    <p class="p12-footer-link"><a href="../admin_dashboard.php">Return to main StudentHub Admin Dashboard</a></p>
</main>
</body>
</html>