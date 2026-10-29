<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
sendNoCacheHeaders();
startSecureSession();

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? (int) $_SESSION['user_id'] : null;
$sessionRole = $_SESSION['role'] ?? null;
$csrf = csrfToken();

$successMessage = null;
$errorMessage = null;

if (isset($_GET['updated'])) {
    $successMessage = 'Your profile details have been updated successfully!';
}

// Database connection if available
$pdo = null;
try {
    require_once __DIR__ . '/db.php';
} catch (Throwable $e) {
    error_log('Database unavailable in profile.php: ' . $e->getMessage());
}

// Handle Form Submission (Edit Profile)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || isset($_POST['ajax']);

    if (!hash_equals($csrf, (string)$postedToken)) {
        $errorMessage = 'Security token expired. Please reload and try again.';
        if ($isAjax) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            exit;
        }
    } else {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $dob = trim((string)($_POST['dob'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $branch = trim((string)($_POST['branch'] ?? ''));
        $semester = (int)($_POST['semester'] ?? 0);
        $studentId = trim((string)($_POST['student_id'] ?? ''));
        $cgpa = trim((string)($_POST['cgpa'] ?? '8.33'));

        if ($fullName === '') {
            $errorMessage = 'Full Name is required.';
        } elseif (mb_strlen($fullName) > 100) {
            $errorMessage = 'Full Name must not exceed 100 characters.';
        } elseif ($phone !== '' && mb_strlen($phone) > 20) {
            $errorMessage = 'Phone number is too long (max 20 characters).';
        } elseif ($address !== '' && mb_strlen($address) > 255) {
            $errorMessage = 'Address is too long (max 255 characters).';
        } elseif ($branch !== '' && mb_strlen($branch) > 100) {
            $errorMessage = 'Branch name is too long (max 100 characters).';
        } elseif ($semester < 0 || $semester > 12) {
            $errorMessage = 'Semester must be between 1 and 12.';
        } else {
            // Process Save
            if ($isLoggedIn && $pdo !== null) {
                try {
                    // Update user table
                    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, dob = ?, address = ? WHERE user_id = ?');
                    $stmt->execute([
                        $fullName,
                        $phone !== '' ? $phone : null,
                        $dob !== '' ? $dob : null,
                        $address !== '' ? $address : null,
                        $userId
                    ]);

                    $_SESSION['full_name'] = $fullName;

                    // If student or user has student record, update or create student record
                    $userEmail = $_SESSION['email'] ?? '';
                    if ($userEmail !== '') {
                        $findStudent = $pdo->prepare('SELECT student_id FROM students WHERE email = ? LIMIT 1');
                        $findStudent->execute([$userEmail]);
                        $matchedStudentId = $findStudent->fetchColumn();

                        if ($matchedStudentId) {
                            $updateStudent = $pdo->prepare('UPDATE students SET full_name = ?, branch = ?, semester = ?, phone = ?, dob = ?, address = ? WHERE student_id = ?');
                            $updateStudent->execute([
                                $fullName,
                                $branch !== '' ? $branch : 'Computer Engineering',
                                $semester > 0 ? $semester : 1,
                                $phone !== '' ? $phone : null,
                                $dob !== '' ? $dob : null,
                                $address !== '' ? $address : null,
                                $matchedStudentId
                            ]);
                        } else if ($sessionRole === 'Student') {
                            $insertStudent = $pdo->prepare('INSERT INTO students (full_name, email, branch, semester, phone, dob, address) VALUES (?, ?, ?, ?, ?, ?, ?)');
                            $insertStudent->execute([
                                $fullName,
                                $userEmail,
                                $branch !== '' ? $branch : 'Computer Engineering',
                                $semester > 0 ? $semester : 1,
                                $phone !== '' ? $phone : null,
                                $dob !== '' ? $dob : null,
                                $address !== '' ? $address : null
                            ]);
                        }
                    }

                    // Log audit trail if audit table exists
                    try {
                        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $logStmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
                        $logStmt->execute([$userId, 'PROFILE_UPDATE', "User updated profile: {$fullName}", $remoteIp]);
                    } catch (Throwable $ignore) {}

                    $successMessage = 'Your profile details have been saved to your account.';
                } catch (Throwable $e) {
                    error_log('Profile update failed: ' . $e->getMessage());
                    $errorMessage = 'Database error while saving profile. Please try again.';
                }
            } else {
                // Demo / guest session storage
                $_SESSION['demo_profile'] = [
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'dob' => $dob,
                    'address' => $address,
                    'branch' => $branch,
                    'semester' => $semester > 0 ? $semester : 3,
                    'student_id' => $studentId !== '' ? $studentId : '25CS033',
                    'cgpa' => $cgpa !== '' ? $cgpa : '8.33'
                ];
                $successMessage = 'Profile updated successfully for this session.';
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                if ($errorMessage) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $errorMessage]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => $successMessage,
                        'profile' => [
                            'full_name' => $fullName,
                            'phone' => $phone,
                            'dob' => $dob,
                            'address' => $address,
                            'branch' => $branch,
                            'semester' => $semester,
                            'student_id' => $studentId,
                            'cgpa' => $cgpa !== '' ? $cgpa : '8.33'
                        ]
                    ]);
                }
                exit;
            }

            if (!$errorMessage) {
                header('Location: profile.php?updated=1');
                exit;
            }
        }

        if ($isAjax && $errorMessage) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            exit;
        }
    }
}

// Prepare profile data to display
$profile = [
    'full_name' => 'Hetvi Khavadiya',
    'student_id' => '25CS033',
    'email' => 'hetvi.25cs033@college.edu.in',
    'branch' => 'Computer Science & Engineering',
    'semester' => 3,
    'phone' => '+91 98765 43210',
    'dob' => '2006-03-15',
    'address' => 'Rajkot, Gujarat',
    'role' => 'Student',
    'cgpa' => '8.33'
];

if ($isLoggedIn && $pdo !== null) {
    try {
        $stmt = $pdo->prepare('SELECT user_id, full_name, username, email, role, phone, dob, address, created_at FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();

        if ($userData) {
            $profile['full_name'] = $userData['full_name'];
            $profile['email'] = $userData['email'];
            $profile['role'] = $userData['role'];
            $profile['username'] = $userData['username'];
            $profile['student_id'] = $userData['username'];
            if (!empty($userData['phone'])) $profile['phone'] = $userData['phone'];
            if (!empty($userData['dob'])) $profile['dob'] = $userData['dob'];
            if (!empty($userData['address'])) $profile['address'] = $userData['address'];

            // Match student record
            $stStmt = $pdo->prepare('SELECT student_id, branch, semester, phone, dob, address FROM students WHERE email = ? LIMIT 1');
            $stStmt->execute([$userData['email']]);
            $stData = $stStmt->fetch();
            if ($stData) {
                $profile['student_id'] = 'STU-' . str_pad((string)$stData['student_id'], 4, '0', STR_PAD_LEFT);
                $profile['branch'] = $stData['branch'] ?? $profile['branch'];
                $profile['semester'] = (int)($stData['semester'] ?? $profile['semester']);
                if (!empty($stData['phone']) && empty($userData['phone'])) $profile['phone'] = $stData['phone'];
                if (!empty($stData['dob']) && empty($userData['dob'])) $profile['dob'] = $stData['dob'];
                if (!empty($stData['address']) && empty($userData['address'])) $profile['address'] = $stData['address'];
            }
        }
    } catch (Throwable $e) {
        error_log('Error loading user profile: ' . $e->getMessage());
    }
} elseif (isset($_SESSION['demo_profile'])) {
    $demo = $_SESSION['demo_profile'];
    $profile['full_name'] = $demo['full_name'] ?? $profile['full_name'];
    $profile['phone'] = $demo['phone'] ?? $profile['phone'];
    $profile['dob'] = $demo['dob'] ?? $profile['dob'];
    $profile['address'] = $demo['address'] ?? $profile['address'];
    $profile['branch'] = $demo['branch'] ?? $profile['branch'];
    $profile['semester'] = $demo['semester'] ?? $profile['semester'];
    $profile['student_id'] = $demo['student_id'] ?? $profile['student_id'];
}

// Compute initials for Avatar
$nameParts = preg_split('/\s+/', trim($profile['full_name']));
$initials = '';
if (!empty($nameParts[0])) $initials .= mb_strtoupper(mb_substr($nameParts[0], 0, 1));
if (count($nameParts) > 1) $initials .= mb_strtoupper(mb_substr($nameParts[count($nameParts) - 1], 0, 1));
if ($initials === '') $initials = 'SH';

function escape(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>StudentHub - Profile</title>

    <style>
        :root {
            --bg: #eef4ff;
            --surface: #ffffff;
            --surface-2: #f8f9fc;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #3f5ae0;
            --primary-hover: #2e47c7;
            --primary-soft: rgba(63, 90, 224, .12);
            --border: #e2e8f0;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --success-text: #065f46;
            --error-bg: #fef2f2;
            --error-border: #fecaca;
            --error-text: #991b1b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(180deg, #f7f9ff 0%, #eef4ff 100%);
            color: var(--text);
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }

        .sidebar {
            background: var(--surface);
            border-radius: 30px;
            padding: 28px 24px;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            display: flex;
            flex-direction: column;
            gap: 24px;
            height: fit-content;
        }

        .sidebar .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 12px;
        }

        .brand-title {
            font-size: 20px;
            letter-spacing: -0.03em;
            font-weight: 700;
        }

        .nav {
            list-style: none;
            display: grid;
            gap: 8px;
        }

        .nav li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            color: #334155;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s ease, color .2s ease;
        }

        .nav li a:hover,
        .nav li a.active {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .nav li a span.icon {
            width: 18px;
            display: inline-flex;
            justify-content: center;
        }

        .nav li a span.badge {
            margin-left: auto;
            background: var(--primary);
            color: #fff;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 12px;
            font-weight: 700;
        }

        .sidebar hr {
            border: none;
            height: 1px;
            background: #e2e8f0;
            margin: 0;
        }

        .sidebar .profile {
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .sidebar .profile h3 {
            margin-bottom: 4px;
            color: var(--text);
            font-size: 17px;
        }

        .sidebar .profile a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .main {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .page-header {
            background: var(--surface);
            border-radius: 28px;
            padding: 24px 28px;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-header::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background: var(--primary);
            border-radius: 6px 0 0 6px;
        }

        .page-header h1 {
            font-size: 28px;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .page-header .action {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s ease;
            box-shadow: 0 4px 14px rgba(63, 90, 224, 0.28);
            text-decoration: none;
        }

        .btn-edit:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(63, 90, 224, 0.38);
        }

        .btn-edit svg {
            width: 16px;
            height: 16px;
        }

        .alert-box {
            padding: 14px 20px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: fadeIn .3s ease;
        }

        .alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .alert-error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        .auth-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--primary-soft);
            color: var(--primary);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .profile-grid {
            display: grid;
            gap: 20px;
        }

        .profile-card {
            background: var(--surface);
            border-radius: 24px;
            padding: 24px 28px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .profile-card .profile-summary {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
            flex-shrink: 0;
        }

        .profile-summary h2 {
            font-size: 22px;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .profile-summary p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }

        .role-tag {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            background: #f1f5f9;
            color: #475569;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .section-title {
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #f1f5f9;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .detail-item {
            display: grid;
            gap: 6px;
        }

        .detail-item span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 500;
        }

        .detail-item strong {
            font-size: 16px;
            color: #111827;
            font-weight: 600;
            word-break: break-word;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all .25s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 660px;
            max-height: 90vh;
            border-radius: 28px;
            box-shadow: 0 30px 60px -15px rgba(15, 23, 42, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(0.95) translateY(12px);
            transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-overlay.active .modal-card {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 28px;
            border-bottom: 1px solid var(--border);
            background: #ffffff;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close-btn {
            background: #f8fafc;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            transition: all .2s;
        }

        .modal-close-btn:hover {
            color: #0f172a;
            background: #e2e8f0;
        }

        .modal-body {
            padding: 26px 28px;
            overflow-y: auto;
            display: grid;
            gap: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            color: #0f172a;
            font-family: inherit;
            background: #ffffff;
            transition: border-color .15s, box-shadow .15s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(63, 90, 224, 0.15);
        }

        .form-group input[readonly] {
            background: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
            border-style: dashed;
        }

        .help-hint {
            font-size: 12px;
            color: #64748b;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            padding: 18px 28px;
            border-top: 1px solid var(--border);
            background: #f8fafc;
        }

        .btn-secondary {
            padding: 11px 20px;
            background: #e2e8f0;
            color: #334155;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-primary {
            padding: 11px 24px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all .2s;
            box-shadow: 0 4px 12px rgba(63, 90, 224, 0.25);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 860px) {
            .container {
                grid-template-columns: 1fr;
                padding: 16px;
            }
            .sidebar {
                border-radius: 24px;
            }
            .form-row, .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="brand">
            <img src="logo.png" class="logo" alt="StudentHub Logo">
            <div class="brand-title">StudentHub</div>
        </div>

        <ul class="nav">
            <li><a href="index.html"><span class="icon">⌂</span>Home</a></li>
            <?php if ($sessionRole === 'Admin'): ?>
                <li><a href="admin_dashboard.php"><span class="icon">⚡</span>Admin Home</a></li>
                <li><a href="student_management.php"><span class="icon">👥</span>Student Records</a></li>
                <li><a href="audit_logs.php"><span class="icon">📜</span>Audit Logs</a></li>
            <?php else: ?>
                <li><a href="student_dashboard.php"><span class="icon">⚡</span>Student Home</a></li>
                <li><a href="timetable.html"><span class="icon">◷</span>Timetable</a></li>
                <li><a href="attendance.html"><span class="icon">▥</span>Attendance</a></li>
                <li><a href="results.html"><span class="icon">▥</span>Results &amp; CGPA</a></li>
                <li><a href="events.html"><span class="icon">□</span>Events</a></li>
                <li><a href="assignment.html"><span class="icon">▤</span>Assignments</a></li>
                <li><a href="fees.html"><span class="icon">▭</span>Fees Payment</a></li>
                <li><a href="certificate.html"><span class="icon">♙</span>Certificates</a></li>
                <li><a href="resources.html"><span class="icon">▣</span>Resources</a></li>
                <li><a href="notifications.html"><span class="icon">♧</span>Notifications<span class="badge">2</span></a></li>
            <?php endif; ?>
            <li><a class="active" href="profile.php"><span class="icon">👤</span>Profile</a></li>
        </ul>

        <hr />

        <div class="profile">
            <h3 id="sidebarName"><?= escape($profile['full_name']) ?></h3>
            <p id="sidebarSubtitle"><?= escape($profile['student_id']) ?> • Semester <?= escape($profile['semester']) ?></p>
            <p style="margin-top: 8px;">
                <?php if ($isLoggedIn): ?>
                    <a href="signout.php">Sign Out</a>
                <?php else: ?>
                    <a href="login.php">Sign In</a> • <a href="register.php">Register</a>
                <?php endif; ?>
            </p>
        </div>
    </aside>

    <main class="main">
        <section class="page-header">
            <div>
                <h1>Profile</h1>
                <p style="color: var(--muted); font-size: 14px; margin-top: 4px;">
                    <?php if ($isLoggedIn): ?>
                        <span class="auth-status-pill">✓ Signed in as <?= escape($sessionRole ?? 'User') ?></span>
                    <?php else: ?>
                        <span class="auth-status-pill" style="background: #fef3c7; color: #92400e;">Demo Preview Mode • <a href="login.php" style="color: inherit; font-weight: 700; text-decoration: underline;">Sign In</a></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="action">
                <button type="button" class="btn-edit" id="openEditModalBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit Profile
                </button>
            </div>
        </section>

        <?php if ($successMessage !== null): ?>
            <div class="alert-box alert-success" id="statusAlert">
                <span>✓ <?= escape($successMessage) ?></span>
                <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:700;color:inherit;">✕</button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== null): ?>
            <div class="alert-box alert-error" id="errorAlert">
                <span>✕ <?= escape($errorMessage) ?></span>
                <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:700;color:inherit;">✕</button>
            </div>
        <?php endif; ?>

        <section class="profile-grid">
            <div class="profile-card">
                <div class="profile-summary">
                    <div class="avatar" id="avatarDisplay"><?= escape($initials) ?></div>
                    <div>
                        <h2 id="viewFullName"><?= escape($profile['full_name']) ?></h2>
                        <p id="viewHeaderSub"><?= escape($profile['student_id']) ?> · <?= escape($profile['branch']) ?></p>
                        <span class="role-tag"><?= escape($profile['role'] ?? 'Student') ?></span>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <div class="section-title">Basic Details</div>
                <div class="details-grid">
                    <div class="detail-item">
                        <span>Full Name</span>
                        <strong id="viewName"><?= escape($profile['full_name']) ?></strong>
                    </div>
                    <div class="detail-item">
                        <span>Student / Enrollment ID</span>
                        <strong id="viewStudentId"><?= escape($profile['student_id']) ?></strong>
                    </div>
                    <div class="detail-item">
                        <span>Branch / Department</span>
                        <strong id="viewBranch"><?= escape($profile['branch']) ?></strong>
                    </div>
                    <div class="detail-item">
                        <span>Semester</span>
                        <strong id="viewSemester"><?= escape($profile['semester']) ?><?= $profile['semester'] == 1 ? 'st' : ($profile['semester'] == 2 ? 'nd' : ($profile['semester'] == 3 ? 'rd' : 'th')) ?> Semester</strong>
                    </div>
                    <div class="detail-item">
                        <span>Current CGPA</span>
                        <strong id="viewCgpa"><?= escape($profile['cgpa']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <div class="section-title">Contact Information</div>
                <div class="details-grid">
                    <div class="detail-item">
                        <span>Email Address</span>
                        <strong id="viewEmail"><?= escape($profile['email']) ?></strong>
                    </div>
                    <div class="detail-item">
                        <span>Phone Number</span>
                        <strong id="viewPhone"><?= escape($profile['phone']) ?></strong>
                    </div>
                    <div class="detail-item">
                        <span>Date of Birth</span>
                        <strong id="viewDob"><?= escape($profile['dob']) ?></strong>
                    </div>
                    <div class="detail-item">
                        <span>Address</span>
                        <strong id="viewAddress"><?= escape($profile['address']) ?></strong>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Edit Profile Modal Dialog -->
<div class="modal-overlay" id="editModal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div class="modal-card">
        <header class="modal-header">
            <h2 id="editModalTitle">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Edit Profile
            </h2>
            <button type="button" class="modal-close-btn" id="closeModalBtn" aria-label="Close modal">✕</button>
        </header>

        <form id="editProfileForm" action="profile.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
            <input type="hidden" name="ajax" value="1">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="inputFullName">Full Name *</label>
                        <input type="text" id="inputFullName" name="full_name" required maxlength="100" value="<?= escape($profile['full_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="inputStudentId">Student ID / Enrollment</label>
                        <input type="text" id="inputStudentId" name="student_id" maxlength="50" value="<?= escape($profile['student_id']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="inputEmail">Email Address</label>
                        <input type="email" id="inputEmail" readonly value="<?= escape($profile['email']) ?>">
                        <span class="help-hint">Email is linked to your login credentials</span>
                    </div>
                    <div class="form-group">
                        <label for="inputPhone">Phone Number</label>
                        <input type="tel" id="inputPhone" name="phone" maxlength="20" placeholder="+91 98765 43210" value="<?= escape($profile['phone']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="inputBranch">Branch / Department</label>
                        <input type="text" id="inputBranch" name="branch" maxlength="100" placeholder="e.g. Computer Science & Engineering" value="<?= escape($profile['branch']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="inputSemester">Semester</label>
                        <select id="inputSemester" name="semester">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>" <?= $profile['semester'] == $i ? 'selected' : '' ?>>
                                    Semester <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="inputDob">Date of Birth</label>
                        <input type="date" id="inputDob" name="dob" value="<?= escape($profile['dob']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="inputCgpa">Current CGPA</label>
                        <input type="text" id="inputCgpa" name="cgpa" maxlength="5" placeholder="8.33" value="<?= escape($profile['cgpa']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="inputAddress">City / Address</label>
                        <input type="text" id="inputAddress" name="address" maxlength="255" placeholder="e.g. Rajkot, Gujarat" value="<?= escape($profile['address']) ?>">
                    </div>
                </div>
            </div>

            <footer class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveProfileBtn">Save Changes</button>
            </footer>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editModal');
    const openBtn = document.getElementById('openEditModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const cancelBtn = document.getElementById('cancelModalBtn');
    const form = document.getElementById('editProfileForm');
    const saveBtn = document.getElementById('saveProfileBtn');

    function openModal() {
        editModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        editModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    // Close on background click
    editModal.addEventListener('click', function (e) {
        if (e.target === editModal) {
            closeModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && editModal.classList.contains('active')) {
            closeModal();
        }
    });

    // Handle AJAX Form Submission for seamless instant update
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const formData = new FormData(form);

        fetch('profile.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';

            if (data.success) {
                // Update on-screen view elements immediately
                const p = data.profile;
                if (p.full_name) {
                    document.getElementById('viewFullName').textContent = p.full_name;
                    document.getElementById('viewName').textContent = p.full_name;
                    document.getElementById('sidebarName').textContent = p.full_name;

                    // Update avatar initials
                    const parts = p.full_name.trim().split(/\s+/);
                    let inits = parts[0] ? parts[0].charAt(0).toUpperCase() : '';
                    if (parts.length > 1) {
                        inits += parts[parts.length - 1].charAt(0).toUpperCase();
                    }
                    document.getElementById('avatarDisplay').textContent = inits || 'SH';
                }

                if (p.student_id) {
                    document.getElementById('viewStudentId').textContent = p.student_id;
                }

                if (p.branch) {
                    document.getElementById('viewBranch').textContent = p.branch;
                }

                if (p.student_id || p.branch) {
                    const sid = p.student_id || document.getElementById('viewStudentId').textContent;
                    const br = p.branch || document.getElementById('viewBranch').textContent;
                    document.getElementById('viewHeaderSub').textContent = sid + ' · ' + br;
                }

                if (p.semester) {
                    const suffixes = {1: 'st', 2: 'nd', 3: 'rd'};
                    const sfx = suffixes[p.semester] || 'th';
                    document.getElementById('viewSemester').textContent = p.semester + sfx + ' Semester';
                    document.getElementById('sidebarSubtitle').textContent = (p.student_id || 'Student') + ' • Semester ' + p.semester;
                }

                if (p.phone) document.getElementById('viewPhone').textContent = p.phone;
                if (p.dob) document.getElementById('viewDob').textContent = p.dob;
                if (p.address) document.getElementById('viewAddress').textContent = p.address;

                if (window.updateStudentHubProfile) {
                    window.updateStudentHubProfile(p);
                }

                // Show toast / alert
                let existingAlert = document.getElementById('statusAlert');
                if (!existingAlert) {
                    existingAlert = document.createElement('div');
                    existingAlert.id = 'statusAlert';
                    existingAlert.className = 'alert-box alert-success';
                    document.querySelector('.main').insertBefore(existingAlert, document.querySelector('.profile-grid'));
                }
                existingAlert.innerHTML = '<span>✓ ' + (data.message || 'Profile updated successfully!') + '</span><button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:700;color:inherit;">✕</button>';

                closeModal();
            } else {
                alert(data.error || 'Failed to update profile.');
            }
        })
        .catch(function (err) {
            console.error(err);
            // Fallback to normal form submit if fetch fails
            form.submit();
        });
    });

    // Sync on initial load
    if (window.updateStudentHubProfile) {
        window.updateStudentHubProfile(<?= json_encode($profile) ?>);
    }
});
</script>
<script src="js/profile-sync.js"></script>
</body>
</html>
