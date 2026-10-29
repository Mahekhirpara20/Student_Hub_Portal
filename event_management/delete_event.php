<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../audit_log.php';
p12RequireAdmin();

$idValue = $_GET['id'] ?? $_POST['event_id'] ?? null;
$eventId = filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($eventId === false) {
    http_response_code(404);
    exit('Event not found.');
}

try {
    $event = p12FetchEvent($mysqli, $eventId);
} catch (mysqli_sql_exception $exception) {
    error_log('Practical 12 event lookup for deletion failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The event could not be loaded. Please try again later.');
}
if ($event === null) {
    http_response_code(404);
    exit('Event not found.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hasValidCsrfToken()) {
        http_response_code(403);
        exit('Invalid request. Refresh the event list and try again.');
    }

    try {
        $deletedEventId = runAuditedAdminMutation(
            $mysqli,
            'event.delete',
            function () use ($mysqli, $eventId): ?int {
                $statement = $mysqli->prepare('DELETE FROM practical12_events WHERE event_id = ?');
                $statement->bind_param('i', $eventId);
                $statement->execute();
                $deleted = $statement->affected_rows === 1;
                $statement->close();
                return $deleted ? $eventId : null;
            },
            static fn (int $deletedId): string => 'Deleted Practical 12 event #' . $deletedId . '.'
        );
        if ($deletedEventId === null) {
            p12SetFlash('error', 'The event was not found or could not be deleted.');
        } else {
            try {
                $posterRemoved = p12DeletePosterIfUnused(
                    $mysqli,
                    $event['poster_path'] === null ? null : (string) $event['poster_path']
                );
            } catch (mysqli_sql_exception $cleanupException) {
                error_log('Practical 12 deleted event poster cleanup failed: ' . $cleanupException->getMessage());
                $posterRemoved = false;
            }
            p12SetFlash(
                $posterRemoved ? 'success' : 'warning',
                $posterRemoved
                    ? 'Event deleted successfully.'
                    : 'Event deleted, but its poster could not be removed.'
            );
        }
        p12Redirect('events.php');
    } catch (mysqli_sql_exception $exception) {
        error_log('Practical 12 event delete failed: ' . $exception->getMessage());
        p12SetFlash('error', 'The event could not be deleted. Please try again.');
        p12Redirect('events.php');
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Event | StudentHub Practical 12</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="p12-page p12-dashboard-page">
<main class="p12-shell p12-form-shell">
    <header class="p12-topbar"><a class="p12-brand" href="dashboard.php"><span class="p12-brand-mark">SH</span>StudentHub</a><a href="events.php">Back to events</a></header>
    <section class="p12-panel p12-confirm-panel">
        <p class="p12-eyebrow">Practical 12 · Confirmation</p><h1>Delete event?</h1>
        <p>Are you sure you want to delete <strong><?= p12Escape($event['event_name']) ?></strong>?</p>
        <p class="p12-muted">This removes the Practical 12 event record and its managed poster file. It does not alter Practical 8 events or registrations.</p>
        <form method="post" action="delete_event.php?id=<?= p12Escape($eventId) ?>" class="p12-actions" onsubmit="return window.confirm('Delete this event? This action cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?= p12Escape(csrfToken()) ?>">
            <input type="hidden" name="event_id" value="<?= p12Escape($eventId) ?>">
            <button class="p12-button p12-button-danger" type="submit">Confirm delete</button>
            <a class="p12-button p12-button-secondary" href="events.php">Cancel</a>
        </form>
    </section>
</main>
</body>
</html>