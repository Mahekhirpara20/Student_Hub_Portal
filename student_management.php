<?php
declare(strict_types=1);

require_once __DIR__ . '/timeout.php';
requireAuthenticatedSession();

if (($_SESSION['role'] ?? null) !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized. Admin access is required.');
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/audit_log.php';
} catch (RuntimeException $exception) {
    http_response_code(503);
    exit('Student records are temporarily unavailable. Please try again later.');
}

function studentManagementEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentManagementUrl(array $parameters = []): string
{
    return 'student_management.php'
        . ($parameters === [] ? '' : '?' . http_build_query($parameters));
}

function redirectToStudentManagement(array $parameters = []): never
{
    header('Location: ' . studentManagementUrl($parameters), true, 303);
    exit;
}

function postString(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function isWithinCharacterLimit(string $value, int $limit): bool
{
    $length = preg_match_all('/./us', $value);
    return $length !== false && $length <= $limit;
}

function validateStudentFields(array $source): array
{
    $values = [
        'full_name' => postString($source, 'full_name'),
        'email' => postString($source, 'email'),
        'branch' => postString($source, 'branch'),
        'semester' => postString($source, 'semester'),
    ];
    $errors = [];

    if ($values['full_name'] === '') {
        $errors[] = 'Student name is required.';
    } elseif (!isWithinCharacterLimit($values['full_name'], 100)) {
        $errors[] = 'Student name must be 100 characters or fewer.';
    }

    if ($values['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!isWithinCharacterLimit($values['email'], 254)
        || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Enter a valid email address (254 characters or fewer).';
    }

    if ($values['branch'] === '') {
        $errors[] = 'Course / branch is required.';
    } elseif (!isWithinCharacterLimit($values['branch'], 100)) {
        $errors[] = 'Course / branch must be 100 characters or fewer.';
    }

    $semester = filter_var($values['semester'], FILTER_VALIDATE_INT);
    if ($semester === false || $semester < 1 || $semester > 12) {
        $errors[] = 'Semester must be a number from 1 to 12.';
    }

    return [$values, $semester === false ? 0 : $semester, $errors];
}

function studentEmailExists(mysqli $mysqli, string $email, ?int $excludeId = null): bool
{
    if ($excludeId === null) {
        $statement = $mysqli->prepare(
            'SELECT student_id FROM students WHERE email = ? LIMIT 1'
        );
        $statement->bind_param('s', $email);
    } else {
        $statement = $mysqli->prepare(
            'SELECT student_id FROM students WHERE email = ? AND student_id <> ? LIMIT 1'
        );
        $statement->bind_param('si', $email, $excludeId);
    }

    $statement->execute();
    $exists = $statement->get_result()->num_rows > 0;
    $statement->close();
    return $exists;
}

function fetchStudentById(mysqli $mysqli, int $studentId): ?array
{
    $statement = $mysqli->prepare(
        'SELECT student_id, full_name, email, branch, semester, status
         FROM students WHERE student_id = ? LIMIT 1'
    );
    $statement->bind_param('i', $studentId);
    $statement->execute();
    $student = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();
    return $student;
}

function fetchStudentRows(
    mysqli $mysqli,
    string $search,
    string $course,
    string $status
): array {
    $statement = $mysqli->prepare(
        'SELECT student_id, full_name, email, branch, semester, status
         FROM students
         WHERE (full_name LIKE ? OR email LIKE ? OR branch LIKE ?)
           AND (? = \'\' OR branch = ?)
           AND (? = \'All\' OR status = ?)
         ORDER BY student_id DESC'
    );
    $searchPattern = '%' . $search . '%';
    $statement->bind_param(
        'sssssss',
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $course,
        $course,
        $status,
        $status
    );
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return $rows;
}

function csvSafeCell(mixed $value): string
{
    $cell = (string) $value;
    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $cell) === 1) {
        return "'" . $cell;
    }
    return $cell;
}

$csrf = csrfToken();
$requestSource = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? $_POST : $_GET;
$search = postString($requestSource, 'search');
$course = postString($requestSource, 'course');
$status = postString($requestSource, 'status');
$errors = [];
$notice = $_SESSION['student_management_notice'] ?? null;
unset($_SESSION['student_management_notice']);
$courseOptions = [];
$students = [];
$activeStudentCount = null;
$inactiveStudentCount = null;
$editStudent = null;
$confirmStudent = null;
$editId = null;
$formValues = ['full_name' => '', 'email' => '', 'branch' => '', 'semester' => ''];
$addFormValues = ['full_name' => '', 'email' => '', 'branch' => '', 'semester' => ''];
$selectedStatus = in_array($status, ['Active', 'Inactive', 'All'], true) ? $status : 'Active';

try {
    $totalsStatement = $mysqli->prepare(
        "SELECT
            COUNT(CASE WHEN status = 'Active' THEN 1 END) AS active_count,
            COUNT(CASE WHEN status = 'Inactive' THEN 1 END) AS inactive_count
         FROM students"
    );
    $totalsStatement->execute();
    $totals = $totalsStatement->get_result()->fetch_assoc();
    $totalsStatement->close();
    if ($totals !== null) {
        $activeStudentCount = (int) $totals['active_count'];
        $inactiveStudentCount = (int) $totals['inactive_count'];
    }

    $branchResult = $mysqli->query('SELECT DISTINCT branch FROM students ORDER BY branch');
    while ($branchRow = $branchResult->fetch_assoc()) {
        $courseOptions[] = (string) $branchRow['branch'];
    }
    $branchResult->free();

    if ($course !== '' && !in_array($course, $courseOptions, true)) {
        $errors[] = 'The selected course is not available. Showing all courses instead.';
        $course = '';
    }
    if (!in_array($status, ['', 'Active', 'Inactive', 'All'], true)) {
        $errors[] = 'The selected status is invalid. Showing Active students instead.';
        $selectedStatus = 'Active';
    } elseif ($status === '') {
        $selectedStatus = 'Active';
    }
    if (!isWithinCharacterLimit($search, 150)) {
        $errors[] = 'Search must be 150 characters or fewer.';
        $search = '';
    }

    $isExport = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
        && ($_GET['export'] ?? '') === 'csv';
    if ($isExport) {
        if ($errors !== []) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            exit('Invalid search or filter selection. Please correct it and try again.');
        }
        $exportRows = fetchStudentRows($mysqli, $search, $course, $selectedStatus);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="student-records-' . gmdate('Y-m-d') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('CSV output could not be opened.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Student ID', 'Student Name', 'Email', 'Course / Branch', 'Semester', 'Status'], ',', '"', '');
        foreach ($exportRows as $row) {
            fputcsv($output, [
                csvSafeCell($row['student_id']),
                csvSafeCell($row['full_name']),
                csvSafeCell($row['email']),
                csvSafeCell($row['branch']),
                csvSafeCell($row['semester']),
                csvSafeCell($row['status']),
            ], ',', '"', '');
        }
        fclose($output);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $action = postString($_POST, 'action');
        if (!hasValidCsrfToken()) {
            $errors[] = 'The form session expired. Refresh the page and try again.';
        } elseif ($action === 'add' || $action === 'update') {
            [$formValues, $semester, $fieldErrors] = validateStudentFields($_POST);
            if ($action === 'add') {
                $addFormValues = $formValues;
            }
            $errors = array_merge($errors, $fieldErrors);
            if ($errors === [] && !in_array($formValues['branch'], $courseOptions, true)) {
                $errors[] = 'Select a course / branch value already present in the student records.';
            }
            $editId = $action === 'update'
                ? filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : null;

            if ($action === 'update' && $editId === false) {
                $errors[] = 'The selected student record is invalid.';
                $editId = null;
            }

            if ($errors === [] && studentEmailExists(
                $mysqli,
                $formValues['email'],
                $action === 'update' ? $editId : null
            )) {
                $errors[] = 'That email address is already assigned to another student.';
            }

            if ($errors === []) {
                if ($action === 'add') {
                    runAuditedAdminMutation(
                        $mysqli,
                        'student.create',
                        function () use ($mysqli, $formValues, $semester): int {
                            $statement = $mysqli->prepare(
                                'INSERT INTO students (full_name, email, branch, semester, status)
                                 VALUES (?, ?, ?, ?, \'Active\')'
                            );
                            $statement->bind_param(
                                'sssi',
                                $formValues['full_name'],
                                $formValues['email'],
                                $formValues['branch'],
                                $semester
                            );
                            $statement->execute();
                            $statement->close();
                            return (int) $mysqli->insert_id;
                        },
                        static fn (int $studentId): string => 'Created student record #' . $studentId . '.'
                    );
                    $_SESSION['student_management_notice'] = 'Student record added successfully.';
                    redirectToStudentManagement(['status' => 'Active']);
                }

                $existingStudent = fetchStudentById($mysqli, $editId);
                if ($existingStudent === null) {
                    $errors[] = 'The selected student record could not be found.';
                    $editId = null;
                } else {
                    runAuditedAdminMutation(
                        $mysqli,
                        'student.update',
                        function () use ($mysqli, $formValues, $semester, $editId): int {
                            $statement = $mysqli->prepare(
                                'UPDATE students
                                 SET full_name = ?, email = ?, branch = ?, semester = ?
                                 WHERE student_id = ?'
                            );
                            $statement->bind_param(
                                'sssii',
                                $formValues['full_name'],
                                $formValues['email'],
                                $formValues['branch'],
                                $semester,
                                $editId
                            );
                            $statement->execute();
                            $statement->close();
                            return $editId;
                        },
                        static fn (int $studentId): string => 'Updated student record #' . $studentId . '.'
                    );
                    $_SESSION['student_management_notice'] = 'Student record updated successfully.';
                    redirectToStudentManagement([
                        'search' => $search,
                        'course' => $course,
                        'status' => $selectedStatus,
                    ]);
                }
            }
        } elseif ($action === 'soft_delete') {
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($studentId === false) {
                $errors[] = 'The selected student record is invalid.';
            } else {
                $loggedStudentId = runAuditedAdminMutation(
                    $mysqli,
                    'student.deactivate',
                    function () use ($mysqli, $studentId): ?int {
                        $statement = $mysqli->prepare(
                            "UPDATE students SET status = 'Inactive' WHERE student_id = ? AND status = 'Active'"
                        );
                        $statement->bind_param('i', $studentId);
                        $statement->execute();
                        $changed = $statement->affected_rows === 1;
                        $statement->close();
                        return $changed ? $studentId : null;
                    },
                    static fn (int $changedStudentId): string => 'Marked student record #' . $changedStudentId . ' Inactive.'
                );
                if ($loggedStudentId !== null) {
                    $_SESSION['student_management_notice'] = 'Student status changed to Inactive. The record was retained.';
                    redirectToStudentManagement([
                        'search' => $search,
                        'course' => $course,
                        'status' => 'Inactive',
                    ]);
                }
                $errors[] = 'The student record was not found or is already Inactive.';
            }
        } else {
            $errors[] = 'The requested student action is not valid.';
        }

        if ($action === 'update' && is_int($editId)) {
            $editStudent = fetchStudentById($mysqli, $editId);
            if ($editStudent !== null) {
                $editStudent = array_merge($editStudent, $formValues);
            }
        }
    }

    if ($editStudent === null && isset($_GET['edit_id'])) {
        $requestedEditId = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($requestedEditId === false) {
            $errors[] = 'The selected student record is invalid.';
        } else {
            $editStudent = fetchStudentById($mysqli, $requestedEditId);
            if ($editStudent === null) {
                $errors[] = 'The selected student record could not be found.';
            } else {
                $formValues = $editStudent;
                $editId = $requestedEditId;
            }
        }
    }

    if (isset($_GET['delete_id'])) {
        $requestedDeleteId = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($requestedDeleteId === false) {
            $errors[] = 'The selected student record is invalid.';
        } else {
            $confirmStudent = fetchStudentById($mysqli, $requestedDeleteId);
            if ($confirmStudent === null || $confirmStudent['status'] !== 'Active') {
                $confirmStudent = null;
                $errors[] = 'Only an existing Active student can be marked Inactive.';
            }
        }
    }

    $students = fetchStudentRows($mysqli, $search, $course, $selectedStatus);
} catch (mysqli_sql_exception $exception) {
    error_log('Student management database query failed: ' . $exception->getMessage());
    if (($_GET['export'] ?? '') === 'csv') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('CSV export could not be generated. Please try again later.');
    }
    $errors[] = 'Student records could not be loaded or saved. Please try again.';
} catch (RuntimeException $exception) {
    error_log('Student management request failed: ' . $exception->getMessage());
    $errors[] = 'Student records could not be loaded or saved. Please try again.';
}

$filterParameters = [
    'search' => $search,
    'course' => $course,
    'status' => $selectedStatus,
];
$formValues = array_merge(
    ['full_name' => '', 'email' => '', 'branch' => '', 'semester' => ''],
    $formValues
);
$addFormValues = array_merge(
    ['full_name' => '', 'email' => '', 'branch' => '', 'semester' => ''],
    $addFormValues
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | StudentHub</title>
    <link rel="stylesheet" href="css/style.css?v=practical11-2">
</head>
<body class="student-management-page">
<main class="student-management">
    <header class="student-management-header">
        <div class="management-brand-row">
            <a class="management-brand" href="admin_dashboard.php">
                <span class="management-brand-mark" aria-hidden="true">SH</span>
                <span>StudentHub</span>
            </a>
            <span class="management-portal-label">Admin Portal</span>
        </div>
        <div class="management-title-row">
            <div>
                <p class="eyebrow">Student records</p>
                <h1>Student Management</h1>
            </div>
            <a class="button button-secondary" href="admin_dashboard.php">Back to Admin Dashboard</a>
        </div>
    </header>

    <?php if (is_string($notice) && $notice !== ''): ?>
        <p class="management-alert management-alert-success" role="status"><?= studentManagementEscape($notice) ?></p>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <p class="management-alert management-alert-error" role="alert"><?= studentManagementEscape($error) ?></p>
    <?php endforeach; ?>

    <section class="management-stats" aria-label="Student totals">
        <article class="management-stat management-stat-active">
            <span class="management-stat-label">Active students</span>
            <strong><?= $activeStudentCount === null ? 'Unavailable' : studentManagementEscape($activeStudentCount) ?></strong>
        </article>
        <article class="management-stat management-stat-inactive">
            <span class="management-stat-label">Inactive students</span>
            <strong><?= $inactiveStudentCount === null ? 'Unavailable' : studentManagementEscape($inactiveStudentCount) ?></strong>
        </article>
    </section>

    <?php if ($confirmStudent !== null): ?>
        <section class="management-panel" aria-labelledby="delete-confirmation-heading">
            <h2 id="delete-confirmation-heading">Confirm student deactivation</h2>
            <p>Mark <strong><?= studentManagementEscape($confirmStudent['full_name']) ?></strong>
                (<?= studentManagementEscape($confirmStudent['email']) ?>) as Inactive?</p>
            <p>The student record and related event registrations will remain in the database.</p>
            <form method="post" action="student_management.php" class="management-actions">
                <input type="hidden" name="csrf_token" value="<?= studentManagementEscape($csrf) ?>">
                <input type="hidden" name="action" value="soft_delete">
                <input type="hidden" name="student_id" value="<?= studentManagementEscape($confirmStudent['student_id']) ?>">
                <input type="hidden" name="search" value="<?= studentManagementEscape($search) ?>">
                <input type="hidden" name="course" value="<?= studentManagementEscape($course) ?>">
                <input type="hidden" name="status" value="<?= studentManagementEscape($selectedStatus) ?>">
                <button class="button button-danger" type="submit">Confirm: mark Inactive</button>
                <a class="button button-secondary" href="<?= studentManagementEscape(studentManagementUrl($filterParameters)) ?>">Cancel</a>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($editStudent !== null): ?>
        <section class="management-panel" aria-labelledby="edit-student-heading">
            <h2 id="edit-student-heading">Edit student #<?= studentManagementEscape($editStudent['student_id']) ?></h2>
            <form method="post" action="student_management.php" class="management-form">
                <input type="hidden" name="csrf_token" value="<?= studentManagementEscape($csrf) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" value="<?= studentManagementEscape($editStudent['student_id']) ?>">
                <input type="hidden" name="search" value="<?= studentManagementEscape($search) ?>">
                <input type="hidden" name="course" value="<?= studentManagementEscape($course) ?>">
                <input type="hidden" name="status" value="<?= studentManagementEscape($selectedStatus) ?>">
                <label>Student name
                    <input name="full_name" type="text" maxlength="100" required value="<?= studentManagementEscape($formValues['full_name']) ?>">
                </label>
                <label>Email
                    <input name="email" type="email" maxlength="254" required value="<?= studentManagementEscape($formValues['email']) ?>">
                </label>
                <label>Course / branch
                    <select name="branch" required>
                        <option value="">Select a course / branch</option>
                        <?php foreach ($courseOptions as $courseOption): ?>
                            <option value="<?= studentManagementEscape($courseOption) ?>" <?= $formValues['branch'] === $courseOption ? 'selected' : '' ?>><?= studentManagementEscape($courseOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Semester
                    <input name="semester" type="number" min="1" max="12" required value="<?= studentManagementEscape($formValues['semester']) ?>">
                </label>
                <p class="management-help">Current status is <?= studentManagementEscape($editStudent['status'] ?? 'unchanged') ?> and is not changed by editing these fields.</p>
                <div class="management-actions">
                    <button class="button button-primary" type="submit">Save changes</button>
                    <a class="button button-secondary" href="<?= studentManagementEscape(studentManagementUrl($filterParameters)) ?>">Cancel</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="management-panel" aria-labelledby="add-student-heading">
        <h2 id="add-student-heading">Add Student</h2>
        <form method="post" action="student_management.php" class="management-form">
            <input type="hidden" name="csrf_token" value="<?= studentManagementEscape($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="search" value="<?= studentManagementEscape($search) ?>">
            <input type="hidden" name="course" value="<?= studentManagementEscape($course) ?>">
            <input type="hidden" name="status" value="<?= studentManagementEscape($selectedStatus) ?>">
            <label>Student name
                <input name="full_name" type="text" maxlength="100" required value="<?= studentManagementEscape($addFormValues['full_name']) ?>">
            </label>
            <label>Email
                <input name="email" type="email" maxlength="254" required value="<?= studentManagementEscape($addFormValues['email']) ?>">
            </label>
            <label>Course / branch
                <select name="branch" required>
                    <option value="">Select a course / branch</option>
                    <?php foreach ($courseOptions as $courseOption): ?>
                        <option value="<?= studentManagementEscape($courseOption) ?>" <?= $addFormValues['branch'] === $courseOption ? 'selected' : '' ?>><?= studentManagementEscape($courseOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Semester
                <input name="semester" type="number" min="1" max="12" required value="<?= studentManagementEscape($addFormValues['semester']) ?>">
            </label>
            <p class="management-help">New student records start with Active status. Adding a record does not create a login account.</p>
            <button class="button button-primary" type="submit">Add student</button>
        </form>
    </section>

    <section class="management-panel" aria-labelledby="student-records-heading">
        <div class="management-section-heading">
            <div>
                <h2 id="student-records-heading">Student Records</h2>
                <p class="management-help">Course filtering uses the existing branch values in the database.</p>
            </div>
        </div>
        <form method="get" action="student_management.php" class="management-filters">
            <label>Search
                <input type="search" name="search" maxlength="150" value="<?= studentManagementEscape($search) ?>" placeholder="Name, email, or course">
            </label>
            <label>Course / branch
                <select name="course">
                    <option value="">All courses</option>
                    <?php foreach ($courseOptions as $courseOption): ?>
                        <option value="<?= studentManagementEscape($courseOption) ?>" <?= $course === $courseOption ? 'selected' : '' ?>><?= studentManagementEscape($courseOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="Active" <?= $selectedStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $selectedStatus === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="All" <?= $selectedStatus === 'All' ? 'selected' : '' ?>>All statuses</option>
                </select>
            </label>
            <div class="management-actions">
                <button class="button button-primary" type="submit">Search / filter</button>
                <a class="button button-secondary" href="student_management.php">Clear</a>
                <a class="button button-secondary" href="<?= studentManagementEscape(studentManagementUrl($filterParameters + ['export' => 'csv'])) ?>">Export CSV</a>
            </div>
        </form>

        <?php if ($students === []): ?>
            <p class="management-empty">No student records match the selected search and filters.</p>
        <?php else: ?>
            <div class="management-table-wrap">
                <table class="management-table">
                    <thead>
                        <tr>
                            <th scope="col">Student ID</th>
                            <th scope="col">Student name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Course / branch</th>
                            <th scope="col">Semester</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= studentManagementEscape($student['student_id']) ?></td>
                            <td><?= studentManagementEscape($student['full_name']) ?></td>
                            <td><?= studentManagementEscape($student['email']) ?></td>
                            <td><?= studentManagementEscape($student['branch']) ?></td>
                            <td><?= studentManagementEscape($student['semester']) ?></td>
                            <td><span class="status-badge status-<?= strtolower(studentManagementEscape($student['status'])) ?>"><?= studentManagementEscape($student['status']) ?></span></td>
                            <td class="management-row-actions">
                                <a href="<?= studentManagementEscape(studentManagementUrl($filterParameters + ['edit_id' => $student['student_id']])) ?>">Edit</a>
                                <?php if ($student['status'] === 'Active'): ?>
                                    <a class="management-delete-link" href="<?= studentManagementEscape(studentManagementUrl($filterParameters + ['delete_id' => $student['student_id']])) ?>">Delete</a>
                                <?php else: ?>
                                    <span>Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
