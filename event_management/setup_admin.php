<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

startSecureSession();
$csrf = csrfToken();
$errors = [];
$adminExists = true;

try {
    $adminStatement = $mysqli->prepare("SELECT COUNT(*) AS admin_count FROM users WHERE role = 'Admin'");
    $adminStatement->execute();
    $adminExists = (int) $adminStatement->get_result()->fetch_assoc()['admin_count'] > 0;
    $adminStatement->close();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!p12IsLocalRequest()) {
            http_response_code(404);
            exit;
        }
        if (!hasValidCsrfToken()) {
            $errors[] = 'The setup form expired. Refresh the page and try again.';
        }
        if ($adminExists) {
            $errors[] = 'An Admin account already exists. Use the existing Admin login.';
        }

        $fullName = trim(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '');
        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
        $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($fullName === '' || strlen($fullName) > 100) {
            $errors[] = 'Enter a name of 100 characters or fewer.';
        }
        if ($username === '' || strlen($username) > 50 || preg_match('/^[A-Za-z0-9_.-]+$/', $username) !== 1) {
            $errors[] = 'Enter a username of up to 50 letters, numbers, dots, underscores, or hyphens.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 12) {
            $errors[] = 'Use a password with at least 12 characters.';
        }

        if ($errors === []) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $mysqli->prepare(
                "INSERT INTO users (full_name, username, email, password_hash, role)
                 VALUES (?, ?, ?, ?, 'Admin')"
            );
            $insert->bind_param('ssss', $fullName, $username, $email, $passwordHash);
            $insert->execute();
            $insert->close();
            header('Location: login.php?setup=1', true, 303);
            exit;
        }
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Practical 12 Admin setup failed: ' . $exception->getMessage());
    $errors[] = 'Admin setup could not be completed. The username or email may already be in use.';
} catch (RuntimeException $exception) {
    error_log('Practical 12 Admin setup unavailable: ' . $exception->getMessage());
    $errors[] = 'Admin setup is unavailable right now.';
}

if (!p12IsLocalRequest()) {
    http_response_code(404);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 12 Admin Setup | StudentHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="p12-page">
<main class="p12-auth-card">
    <a class="p12-brand" href="../index.html"><span class="p12-brand-mark">SH</span>StudentHub</a>
    <p class="p12-eyebrow">One-time local setup</p>
    <h1>Create the first Admin</h1>
    <?php foreach ($errors as $error): ?>
        <p class="p12-alert p12-alert-error" role="alert"><?= p12Escape($error) ?></p>
    <?php endforeach; ?>
    <?php if ($adminExists): ?>
        <p class="p12-alert p12-alert-success" role="status">An Admin account already exists. No account was created.</p>
        <a class="p12-button p12-button-primary" href="login.php">Go to Admin login</a>
    <?php else: ?>
        <p class="p12-muted">This page works only from localhost, creates only the first Admin account, and stores a PHP password hash.</p>
        <form method="post" action="setup_admin.php" class="p12-form">
            <input type="hidden" name="csrf_token" value="<?= p12Escape($csrf) ?>">
            <label>Full name<input name="full_name" maxlength="100" required></label>
            <label>Username<input name="username" maxlength="50" required autocomplete="username"></label>
            <label>Email<input name="email" type="email" maxlength="254" required autocomplete="email"></label>
            <label>Password (12 characters minimum)<input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
            <button class="p12-button p12-button-primary" type="submit">Create Admin account</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>