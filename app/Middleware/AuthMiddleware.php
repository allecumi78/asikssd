<?php

namespace App\Middleware;

class AuthMiddleware
{
    private const PERMISSIONS = [
        'ADMIN' => ['*'],
        'OPERATOR' => ['dashboard', 'school', 'students', 'grades', 'grade_recap', 'graduation', 'reports'],
        'GURU' => ['dashboard', 'students', 'grades', 'grade_recap'],
        'KEPALA SEKOLAH' => ['dashboard', 'grade_recap', 'graduation', 'reports'],
    ];

    public static function requireLogin(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: admin_login.php');
            exit;
        }
    }

    public static function requirePermission(string $module): void
    {
        self::requireLogin();
        $role = $_SESSION['user']['role'] ?? '';
        $allowed = self::PERMISSIONS[$role] ?? [];

        if (!in_array('*', $allowed, true) && !in_array($module, $allowed, true)) {
            http_response_code(403);
            require dirname(__DIR__, 2) . '/public/403.php';
            exit;
        }
    }
}
