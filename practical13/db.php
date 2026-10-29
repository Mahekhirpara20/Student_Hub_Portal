<?php
declare(strict_types=1);

function practical13Database(): PDO
{
    $dbHost = getenv('STUDENTHUB_DB_HOST');
    $dbName = getenv('STUDENTHUB_DB_NAME');
    $dbUser = getenv('STUDENTHUB_DB_USER');
    $dbPassword = getenv('STUDENTHUB_DB_PASSWORD');

    $dbHost = $dbHost !== false ? $dbHost : 'localhost';
    $dbName = $dbName !== false ? $dbName : 'studenthub_db';
    $dbUser = $dbUser !== false ? $dbUser : 'root';
    $dbPassword = $dbPassword !== false ? $dbPassword : '';

    return new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
