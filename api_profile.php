<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
sendNoCacheHeaders();
startSecureSession();

header('Content-Type: application/json');

$name = 'Hetvi Khavadiya';
$studentId = '25CS033';
$cgpa = '8.33';
$branch = 'Computer Science & Engineering';
$semester = 3;

if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    try {
        require_once __DIR__ . '/db.php';
        $stmt = $pdo->prepare('SELECT full_name, email, role FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if ($u && !empty($u['full_name'])) {
            $name = $u['full_name'];
        }
        if (!empty($u['email'])) {
            $stStmt = $pdo->prepare('SELECT student_id, branch, semester FROM students WHERE email = ? LIMIT 1');
            $stStmt->execute([$u['email']]);
            $st = $stStmt->fetch();
            if ($st) {
                $studentId = 'STU-' . str_pad((string)$st['student_id'], 4, '0', STR_PAD_LEFT);
                if (!empty($st['branch'])) $branch = $st['branch'];
                if (!empty($st['semester'])) $semester = (int)$st['semester'];
            }
        }
    } catch (Throwable $e) {
        error_log('api_profile error: ' . $e->getMessage());
    }
} elseif (isset($_SESSION['demo_profile'])) {
    $demo = $_SESSION['demo_profile'];
    if (!empty($demo['full_name'])) $name = $demo['full_name'];
    if (!empty($demo['student_id'])) $studentId = $demo['student_id'];
    if (!empty($demo['branch'])) $branch = $demo['branch'];
    if (!empty($demo['semester'])) $semester = (int)$demo['semester'];
    if (!empty($demo['cgpa'])) $cgpa = (string)$demo['cgpa'];
}

echo json_encode([
    'full_name' => $name,
    'student_id' => $studentId,
    'cgpa' => $cgpa,
    'branch' => $branch,
    'semester' => $semester,
    'is_logged_in' => isset($_SESSION['user_id'])
]);
