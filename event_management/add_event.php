<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../audit_log.php';
p12RequireAdmin();

$csrf = csrfToken();
$errors = [];
$values = ['event_name' => '', 'description' => '', 'event_date' => '', 'event_time' => '', 'venue' => '', 'status' => 'Upcoming'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hasValidCsrfToken()) {
        $errors[] = 'The form expired. Refresh the page and try again.';
    }
    [$values, $fieldErrors] = p12ValidateEventInput($_POST);
    $errors = array_merge($errors, $fieldErrors);
    [$posterPath, $posterError] = p12StorePoster($_FILES['poster'] ?? [], false);
    if ($posterError !== null) {
        $errors[] = $posterError;
    }

    if ($errors === []) {
        try {
            runAuditedAdminMutation(
                $mysqli,
                'event.create',
                function () use ($mysqli, $values, $posterPath): int {
                    $statement = $mysqli->prepare(
                        'INSERT INTO practical12_events
                            (event_name, description, event_date, event_time, venue, status, poster_path)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $statement->bind_param(
                        'sssssss',
                        $values['event_name'],
                        $values['description'],
                        $values['event_date'],
                        $values['event_time'],
                        $values['venue'],
                        $values['status'],
                        $posterPath
                    );
                    $statement->execute();
                    $statement->close();
                    return (int) $mysqli->insert_id;
                },
                static fn (int $eventId): string => 'Created Practical 12 event #' . $eventId . '.'
            );
            p12SetFlash('success', 'Event created successfully.');
            p12Redirect('events.php');
        } catch (mysqli_sql_exception $exception) {
            if ($posterPath !== null && !p12RemoveStoredPosterFile($posterPath)) {
                error_log('Practical 12 could not remove a poster after an unsuccessful event insert.');
            }
            error_log('Practical 12 event insert failed: ' . $exception->getMessage());
            $errors[] = 'The event could not be saved. Please try again.';
        }
    } elseif ($posterPath !== null) {
        if (!p12RemoveStoredPosterFile($posterPath)) {
            error_log('Practical 12 could not remove a poster after event validation failed.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event | StudentHub Practical 12</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="p12-page p12-dashboard-page">
<main class="p12-shell p12-form-shell">
    <header class="p12-topbar"><a class="p12-brand" href="dashboard.php"><span class="p12-brand-mark">SH</span>StudentHub</a><a href="events.php">Back to events</a></header>
    <section class="p12-panel p12-form-panel">
        <p class="p12-eyebrow">Practical 12 · Events</p><h1>Add event</h1>
        <?php foreach ($errors as $error): ?><p class="p12-alert p12-alert-error" role="alert"><?= p12Escape($error) ?></p><?php endforeach; ?>
        <form method="post" action="add_event.php" enctype="multipart/form-data" class="p12-form">
            <input type="hidden" name="csrf_token" value="<?= p12Escape($csrf) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
            <label>Event name<input name="event_name" maxlength="150" required value="<?= p12Escape($values['event_name']) ?>"></label>
            <label>Description<textarea name="description" rows="5" maxlength="60000" required><?= p12Escape($values['description']) ?></textarea></label>
            <div class="p12-form-grid"><label>Date<input name="event_date" type="date" required value="<?= p12Escape($values['event_date']) ?>"></label><label>Time<input name="event_time" type="time" required value="<?= p12Escape($values['event_time']) ?>"></label></div>
            <label>Venue<input name="venue" maxlength="150" required value="<?= p12Escape($values['venue']) ?>"></label>
            <label>Status<select name="status" required><?php foreach (['Upcoming', 'Completed', 'Cancelled'] as $option): ?><option value="<?= p12Escape($option) ?>" <?= $values['status'] === $option ? 'selected' : '' ?>><?= p12Escape($option) ?></option><?php endforeach; ?></select></label>
            <label>Poster (optional; JPG, JPEG, PNG, or WebP; max 2 MB)<input name="poster" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
            <p class="p12-muted">Upload a real image file. The server checks extension, MIME type, image data, and file size.</p>
            <div class="p12-actions"><button class="p12-button p12-button-primary" type="submit">Create event</button><a class="p12-button p12-button-secondary" href="events.php">Cancel</a></div>
        </form>
    </section>
</main>
</body>
</html>