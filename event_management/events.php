<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
p12RequireAdmin();

$searchValue = $_GET['search'] ?? '';
$search = is_string($searchValue) ? trim($searchValue) : '';
$statusValue = $_GET['status'] ?? 'All';
$status = is_string($statusValue) ? $statusValue : 'All';
$fromValue = $_GET['from'] ?? '';
$fromDate = is_string($fromValue) ? trim($fromValue) : '';
$toValue = $_GET['to'] ?? '';
$toDate = is_string($toValue) ? trim($toValue) : '';
$errors = [];
$events = [];
$flash = p12ConsumeFlash();
$csrf = csrfToken();

if (strlen($search) > 150) {
    $errors[] = 'Search must be 150 characters or fewer.';
    $search = '';
}
if (!in_array($status, ['All', 'Upcoming', 'Completed', 'Cancelled'], true)) {
    $errors[] = 'The selected event status is invalid.';
    $status = 'All';
}
foreach (['from' => &$fromDate, 'to' => &$toDate] as $label => &$filterDate) {
    if ($filterDate !== '') {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $filterDate);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $filterDate) {
            $errors[] = 'Enter a valid ' . ($label === 'from' ? 'start' : 'end') . ' date.';
            $filterDate = '';
        }
    }
}
unset($filterDate);
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $errors[] = 'The start date must be on or before the end date.';
    $fromDate = '';
    $toDate = '';
}

try {
    $pattern = '%' . $search . '%';
    $statement = $mysqli->prepare(
        'SELECT event_id, event_name, description, event_date, event_time, venue, status, poster_path
         FROM practical12_events
         WHERE (event_name LIKE ? OR venue LIKE ?)
           AND (? = \'All\' OR status = ?)
           AND (? = \'\' OR event_date >= ?)
           AND (? = \'\' OR event_date <= ?)
         ORDER BY event_date DESC, event_time DESC, event_id DESC'
    );
    $statement->bind_param(
        'ssssssss',
        $pattern,
        $pattern,
        $status,
        $status,
        $fromDate,
        $fromDate,
        $toDate,
        $toDate
    );
    $statement->execute();
    $events = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Practical 12 event list failed: ' . $exception->getMessage());
    $errors[] = 'Events could not be loaded. Import database.sql and try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events | StudentHub Practical 12</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="p12-page p12-dashboard-page">
<main class="p12-shell">
    <header class="p12-topbar">
        <a class="p12-brand" href="dashboard.php"><span class="p12-brand-mark">SH</span>StudentHub</a>
        <div class="p12-user-tools"><span>Admin Portal</span><a href="dashboard.php">Dashboard</a></div>
    </header>
    <div class="p12-page-heading">
        <div><p class="p12-eyebrow">Practical 12</p><h1>Events</h1><p class="p12-muted">Search, filter, and manage event records.</p></div>
        <a class="p12-button p12-button-primary" href="add_event.php">Add event</a>
    </div>
    <?php if ($flash !== null): ?>
        <p class="p12-alert p12-alert-<?= p12Escape($flash['type']) ?>" role="status"><?= p12Escape($flash['message']) ?></p>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <p class="p12-alert p12-alert-error" role="alert"><?= p12Escape($error) ?></p>
    <?php endforeach; ?>
    <section class="p12-panel">
        <form method="get" action="events.php" class="p12-filter-bar">
            <label>Search event or venue<input type="search" name="search" maxlength="150" value="<?= p12Escape($search) ?>" placeholder="Event name or venue"></label>
            <label>From date<input type="date" name="from" value="<?= p12Escape($fromDate) ?>"></label>
            <label>To date<input type="date" name="to" value="<?= p12Escape($toDate) ?>"></label>
            <label>Status<select name="status">
                <?php foreach (['All', 'Upcoming', 'Completed', 'Cancelled'] as $option): ?>
                    <option value="<?= p12Escape($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= p12Escape($option === 'All' ? 'All statuses' : $option) ?></option>
                <?php endforeach; ?>
            </select></label>
            <div class="p12-actions"><button class="p12-button p12-button-primary" type="submit">Search / filter</button><a class="p12-button p12-button-secondary" href="events.php">Clear</a></div>
        </form>
        <?php if ($events === []): ?>
            <p class="p12-empty">No events match the current search and status filter.</p>
        <?php else: ?>
            <div class="p12-table-wrap">
                <table class="p12-table">
                    <thead><tr><th scope="col">Poster</th><th scope="col">Event</th><th scope="col">Date &amp; time</th><th scope="col">Venue</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?php if (is_string($event['poster_path']) && preg_match('#^uploads/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $event['poster_path']) === 1): ?><img class="p12-poster-thumb" src="<?= p12Escape($event['poster_path']) ?>" alt="Poster for <?= p12Escape($event['event_name']) ?>"><?php else: ?><span class="p12-no-poster">No poster</span><?php endif; ?></td>
                            <td><strong><?= p12Escape($event['event_name']) ?></strong><p class="p12-event-description"><?= nl2br(p12Escape($event['description'])) ?></p></td>
                            <td><?= p12Escape($event['event_date']) ?><br><?= p12Escape(substr((string) $event['event_time'], 0, 5)) ?></td>
                            <td><?= p12Escape($event['venue']) ?></td>
                            <td><span class="p12-status p12-status-<?= strtolower(p12Escape($event['status'])) ?>"><?= p12Escape($event['status']) ?></span></td>
                            <td class="p12-row-actions"><a href="edit_event.php?id=<?= p12Escape($event['event_id']) ?>">Edit</a><a class="p12-delete-link" href="delete_event.php?id=<?= p12Escape($event['event_id']) ?>">Delete</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <p class="p12-footer-link"><a href="../admin_dashboard.php">Return to main StudentHub Admin Dashboard</a></p>
</main>
</body>
</html>