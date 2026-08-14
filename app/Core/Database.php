<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(?string $database = null): PDO
    {
        if (self::$connection && $database === null) {
            return self::$connection;
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $dbName = $database ?? $config['database'];
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";

        if ($dbName !== '') {
            $dsn .= ";dbname={$dbName}";
        }

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new PDOException('Koneksi database gagal: ' . $exception->getMessage(), (int) $exception->getCode());
        }

        if ($database === null) {
            self::$connection = $pdo;
        }

        return $pdo;
    }
}
