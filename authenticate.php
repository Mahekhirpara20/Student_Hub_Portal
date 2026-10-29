<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
sendNoCacheHeaders();
startSecureSession();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
if (
    !hasValidCsrfToken()
    || !is_string($email)
    || trim($email) === ''
    || !is_string($password)
    || $password === ''
) {
    header('Location: login.php?error=1');
    exit;
}

require_once __DIR__ . '/session_regeneration_diagnostic.php';
$previousSessionIdHash = hash('sha256', session_id());

try {
    require_once __DIR__ . '/db.php';

    $statement = $mysqli->prepare(
        'SELECT user_id, email, full_name, password_hash, role
         FROM users
         WHERE email = ?
         LIMIT 1'
    );
    $email = trim($email);
    $statement->bind_param('s', $email);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (
        $user === null
        || !password_verify($password, (string) $user['password_hash'])
        || !in_array($user['role'], ['Admin', 'Student'], true)
    ) {
        header('Location: login.php?error=1');
        exit;
    }

    $_SESSION = [];
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('The session ID could not be regenerated.');
    }
    recordSessionIdRegenerationIfLocal($previousSessionIdHash);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['email'] = (string) $user['email'];
    $_SESSION['full_name'] = (string) $user['full_name'];
    $_SESSION['role'] = (string) $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: ' . ($user['role'] === 'Admin'
        ? 'admin_dashboard.php'
        : 'student_dashboard.php'));
    exit;
} catch (RuntimeException $exception) {
    error_log('StudentHub authentication failed: ' . $exception->getMessage());
    header('Location: login.php?error=1');
    exit;
}
