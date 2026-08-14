<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Utilities/StudentPassword.php';

use App\Core\Database;
use App\Utilities\StudentPassword;

$config = require dirname(__DIR__) . '/config/database.php';

try {
    $server = Database::connection('');
    $databaseName = str_replace('`', '', $config['database']);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = Database::connection($config['database']);
    $pdo->exec(file_get_contents(__DIR__ . '/migrations/001_create_core_schema.sql'));
    $columnCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = "students" AND COLUMN_NAME = "password_hash"'
    );
    $columnCheck->execute(['schema' => $databaseName]);
    if ((int) $columnCheck->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE students ADD COLUMN password_hash VARCHAR(255) NULL AFTER birth_date');
    }
    $parentColumnCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = "students" AND COLUMN_NAME = "parent_name"'
    );
    $parentColumnCheck->execute(['schema' => $databaseName]);
    if ((int) $parentColumnCheck->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE students ADD COLUMN parent_name VARCHAR(150) NULL AFTER birth_date');
    }
    $pdo->exec("ALTER TABLE grades MODIFY semester ENUM('S7','S8','S9','S10','S11','ASAJ','Ganjil','Genap') NOT NULL");
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS graduation_subject_scores (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,
            graduation_rule_id BIGINT UNSIGNED NULL,
            report_average DECIMAL(5,2) NOT NULL DEFAULT 0,
            assessment_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            achievement_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            extracurricular_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            final_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY graduation_subject_unique (student_id, subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(file_get_contents(__DIR__ . '/seeds/phase1_seed.sql'));

    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'ADMIN' LIMIT 1")->fetchColumn();
    $schoolId = (int) $pdo->query("SELECT id FROM schools WHERE npsn = '12345678' LIMIT 1")->fetchColumn();
    $activeYearId = (int) $pdo->query("SELECT id FROM academic_years WHERE school_id = {$schoolId} AND name = '2025/2026' AND semester = 'Genap' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($activeYearId > 0) {
        $pdo->prepare('DELETE FROM grades WHERE school_id = :school_id AND academic_year_id <> :active_id')
            ->execute(['school_id' => $schoolId, 'active_id' => $activeYearId]);
        $pdo->prepare("DELETE FROM grades WHERE school_id = :school_id AND semester IN ('Ganjil','Genap')")
            ->execute(['school_id' => $schoolId]);
        $pdo->prepare('UPDATE graduation_rules SET academic_year_id = :active_id WHERE school_id = :school_id')
            ->execute(['active_id' => $activeYearId, 'school_id' => $schoolId]);
        $pdo->prepare('UPDATE academic_years SET is_active = CASE WHEN id = :active_id THEN 1 ELSE 0 END WHERE school_id = :school_id')
            ->execute(['active_id' => $activeYearId, 'school_id' => $schoolId]);
        $pdo->prepare('DELETE FROM academic_years WHERE school_id = :school_id AND name = "2025/2026" AND semester = "Genap" AND id <> :active_id')
            ->execute(['school_id' => $schoolId, 'active_id' => $activeYearId]);
    }

    if ($schoolId > 0) {
        $pdo->prepare(
            'INSERT INTO settings (school_id, setting_key, setting_value)
             VALUES (:school_id, "announcement_at", "2026-06-15 10:00:00")
             ON DUPLICATE KEY UPDATE setting_value = setting_value'
        )->execute(['school_id' => $schoolId]);
    }

    $exists = $pdo->prepare('SELECT id FROM users WHERE school_id = :school_id AND email = :email LIMIT 1');
    $exists->execute(['school_id' => $schoolId, 'email' => 'admin@asikssd.local']);

    if (!$exists->fetchColumn()) {
        $stmt = $pdo->prepare('INSERT INTO users (school_id, role_id, name, email, password_hash) VALUES (:school_id, :role_id, :name, :email, :password_hash)');
        $stmt->execute([
            'school_id' => $schoolId,
            'role_id' => $roleId,
            'name' => 'Administrator ASIKSSD',
            'email' => 'admin@asikssd.local',
            'password_hash' => password_hash('Admin@12345', PASSWORD_DEFAULT),
        ]);
    }

    $studentsWithoutPassword = $pdo->prepare('SELECT id, birth_date, password_hash FROM students WHERE school_id = :school_id');
    $studentsWithoutPassword->execute(['school_id' => $schoolId]);
    $passwordUpdate = $pdo->prepare('UPDATE students SET password_hash = :hash WHERE id = :id');
    foreach ($studentsWithoutPassword as $student) {
        $defaultPassword = StudentPassword::defaultFromBirthDate($student['birth_date']);
        $usesOldDefault = !empty($student['password_hash']) && password_verify('Siswa@12345', $student['password_hash']);
        if ($defaultPassword !== null && (empty($student['password_hash']) || $usesOldDefault)) {
            $passwordUpdate->execute(['hash' => password_hash($defaultPassword, PASSWORD_DEFAULT), 'id' => $student['id']]);
        }
    }

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Setup Phase 1 berhasil.\n";
    echo "Database: {$databaseName}\n";
    echo "Jumlah tabel: " . count($tables) . "\n";
    echo "Login admin: NPSN 12345678 / Password Admin@12345\n";
    echo "Login siswa demo: NISN 0031234567 / Password 12042013*\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Setup gagal: {$exception->getMessage()}\n");
    exit(1);
}
