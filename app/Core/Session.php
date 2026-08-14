<?php

namespace App\Core;

class Session
{
    public static function start(): void
    {
        $app = require dirname(__DIR__, 2) . '/config/app.php';

        if (session_status() === PHP_SESSION_NONE) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            session_name($app['session_name']);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            ]);
            session_start();
        }
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        self::start();

        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }
}
