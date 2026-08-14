<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';
require_once dirname(__DIR__) . '/app/Utilities/StudentPassword.php';

use App\Core\Database;
use App\Core\Session;
use App\Utilities\Security;
use App\Utilities\StudentPassword;

Session::start();
$app = require dirname(__DIR__) . '/config/app.php';
$db = require dirname(__DIR__) . '/config/database.php';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        try {
            $server = Database::connection('');
            $databaseName = str_replace('`', '', $db['database']);
            $server->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo = Database::connection($db['database']);
            $schema = file_get_contents(dirname(__DIR__) . '/database/migrations/001_create_core_schema.sql');
            $pdo->exec($schema);
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

            $seed = file_get_contents(dirname(__DIR__) . '/database/seeds/phase1_seed.sql');
            $pdo->exec($seed);

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

            $message = 'Setup berhasil. Admin: NPSN 12345678 / Admin@12345. Siswa demo: NISN 0031234567 / 12042013*.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="brand-mark">AS</div>
            <h1>Setup ASIKSSD</h1>
            <p class="text-muted">Membuat database, tabel inti, role, sekolah demo, dan akun administrator awal.</p>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= Security::e($message) ?></div>
                <a class="btn btn-primary w-100" href="admin_login.php">Masuk ke Login Admin</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= Security::e($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <button class="btn btn-primary w-100" type="submit">Jalankan Setup Phase 1</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
