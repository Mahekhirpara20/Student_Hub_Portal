<?php
$pdo = null;
$students = [];
$studentCount = 0;
$eventCount = 0;
$matchedStudent = null;
$connectionError = null;
$queryError = null;
$connectionSucceeded = false;
$studentQuerySucceeded = false;
$eventQuerySucceeded = false;
$demoQuerySucceeded = false;
$demoEmail = 'aanya.patel@example.com';

try {
    require_once __DIR__ . '/db.php';
    $connectionSucceeded = true;

    $studentStatement = $pdo->prepare(
        'SELECT student_id, full_name, email, branch, semester
         FROM students
         ORDER BY student_id'
    );
    $studentStatement->execute();
    $students = $studentStatement->fetchAll();
    $studentCount = count($students);
    $studentQuerySucceeded = true;

    $eventStatement = $pdo->prepare('SELECT COUNT(*) FROM events');
    $eventStatement->execute();
    $eventCount = (int) $eventStatement->fetchColumn();
    $eventQuerySucceeded = true;

    $emailStatement = $pdo->prepare(
        'SELECT student_id, full_name, email, branch, semester
         FROM students
         WHERE email = :email'
    );
    $emailStatement->bindValue(':email', $demoEmail, PDO::PARAM_STR);
    $emailStatement->execute();
    $matchedStudent = $emailStatement->fetch();
    $demoQuerySucceeded = true;
} catch (PDOException $exception) {
    error_log('StudentHub practical query failed: ' . $exception->getMessage());
    $queryError = 'The database was reached, but a practical query could not be completed. Check the schema and try again.';
} catch (RuntimeException $exception) {
    $connectionError = $exception->getMessage();
}

function escapeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | PDO Connection Test</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
            background: #f3f6fb;
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 36px 18px; }
        main { width: min(100%, 960px); margin: 0 auto; }
        h1 { margin: 0 0 8px; color: #243b80; }
        h2 { margin: 0 0 14px; font-size: 1.2rem; }
        p { line-height: 1.55; }
        .intro { margin: 0 0 24px; color: #526078; }
        .card {
            margin: 18px 0;
            padding: 22px;
            overflow: hidden;
            background: #fff;
            border: 1px solid #dce3ef;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(31, 50, 87, .06);
        }
        .status {
            padding: 13px 16px;
            border-radius: 9px;
            font-weight: 700;
        }
        .success { color: #17633a; background: #e8f7ee; }
        .error { color: #8b2525; background: #fff0ef; }
        .muted { color: #5e6b80; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 11px 12px; border-bottom: 1px solid #e5eaf2; }
        th { color: #33446b; background: #f5f7fb; }
        tbody tr:last-child td { border-bottom: 0; }
        .count { margin-bottom: 0; font-weight: 700; }
        @media (max-width: 600px) {
            body { padding: 24px 12px; }
            .card { padding: 16px; }
            th, td { padding: 9px; }
        }
    </style>
</head>
<body>
<main>
    <h1>StudentHub Database Connectivity</h1>
    <p class="intro">Practical 8 — PHP Data Objects (PDO)</p>

    <?php if ($connectionSucceeded): ?>
        <p class="status success">Connection successful: StudentHub is connected to the database.</p>
    <?php elseif ($connectionError !== null): ?>
        <p class="status error"><?= escapeHtml($connectionError) ?></p>
        <p class="muted">Check that Apache and MySQL are running, the schema has been imported, and the settings in <code>db.php</code> match your XAMPP MySQL setup.</p>
    <?php endif; ?>

    <?php if ($queryError !== null): ?>
        <p class="status error"><?= escapeHtml($queryError) ?></p>
    <?php endif; ?>

    <?php if ($connectionSucceeded): ?>
        <section class="card">
            <h2>Student Records</h2>
            <?php if (!$studentQuerySucceeded): ?>
                <p class="muted">Student records could not be retrieved.</p>
            <?php elseif ($studentCount > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Branch</th>
                                <th>Semester</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= escapeHtml($student['student_id']) ?></td>
                                <td><?= escapeHtml($student['full_name']) ?></td>
                                <td><?= escapeHtml($student['email']) ?></td>
                                <td><?= escapeHtml($student['branch']) ?></td>
                                <td><?= escapeHtml($student['semester']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="count">Total students retrieved: <?= escapeHtml($studentCount) ?></p>
            <?php else: ?>
                <p class="muted">There are no student records yet. Import the sample data using <code>schema.sql</code>.</p>
                <p class="count">Total students retrieved: 0</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Prepared-Statement Demonstration</h2>
            <p class="muted">The student is looked up by email using a named placeholder and a bound value.</p>
            <?php if (!$demoQuerySucceeded): ?>
                <p class="muted">The prepared-statement lookup could not be completed.</p>
            <?php elseif ($matchedStudent): ?>
                <p>
                    Match found:
                    <strong><?= escapeHtml($matchedStudent['full_name']) ?></strong>
                    (<?= escapeHtml($matchedStudent['email']) ?>),
                    <?= escapeHtml($matchedStudent['branch']) ?>,
                    semester <?= escapeHtml($matchedStudent['semester']) ?>.
                </p>
            <?php else: ?>
                <p class="muted">No student matched the demonstration email <?= escapeHtml($demoEmail) ?>.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Events</h2>
            <?php if ($eventQuerySucceeded): ?>
                <p class="count">Total events in the database: <?= escapeHtml($eventCount) ?></p>
            <?php else: ?>
                <p class="muted">The event count could not be retrieved.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
