<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function practical13Respond(int $statusCode, string $status, string $message, mixed $data = null): void
{
    http_response_code($statusCode);
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(
        ['status' => $status, 'message' => $message, 'data' => $data],
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function practical13RequireAdmin(): void
{
    $userId = $_SESSION['user_id'] ?? null;
    $email = $_SESSION['email'] ?? null;
    $role = $_SESSION['role'] ?? null;
    $lastActivity = $_SESSION['last_activity'] ?? null;

    if (
        !is_int($userId)
        || $userId < 1
        || !is_string($email)
        || $email === ''
        || !in_array($role, ['Admin', 'Student'], true)
        || !is_int($lastActivity)
    ) {
        practical13Respond(401, 'error', 'Please sign in to access the inventory API.');
    }

    if (time() - $lastActivity >= SESSION_TIMEOUT_SECONDS) {
        practical13Respond(401, 'error', 'Your session has expired. Please sign in again.');
    }

    if ($role !== 'Admin') {
        practical13Respond(403, 'error', 'Admin access is required for the inventory API.');
    }

    $_SESSION['last_activity'] = time();
}

function practical13ReadJsonObject(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        practical13Respond(400, 'error', 'A JSON request body is required.');
    }

    $decoded = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
    if (!is_object($decoded)) {
        practical13Respond(400, 'error', 'The JSON request body must be an object.');
    }

    return get_object_vars($decoded);
}

function practical13ValidateItem(array $input, bool $includeId): array
{
    $errors = [];
    $name = $input['name'] ?? null;
    $category = $input['category'] ?? null;
    $price = $input['price'] ?? null;
    $id = $input['id'] ?? null;

    if (!is_string($name) || trim($name) === '') {
        $errors[] = 'Item name is required.';
    } elseif (preg_match_all('/./us', trim($name)) > 100) {
        $errors[] = 'Item name must be 100 characters or fewer.';
    }

    if (!is_string($category) || trim($category) === '') {
        $errors[] = 'Category is required.';
    } elseif (preg_match_all('/./us', trim($category)) > 100) {
        $errors[] = 'Category must be 100 characters or fewer.';
    }

    if (
        (!is_int($price) && !is_float($price))
        || !is_finite((float) $price)
        || $price <= 0
        || $price > 99999999.99
        || abs(((float) $price * 100) - round((float) $price * 100)) > 0.00001
    ) {
        $errors[] = 'Price must be a positive number with no more than two decimal places.';
    }

    if ($includeId && (!is_int($id) || $id < 1 || $id > PHP_INT_MAX)) {
        $errors[] = 'A valid positive integer item ID is required.';
    }

    if ($errors !== []) {
        practical13Respond(400, 'error', implode(' ', $errors));
    }

    return [
        'id' => $includeId ? $id : null,
        'name' => trim($name),
        'category' => trim($category),
        'price' => number_format((float) $price, 2, '.', ''),
    ];
}

function practical13ItemFromRow(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'category' => (string) $row['category'],
        'price' => (float) $row['price'],
    ];
}

try {
    require_once __DIR__ . '/../timeout.php';
    sendNoCacheHeaders();
    startSecureSession();
    practical13RequireAdmin();

    $action = $_GET['action'] ?? null;
    if (!is_string($action) || $action === '') {
        practical13Respond(400, 'error', 'An API action is required.');
    }

    $getActions = ['read', 'get_menu', 'csrf'];
    $postActions = ['create', 'update', 'delete'];
    if (!in_array($action, array_merge($getActions, $postActions), true)) {
        practical13Respond(400, 'error', 'Unknown API action.');
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $expectedMethod = in_array($action, $getActions, true) ? 'GET' : 'POST';
    if ($method !== $expectedMethod) {
        header('Allow: ' . $expectedMethod);
        practical13Respond(405, 'error', 'This API action does not support the request method.');
    }

    if (in_array($action, $postActions, true)) {
        $postedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (
            !is_string($postedToken)
            || !isset($_SESSION['csrf_token'])
            || !is_string($_SESSION['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $postedToken)
        ) {
            practical13Respond(403, 'error', 'The security token is missing or invalid. Refresh and try again.');
        }
    }

    if ($action === 'csrf') {
        practical13Respond(200, 'success', 'Security token ready.', [
            'csrf_token' => csrfToken(),
        ]);
    }

    require_once __DIR__ . '/db.php';
    $pdo = practical13Database();
    require_once __DIR__ . '/../audit_log.php';

    if ($action === 'read') {
        $statement = $pdo->query(
            'SELECT id, name, category, price FROM items ORDER BY id DESC'
        );
        $items = array_map('practical13ItemFromRow', $statement->fetchAll());
        practical13Respond(200, 'success', 'Inventory loaded.', $items);
    }

    if ($action === 'get_menu') {
        $role = $_GET['role'] ?? 'user';
        if (!is_string($role) || !in_array($role, ['user', 'editor', 'admin'], true)) {
            practical13Respond(400, 'error', 'Role must be user, editor, or admin.');
        }

        $statement = $pdo->prepare(
            "SELECT id, title, link, min_role
             FROM menu_items
             WHERE FIELD(min_role, 'user', 'editor', 'admin')
                   <= FIELD(?, 'user', 'editor', 'admin')
             ORDER BY id ASC"
        );
        $statement->execute([$role]);
        practical13Respond(200, 'success', 'Navigation loaded.', $statement->fetchAll());
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i', trim($contentType)) !== 1) {
        practical13Respond(400, 'error', 'Send request data as application/json.');
    }

    $input = practical13ReadJsonObject();

    if ($action === 'create') {
        $item = practical13ValidateItem($input, false);
        $item = runAuditedAdminMutation(
            $pdo,
            'inventory.item.create',
            function () use ($pdo, $item): array {
                $statement = $pdo->prepare(
                    'INSERT INTO items (name, category, price) VALUES (:name, :category, :price)'
                );
                $statement->execute([
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'price' => $item['price'],
                ]);
                $item['id'] = (int) $pdo->lastInsertId();
                $item['price'] = (float) $item['price'];
                return $item;
            },
            static fn (array $createdItem): string => 'Created inventory item #' . $createdItem['id'] . '.'
        );
        practical13Respond(201, 'success', 'Item created successfully.', $item);
    }

    if ($action === 'update') {
        $item = practical13ValidateItem($input, true);
        $item = runAuditedAdminMutation(
            $pdo,
            'inventory.item.update',
            function () use ($pdo, $item): ?array {
                $statement = $pdo->prepare(
                    'UPDATE items SET name = :name, category = :category, price = :price WHERE id = :id'
                );
                $statement->execute([
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'price' => $item['price'],
                    'id' => $item['id'],
                ]);

                if ($statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM items WHERE id = ?');
                    $exists->execute([$item['id']]);
                    if ($exists->fetchColumn() === false) {
                        return null;
                    }
                }

                $item['price'] = (float) $item['price'];
                return $item;
            },
            static fn (array $updatedItem): string => 'Updated inventory item #' . $updatedItem['id'] . '.'
        );
        if ($item === null) {
            practical13Respond(404, 'error', 'The requested inventory item was not found.');
        }

        practical13Respond(200, 'success', 'Item updated successfully.', $item);
    }

    if ($action === 'delete') {
        $id = $input['id'] ?? null;
        if (!is_int($id) || $id < 1 || $id > PHP_INT_MAX) {
            practical13Respond(400, 'error', 'A valid positive integer item ID is required.');
        }

        $deletedId = runAuditedAdminMutation(
            $pdo,
            'inventory.item.delete',
            function () use ($pdo, $id): ?int {
                $statement = $pdo->prepare('DELETE FROM items WHERE id = ?');
                $statement->execute([$id]);
                return $statement->rowCount() === 1 ? $id : null;
            },
            static fn (int $deletedItemId): string => 'Deleted inventory item #' . $deletedItemId . '.'
        );
        if ($deletedId === null) {
            practical13Respond(404, 'error', 'The requested inventory item was not found.');
        }

        practical13Respond(200, 'success', 'Item deleted successfully.', ['id' => $deletedId]);
    }
} catch (JsonException $exception) {
    practical13Respond(400, 'error', 'The request body contains invalid JSON.');
} catch (PDOException $exception) {
    error_log('Practical 13 database request failed: ' . $exception->getMessage());
    practical13Respond(503, 'error', 'The inventory service is temporarily unavailable.');
} catch (Throwable $exception) {
    error_log('Practical 13 API request failed: ' . $exception->getMessage());
    practical13Respond(500, 'error', 'An unexpected server error occurred.');
}
