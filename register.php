<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
startSecureSession();

if (!isset($_SESSION['registration_csrf_token'])) {
    $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
if ($requestMethod !== 'GET' && $requestMethod !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
}

$fullName = '';
$username = '';
$email = '';
$password = '';
$confirmPassword = '';
$errors = [];
$successMessage = null;

if ($requestMethod === 'GET' && isset($_SESSION['registration_success'])) {
    $successMessage = 'Your account has been created successfully. You can now sign in.';
    unset($_SESSION['registration_success']);
}

if ($requestMethod === 'POST') {
    $postedFullName = $_POST['full_name'] ?? '';
    $postedUsername = $_POST['username'] ?? '';
    $postedEmail = $_POST['email'] ?? '';
    $postedPassword = $_POST['password'] ?? '';
    $postedConfirmPassword = $_POST['confirm_password'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';

    $fullName = is_string($postedFullName) ? trim($postedFullName) : '';
    $username = is_string($postedUsername) ? trim($postedUsername) : '';
    $email = is_string($postedEmail) ? trim($postedEmail) : '';
    $password = is_string($postedPassword) ? $postedPassword : '';
    $confirmPassword = is_string($postedConfirmPassword) ? $postedConfirmPassword : '';

    if (
        !is_string($postedToken)
        || !hash_equals($_SESSION['registration_csrf_token'], $postedToken)
    ) {
        $errors[] = 'Your form session has expired. Please try again.';
    } elseif (
        $fullName === ''
        || $username === ''
        || $email === ''
        || $password === ''
        || $confirmPassword === ''
    ) {
        $errors[] = 'All fields are required.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $fullNameLength = preg_match_all('/./u', $fullName, $fullNameCharacters);
        $usernameLength = preg_match_all('/./u', $username, $usernameCharacters);

        if ($fullNameLength === false || $fullNameLength > 100) {
            $errors[] = 'Full name must be 100 characters or fewer.';
        }
        if ($usernameLength === false || $usernameLength > 50) {
            $errors[] = 'Username must be 50 characters or fewer.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
    }

    if ($errors === []) {
        try {
            require_once __DIR__ . '/db.php';

            $emailStatement = $pdo->prepare(
                'SELECT 1 FROM users WHERE email = :email LIMIT 1'
            );
            $emailStatement->execute(['email' => $email]);
            if ($emailStatement->fetchColumn()) {
                $errors[] = 'Email is already registered.';
            }

            $usernameStatement = $pdo->prepare(
                'SELECT 1 FROM users WHERE username = :username LIMIT 1'
            );
            $usernameStatement->execute(['username' => $username]);
            if ($usernameStatement->fetchColumn()) {
                $errors[] = 'Username is already taken.';
            }

            if ($errors === []) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $insertStatement = $pdo->prepare(
                    'INSERT INTO users (full_name, username, email, password_hash)
                     VALUES (:full_name, :username, :email, :password_hash)'
                );
                $insertStatement->execute([
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                ]);

                $_SESSION['registration_success'] = true;
                header('Location: register.php', true, 303);
                exit;
            }
        } catch (PDOException $exception) {
            error_log('StudentHub registration database error: ' . $exception->getMessage());
            $errorInfo = $exception->errorInfo;
            $driverCode = is_array($errorInfo) && isset($errorInfo[1])
                ? (int) $errorInfo[1]
                : 0;
            $driverMessage = is_array($errorInfo) && isset($errorInfo[2])
                ? (string) $errorInfo[2]
                : '';

            if ($exception->getCode() === '23000' && $driverCode === 1062) {
                if (strpos($driverMessage, 'uq_users_email') !== false) {
                    $errors[] = 'Email is already registered.';
                } elseif (strpos($driverMessage, 'uq_users_username') !== false) {
                    $errors[] = 'Username is already taken.';
                } else {
                    $errors[] = 'An account with that username or email already exists.';
                }
            } else {
                $errors[] = 'Registration could not be completed right now. Please try again.';
            }
        } catch (RuntimeException $exception) {
            error_log('StudentHub registration connection error: ' . $exception->getMessage());
            $errors[] = 'Registration could not connect to the database. Please check that MySQL is running.';
        }
    }
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Create Account</title>
    <style>
        :root {
            --primary: #3F5AE0;
            --primary-dark: #3048C8;
            --surface: #ffffff;
            --surface-2: #F8FAFF;
            --text: #1F2937;
            --muted: #64748B;
            --border: #D3D9EB;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            background: radial-gradient(circle at top, rgba(63,90,224,.14), transparent 28%),
                linear-gradient(180deg, #EEF4FF 0%, #F5F7FB 100%);
        }
        .navbar {
            max-width: 1000px;
            margin: 0 auto 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nav-logo { color: var(--primary); font-size: 22px; font-weight: 800; text-decoration: none; }
        .nav-link, .login-link { color: var(--primary); font-weight: 700; text-decoration: none; }
        .nav-link:hover, .login-link:hover { text-decoration: underline; }
        main { width: 100%; max-width: 480px; margin: 36px auto; }
        .brand { margin-bottom: 24px; text-align: center; }
        h1 { margin: 0 0 8px; font-size: 36px; }
        .subtitle { margin: 0; color: var(--muted); }
        .card {
            padding: 30px;
            border-radius: 24px;
            background: var(--surface);
            box-shadow: 0 22px 55px rgba(0,0,0,.12);
        }
        h2 { margin: 0 0 22px; font-size: 24px; }
        .message { margin: 0 0 18px; padding: 13px 15px; border-radius: 10px; line-height: 1.5; }
        .error { color: #8b2525; background: #fff0ef; }
        .success { color: #17633a; background: #e8f7ee; }
        .message ul { margin: 0; padding-left: 20px; }
        .field { margin-bottom: 16px; }
        label { display: block; margin-bottom: 7px; font-weight: 700; }
        input {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-2);
            color: var(--text);
            font-size: 16px;
        }
        input:focus { outline: 3px solid rgba(63,90,224,.2); border-color: var(--primary); }
        .submit-btn {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
        }
        .submit-btn:hover { background: var(--primary-dark); }
        .login-prompt { margin: 20px 0 0; color: var(--muted); text-align: center; }
        @media (max-width: 520px) {
            body { padding: 16px; }
            main { margin: 24px auto; }
            .card { padding: 22px 18px; border-radius: 18px; }
            h1 { font-size: 30px; }
        }
    </style>
</head>
<body>
    <nav class="navbar" aria-label="Main navigation">
        <a class="nav-logo" href="index.html">StudentHub</a>
        <a class="nav-link" href="login.php">Login</a>
    </nav>

    <main>
        <header class="brand">
            <h1>StudentHub</h1>
            <p class="subtitle">Create your student portal account</p>
        </header>

        <section class="card" aria-labelledby="register-heading">
            <h2 id="register-heading">User Registration</h2>

            <?php if ($successMessage !== null): ?>
                <div class="message success" role="status" aria-live="polite">
                    <?= escapeHtml($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="message error" role="alert" aria-live="assertive">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escapeHtml($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($requestMethod === 'GET' || $requestMethod === 'POST'): ?>
                <form method="POST" action="register.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeHtml($_SESSION['registration_csrf_token']) ?>">

                    <div class="field">
                        <label for="full_name">Full Name</label>
                        <input
                            id="full_name"
                            name="full_name"
                            type="text"
                            maxlength="100"
                            autocomplete="name"
                            value="<?= escapeHtml($fullName) ?>"
                            required>
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            maxlength="50"
                            autocomplete="username"
                            value="<?= escapeHtml($username) ?>"
                            required>
                    </div>

                    <div class="field">
                        <label for="email">Email Address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            maxlength="254"
                            autocomplete="email"
                            value="<?= escapeHtml($email) ?>"
                            required>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            required>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <input
                            id="confirm_password"
                            name="confirm_password"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            required>
                    </div>

                    <button class="submit-btn" type="submit">Register</button>
                </form>
            <?php endif; ?>

            <p class="login-prompt">
                Already have an account?
                <a class="login-link" href="login.php">Login here</a>
            </p>
        </section>
    </main>
</body>
</html>
