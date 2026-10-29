<?php
declare(strict_types=1);

require_once __DIR__ . '/../timeout.php';

function p12Escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function p12RequireAdmin(): void
{
    requireAuthenticatedSession('../login.php');

    if (($_SESSION['role'] ?? null) !== 'Admin') {
        http_response_code(403);
        exit('Forbidden. Practical 12 is available to Admin accounts only.');
    }
}

function p12IsLocalRequest(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

function p12SetFlash(string $type, string $message): void
{
    $_SESSION['p12_flash'] = [
        'type' => in_array($type, ['success', 'warning'], true) ? $type : 'error',
        'message' => $message,
    ];
}

function p12ConsumeFlash(): ?array
{
    $flash = $_SESSION['p12_flash'] ?? null;
    unset($_SESSION['p12_flash']);

    if (
        !is_array($flash)
        || !in_array($flash['type'] ?? null, ['success', 'error', 'warning'], true)
        || !is_string($flash['message'] ?? null)
    ) {
        return null;
    }

    return $flash;
}

function p12ValidateEventInput(array $source): array
{
    $fields = [];
    foreach (['event_name', 'description', 'event_date', 'event_time', 'venue', 'status'] as $key) {
        $value = $source[$key] ?? '';
        $fields[$key] = is_string($value) ? trim($value) : '';
    }

    $errors = [];
    $nameLength = preg_match_all('/./us', $fields['event_name']);
    if ($fields['event_name'] === '') {
        $errors[] = 'Event name is required.';
    } elseif ($nameLength === false || $nameLength > 150) {
        $errors[] = 'Event name must be 150 characters or fewer.';
    }

    if ($fields['description'] === '') {
        $errors[] = 'Event description is required.';
    } elseif (strlen($fields['description']) > 60000) {
        $errors[] = 'Event description is too long.';
    }

    $venueLength = preg_match_all('/./us', $fields['venue']);
    if ($fields['venue'] === '') {
        $errors[] = 'Venue is required.';
    } elseif ($venueLength === false || $venueLength > 150) {
        $errors[] = 'Venue must be 150 characters or fewer.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $fields['event_date']);
    if ($date === false || $date->format('Y-m-d') !== $fields['event_date']) {
        $errors[] = 'Enter a valid event date.';
    }

    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $fields['event_time']) !== 1) {
        $errors[] = 'Enter a valid event time.';
    }

    if (!in_array($fields['status'], ['Upcoming', 'Completed', 'Cancelled'], true)) {
        $errors[] = 'Select a valid event status.';
    }

    return [$fields, $errors];
}

function p12StorePoster(array $file, bool $required = false): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return $required
            ? [null, 'Choose a JPG, JPEG, PNG, or WebP poster (maximum 2 MB).']
            : [null, null];
    }

    if ($error !== UPLOAD_ERR_OK) {
        $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'The poster exceeds the 2 MB upload limit.'
            : 'The poster upload did not complete. Please try again.';
        return [null, $message];
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        return [null, 'The uploaded poster could not be verified.'];
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1) {
        return [null, 'The poster file is empty or unreadable.'];
    }
    if ($size > 2 * 1024 * 1024) {
        return [null, 'The poster must be 2 MB or smaller.'];
    }

    $originalName = $file['name'] ?? '';
    $extension = strtolower(pathinfo(is_string($originalName) ? $originalName : '', PATHINFO_EXTENSION));
    $allowedMimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
    if (!isset($allowedMimes[$extension])) {
        return [null, 'Only JPG, JPEG, PNG, and WebP posters are allowed.'];
    }

    try {
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($temporaryPath);
        $imageInfo = @getimagesize($temporaryPath);
    } catch (Throwable $exception) {
        error_log('Practical 12 poster validation failed: ' . $exception->getMessage());
        return [null, 'The poster could not be validated as an image.'];
    }
    if (!is_string($mimeType) || !in_array($mimeType, $allowedMimes[$extension], true)) {
        return [null, 'The poster extension and actual image type do not match. Upload a valid JPG, PNG, or WebP image.'];
    }
    if (!is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $mimeType) {
        return [null, 'The uploaded file is not a valid image.'];
    }

    $safeExtension = match ($mimeType) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };
    try {
        $filename = bin2hex(random_bytes(16)) . '.' . $safeExtension;
    } catch (Throwable $exception) {
        return [null, 'A safe poster filename could not be generated.'];
    }

    $uploadDirectory = __DIR__ . '/uploads';
    if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
        return [null, 'The poster upload folder is not writable.'];
    }

    if (!move_uploaded_file($temporaryPath, $uploadDirectory . '/' . $filename)) {
        return [null, 'The poster could not be saved. Check the upload folder permissions.'];
    }

    return ['uploads/' . $filename, null];
}

function p12RemoveStoredPosterFile(?string $relativePath): bool
{
    if (
        $relativePath === null
        || preg_match('#^uploads/([a-f0-9]{32}\.(?:jpg|png|webp))$#D', $relativePath, $matches) !== 1
    ) {
        return $relativePath === null;
    }

    $moduleDirectory = realpath(__DIR__);
    $uploadDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
    if ($moduleDirectory === false || $uploadDirectory === false) {
        return false;
    }

    $expectedUploadDirectory = $moduleDirectory . DIRECTORY_SEPARATOR . 'uploads';
    if (strcasecmp($uploadDirectory, $expectedUploadDirectory) !== 0) {
        error_log('Practical 12 poster directory resolved outside its designated location.');
        return false;
    }

    $absolutePath = realpath($uploadDirectory . DIRECTORY_SEPARATOR . $matches[1]);
    if ($absolutePath === false) {
        return true;
    }
    if (strcasecmp(dirname($absolutePath), $uploadDirectory) !== 0 || !is_file($absolutePath)) {
        error_log('Practical 12 poster failed its upload-directory safety check.');
        return false;
    }
    if (!unlink($absolutePath)) {
        error_log('Practical 12 could not remove an unused poster file.');
        return false;
    }

    return true;
}

function p12DeletePosterIfUnused(mysqli $mysqli, ?string $relativePath): bool
{
    if ($relativePath === null) {
        return true;
    }

    $statement = $mysqli->prepare(
        'SELECT COUNT(*) AS reference_count
         FROM practical12_events
         WHERE poster_path = ?'
    );
    $statement->bind_param('s', $relativePath);
    $statement->execute();
    $referenceCount = (int) $statement->get_result()->fetch_assoc()['reference_count'];
    $statement->close();

    return $referenceCount > 0 || p12RemoveStoredPosterFile($relativePath);
}

function p12FetchEvent(mysqli $mysqli, int $eventId): ?array
{
    $statement = $mysqli->prepare(
        'SELECT event_id, event_name, description, event_date, event_time, venue, status, poster_path
         FROM practical12_events WHERE event_id = ? LIMIT 1'
    );
    $statement->bind_param('i', $eventId);
    $statement->execute();
    $event = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();
    return $event;
}

function p12Redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}