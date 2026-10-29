<?php
declare(strict_types=1);

const SESSION_TIMEOUT_SECONDS = 300;

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    $isHttps = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function sendNoCacheHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function destroyCurrentSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
    session_destroy();
}

function redirectToLogin(string $query = ''): never
{
    header('Location: login.php' . $query);
    exit;
}

function requireAuthenticatedSession(): void
{
    sendNoCacheHeaders();
    startSecureSession();

    $userId = $_SESSION['user_id'] ?? null;
    $email = $_SESSION['email'] ?? null;
    $role = $_SESSION['role'] ?? null;
    $lastActivity = $_SESSION['last_activity'] ?? null;

    if (
        !is_int($userId)
        || $userId < 1
        || !is_string($email)
        || $email === ''
        || !in_array($role, ['Admin', 'Student'], true)
        || !is_int($lastActivity)
    ) {
        destroyCurrentSession();
        redirectToLogin();
    }

    if (time() - $lastActivity >= SESSION_TIMEOUT_SECONDS) {
        destroyCurrentSession();
        redirectToLogin('?timeout=1');
    }

    $_SESSION['last_activity'] = time();
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function hasValidCsrfToken(): bool
{
    $postedToken = $_POST['csrf_token'] ?? null;

    return is_string($postedToken)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $postedToken);
}
