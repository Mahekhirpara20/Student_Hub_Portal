<?php
// XAMPP defaults are used only when the corresponding environment variable
// is not set. Change these values or set the variables to match your MySQL.
$dbHost = getenv('STUDENTHUB_DB_HOST');
$dbName = getenv('STUDENTHUB_DB_NAME');
$dbUser = getenv('STUDENTHUB_DB_USER');
$dbPassword = getenv('STUDENTHUB_DB_PASSWORD');

$dbHost = $dbHost !== false ? $dbHost : 'localhost';
$dbName = $dbName !== false ? $dbName : 'studenthub_db';
$dbUser = $dbUser !== false ? $dbUser : 'root';
$dbPassword = $dbPassword !== false ? $dbPassword : '';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('StudentHub database connection failed: ' . $exception->getMessage());
    throw new RuntimeException('The database connection could not be established.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
    $mysqli->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('StudentHub MySQLi connection failed: ' . $exception->getMessage());
    throw new RuntimeException('The database connection could not be established.');
}
