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
    error_log('Practical 12 event lookup for editing failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The event could not be loaded. Please try again later.');
}
if ($event === null) {
    http_response_code(404);
    exit('Event not found.');
}

$csrf = csrfToken();
$errors = [];
$values = $event;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hasValidCsrfToken()) {
        $errors[] = 'The form expired. Refresh the page and try again.';
    }
    [$values, $fieldErrors] = p12ValidateEventInput($_POST);
    $errors = array_merge($errors, $fieldErrors);
    [$newPosterPath, $posterError] = p12StorePoster($_FILES['poster'] ?? [], false);
    if ($posterError !== null) {
        $errors[] = $posterError;
    }

    if ($errors === []) {
        $posterPath = $newPosterPath ?? $event['poster_path'];
        try {
            runAuditedAdminMutation(
                $mysqli,
                'event.update',
                function () use ($mysqli, $values, $posterPath, $eventId): ?int {
                    $statement = $mysqli->prepare(
                        'UPDATE practical12_events
                         SET event_name = ?, description = ?, event_date = ?, event_time = ?,
                             venue = ?, status = ?, poster_path = ?
                         WHERE event_id = ?'
                    );
                    $statement->bind_param(
                        'sssssssi',
                        $values['event_name'],
                        $values['description'],
                        $values['event_date'],
                        $values['event_time'],
                        $values['venue'],
                        $values['status'],
                        $posterPath,
                        $eventId
                    );
                    $statement->execute();
                    $changed = $statement->affected_rows === 1;
                    $statement->close();
                    return $changed ? $eventId : null;
                },
                static fn (int $updatedEventId): string => 'Updated Practical 12 event #' . $updatedEventId . '.'
            );

            $posterCleanupFailed = false;
            if ($newPosterPath !== null && $event['poster_path'] !== null) {
                try {
                    $posterCleanupFailed = !p12DeletePosterIfUnused(
                        $mysqli,
                        (string) $event['poster_path']
                    );
                } catch (mysqli_sql_exception $cleanupException) {
                    error_log('Practical 12 replaced poster cleanup failed: ' . $cleanupException->getMessage());
                    $posterCleanupFailed = true;
                }
            }
            p12SetFlash(
                $posterCleanupFailed ? 'warning' : 'success',
                $posterCleanupFailed
                    ? 'Event updated, but its previous poster could not be removed.'
                    : 'Event updated successfully.'
            );
            p12Redirect('events.php');
        } catch (mysqli_sql_exception $exception) {
            if ($newPosterPath !== null && !p12RemoveStoredPosterFile($newPosterPath)) {
                error_log('Practical 12 could not remove a replacement poster after an unsuccessful event update.');
            }
            error_log('Practical 12 event update failed: ' . $exception->getMessage());
            $errors[] = 'The event could not be updated. Please try again.';
        }
    } elseif ($newPosterPath !== null) {
        if (!p12RemoveStoredPosterFile($newPosterPath)) {
            error_log('Practical 12 could not remove a replacement poster after event validation failed.');
        }
    }
    $values['poster_path'] = $event['poster_path'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event | StudentHub Practical 12</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="p12-page p12-dashboard-page">
<main class="p12-shell p12-form-shell">
    <header class="p12-topbar"><a class="p12-brand" href="dashboard.php"><span class="p12-brand-mark">SH</span>StudentHub</a><a href="events.php">Back to events</a></header>
    <section class="p12-panel p12-form-panel">
        <p class="p12-eyebrow">Practical 12 · Events</p><h1>Edit event #<?= p12Escape($eventId) ?></h1>
        <?php foreach ($errors as $error): ?><p class="p12-alert p12-alert-error" role="alert"><?= p12Escape($error) ?></p><?php endforeach; ?>
        <?php if (is_string($event['poster_path']) && preg_match('#^uploads/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $event['poster_path']) === 1): ?>
            <div class="p12-current-poster"><img src="<?= p12Escape($event['poster_path']) ?>" alt="Current event poster"><span>Current poster · Choose a new file to replace it.</span></div>
        <?php endif; ?>
        <form method="post" action="edit_event.php?id=<?= p12Escape($eventId) ?>" enctype="multipart/form-data" class="p12-form">
            <input type="hidden" name="csrf_token" value="<?= p12Escape($csrf) ?>">
            <input type="hidden" name="event_id" value="<?= p12Escape($eventId) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
            <label>Event name<input name="event_name" maxlength="150" required value="<?= p12Escape($values['event_name']) ?>"></label>
            <label>Description<textarea name="description" rows="5" maxlength="60000" required><?= p12Escape($values['description']) ?></textarea></label>
            <div class="p12-form-grid"><label>Date<input name="event_date" type="date" required value="<?= p12Escape($values['event_date']) ?>"></label><label>Time<input name="event_time" type="time" required value="<?= p12Escape(substr((string) $values['event_time'], 0, 5)) ?>"></label></div>
            <label>Venue<input name="venue" maxlength="150" required value="<?= p12Escape($values['venue']) ?>"></label>
            <label>Status<select name="status" required><?php foreach (['Upcoming', 'Completed', 'Cancelled'] as $option): ?><option value="<?= p12Escape($option) ?>" <?= $values['status'] === $option ? 'selected' : '' ?>><?= p12Escape($option) ?></option><?php endforeach; ?></select></label>
            <label>Replace poster (JPG, JPEG, PNG, or WebP; max 2 MB)<input name="poster" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
            <p class="p12-muted">Leave the poster field empty to retain the existing poster.</p>
            <div class="p12-actions"><button class="p12-button p12-button-primary" type="submit">Save changes</button><a class="p12-button p12-button-secondary" href="events.php">Cancel</a></div>
        </form>
    </section>
</main>
</body>
</html>