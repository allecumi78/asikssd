<?php

namespace App\Services;

use App\Core\Database;

class AuditLogger
{
    public static function log(string $action, string $tableName, ?int $recordId = null, array $old = [], array $new = []): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, school_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
             VALUES (:user_id, :school_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent, NOW())'
        );

        $stmt->execute([
            'user_id' => $_SESSION['user']['id'] ?? null,
            'school_id' => $_SESSION['user']['school_id'] ?? null,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }
}
