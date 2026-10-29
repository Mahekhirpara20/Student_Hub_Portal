<?php
declare(strict_types=1);

function isLocalSessionRegenerationDiagnosticRequest(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

function recordSessionIdRegenerationIfLocal(string $previousSessionIdHash): void
{
    if (!isLocalSessionRegenerationDiagnosticRequest()) {
        return;
    }

    $previousSessionIdRecorded = strlen($previousSessionIdHash) === 64
        && ctype_xdigit($previousSessionIdHash);
    $newSessionIdHash = hash('sha256', session_id());

    $_SESSION['session_regeneration_diagnostic'] = [
        'authentication_successful' => true,
        'previous_session_id_recorded' => $previousSessionIdRecorded,
        'session_id_regenerated' => $previousSessionIdRecorded
            && !hash_equals($previousSessionIdHash, $newSessionIdHash),
    ];
}

function consumeSessionRegenerationDiagnostic(): ?array
{
    $diagnostic = $_SESSION['session_regeneration_diagnostic'] ?? null;
    unset($_SESSION['session_regeneration_diagnostic']);

    if (
        !isLocalSessionRegenerationDiagnosticRequest()
        || !is_array($diagnostic)
        || !is_bool($diagnostic['authentication_successful'] ?? null)
        || !is_bool($diagnostic['previous_session_id_recorded'] ?? null)
        || !is_bool($diagnostic['session_id_regenerated'] ?? null)
    ) {
        return null;
    }

    return $diagnostic;
}