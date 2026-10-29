<?php
declare(strict_types=1);

function runAuditedAdminMutation(
    mysqli|PDO $database,
    string $action,
    callable $mutation,
    callable $details
): mixed {
    $userId = $_SESSION['user_id'] ?? null;
    if (($_SESSION['role'] ?? null) !== 'Admin' || !is_int($userId) || $userId < 1) {
        throw new RuntimeException('An authenticated administrator is required for this action.');
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $ipAddress = is_string($remoteAddress) && filter_var($remoteAddress, FILTER_VALIDATE_IP)
        ? $remoteAddress
        : null;

    if ($database instanceof mysqli) {
        $database->begin_transaction();
    } else {
        $database->beginTransaction();
    }

    try {
        $result = $mutation();
        if ($result !== null) {
            $description = $details($result);
            if (!is_string($description) || $description === '') {
                throw new RuntimeException('The audit description is invalid.');
            }
            $statement = $database->prepare(
                'INSERT INTO audit_logs (user_id, action, details, ip_address)
                 VALUES (?, ?, ?, ?)'
            );
            if ($database instanceof mysqli) {
                $statement->bind_param('isss', $userId, $action, $description, $ipAddress);
                $statement->execute();
                $statement->close();
            } else {
                $statement->execute([$userId, $action, $description, $ipAddress]);
            }
        }
        $database->commit();

        return $result;
    } catch (Throwable $exception) {
        if ($database instanceof mysqli) {
            $database->rollback();
        } elseif ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}
