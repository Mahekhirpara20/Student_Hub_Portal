<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../db.php';
} catch (RuntimeException $exception) {
    http_response_code(503);
    exit('The event database is temporarily unavailable. Please try again later.');
}