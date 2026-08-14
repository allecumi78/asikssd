<?php

namespace App\Services;

use App\Core\Database;

class AuthService
{
    public function attempt(string $npsn, string $password, bool $remember = false): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT users.*, roles.name AS role_name, schools.npsn, schools.name AS school_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             INNER JOIN schools ON schools.id = users.school_id
             WHERE schools.npsn = :npsn AND users.is_active = 1
             ORDER BY users.id ASC
             LIMIT 1'
        );
        $stmt->execute(['npsn' => $npsn]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role_name'],
            'school_id' => (int) $user['school_id'],
            'school_name' => $user['school_name'],
            'npsn' => $user['npsn'],
        ];

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
        AuditLogger::log('LOGIN', 'users', (int) $user['id']);

        return true;
    }

    public function logout(): void
    {
        if (!empty($_SESSION['user'])) {
            AuditLogger::log('LOGOUT', 'users', (int) $_SESSION['user']['id']);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
