<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';
require_once dirname(__DIR__) . '/app/Services/AuditLogger.php';

use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Services\AuditLogger;
use App\Utilities\Security;

Session::start();
AuthMiddleware::requirePermission('settings');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = Session::flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    $action = $_POST['action'] ?? '';
    if (!$errors && $action === 'graduation_rule') {
        $minimum = (float) ($_POST['minimum_score'] ?? 0);
        $reportWeight = (float) ($_POST['report_weight'] ?? 0);
        $assessmentWeight = (float) ($_POST['assessment_weight'] ?? 0);
        if ($minimum < 0 || $minimum > 100 || ($reportWeight + $assessmentWeight) !== 100.0) {
            $errors[] = 'Nilai minimum harus 0-100 dan total bobot harus 100%.';
        } else {
            $stmt = $pdo->prepare('UPDATE graduation_rules SET minimum_score = :minimum, report_weight = :report_weight, assessment_weight = :assessment_weight, require_complete_grades = :complete, require_administration = :admin WHERE school_id = :school_id AND is_active = 1');
            $stmt->execute([
                'minimum' => $minimum,
                'report_weight' => $reportWeight,
                'assessment_weight' => $assessmentWeight,
                'complete' => isset($_POST['require_complete_grades']) ? 1 : 0,
                'admin' => isset($_POST['require_administration']) ? 1 : 0,
                'school_id' => $user['school_id'],
            ]);
            AuditLogger::log('UPDATE', 'graduation_rules', null, [], $_POST);
            Session::flash('success', 'Konfigurasi kelulusan berhasil disimpan.');
            header('Location: settings.php');
            exit;
        }
    }

    if (!$errors && $action === 'upload_logo') {
        if (empty($_FILES['logo_file']['tmp_name']) || ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'File logo wajib dipilih.';
        } elseif (($_FILES['logo_file']['size'] ?? 0) > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran logo maksimal 2 MB.';
        } else {
            $mime = mime_content_type($_FILES['logo_file']['tmp_name']);
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                $errors[] = 'Logo harus berformat PNG, JPG, atau WEBP.';
            } else {
                $logoDir = __DIR__ . '/assets/img/uploads';
                if (!is_dir($logoDir)) {
                    mkdir($logoDir, 0775, true);
                }
                $filename = 'logo-sekolah-' . $user['school_id'] . '-' . date('YmdHis') . '.' . $allowed[$mime];
                $relativePath = 'assets/img/uploads/' . $filename;
                move_uploaded_file($_FILES['logo_file']['tmp_name'], __DIR__ . '/' . $relativePath);
                $pdo->prepare('UPDATE schools SET logo_path = :logo_path WHERE id = :school_id')
                    ->execute(['logo_path' => $relativePath, 'school_id' => $user['school_id']]);
                AuditLogger::log('UPDATE_LOGO', 'schools', (int) $user['school_id'], [], ['logo_path' => $relativePath]);
                Session::flash('success', 'Logo tampilan login siswa berhasil diperbarui.');
                header('Location: settings.php');
                exit;
            }
        }
    }

    if (!$errors && $action === 'upload_skl_header') {
        if (empty($_FILES['skl_header_file']['tmp_name']) || ($_FILES['skl_header_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'File kop SKL wajib dipilih.';
        } elseif (($_FILES['skl_header_file']['size'] ?? 0) > 3 * 1024 * 1024) {
            $errors[] = 'Ukuran kop SKL maksimal 3 MB.';
        } else {
            $mime = mime_content_type($_FILES['skl_header_file']['tmp_name']);
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                $errors[] = 'Kop SKL harus berformat PNG, JPG, atau WEBP.';
            } else {
                $headerDir = __DIR__ . '/assets/img/uploads';
                if (!is_dir($headerDir)) {
                    mkdir($headerDir, 0775, true);
                }
                $filename = 'kop-skl-' . $user['school_id'] . '-' . date('YmdHis') . '.' . $allowed[$mime];
                $relativePath = 'assets/img/uploads/' . $filename;
                move_uploaded_file($_FILES['skl_header_file']['tmp_name'], __DIR__ . '/' . $relativePath);
                $stmt = $pdo->prepare(
                    'INSERT INTO settings (school_id, setting_key, setting_value)
                     VALUES (:school_id, "skl_header_path", :setting_value)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                $stmt->execute(['school_id' => $user['school_id'], 'setting_value' => $relativePath]);
                AuditLogger::log('UPDATE', 'settings', null, [], ['skl_header_path' => $relativePath]);
                Session::flash('success', 'Kop SKL berhasil diperbarui.');
                header('Location: settings.php');
                exit;
            }
        }
    }

    if (!$errors && $action === 'announcement_timer') {
        $announcementInput = trim((string) ($_POST['announcement_at'] ?? ''));
        if ($announcementInput === '') {
            $errors[] = 'Jadwal pengumuman wajib diisi.';
        } else {
            $timestamp = strtotime($announcementInput);
            if ($timestamp === false) {
                $errors[] = 'Format jadwal pengumuman tidak valid.';
            } else {
                $announcementAt = date('Y-m-d H:i:s', $timestamp);
                $stmt = $pdo->prepare(
                    'INSERT INTO settings (school_id, setting_key, setting_value)
                     VALUES (:school_id, "announcement_at", :setting_value)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                $stmt->execute(['school_id' => $user['school_id'], 'setting_value' => $announcementAt]);
                AuditLogger::log('UPDATE', 'settings', null, [], ['announcement_at' => $announcementAt]);
                Session::flash('success', 'Jadwal pengumuman berhasil disimpan.');
                header('Location: settings.php');
                exit;
            }
        }
    }

    if (!$errors && $action === 'create_user') {
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || $email === '' || strlen($password) < 8) {
            $errors[] = 'Nama, email, dan password minimal 8 karakter wajib diisi.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (school_id, role_id, name, email, password_hash) VALUES (:school_id, :role_id, :name, :email, :password_hash)');
            $stmt->execute([
                'school_id' => $user['school_id'],
                'role_id' => $roleId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            AuditLogger::log('CREATE', 'users', (int) $pdo->lastInsertId(), [], ['name' => $name, 'email' => $email]);
            Session::flash('success', 'User baru berhasil dibuat.');
            header('Location: settings.php');
            exit;
        }
    }

    if (!$errors && $action === 'toggle_user') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === (int) $user['id']) {
            $errors[] = 'Akun yang sedang digunakan tidak dapat dinonaktifkan sendiri.';
        } else {
            $oldStmt = $pdo->prepare('SELECT id, name, email, is_active FROM users WHERE id = :id AND school_id = :school_id');
            $oldStmt->execute(['id' => $targetUserId, 'school_id' => $user['school_id']]);
            $old = $oldStmt->fetch();
            if (!$old) {
                $errors[] = 'User tidak ditemukan.';
            } else {
                $newStatus = (int) $old['is_active'] ? 0 : 1;
                $pdo->prepare('UPDATE users SET is_active = :status WHERE id = :id AND school_id = :school_id')->execute(['status' => $newStatus, 'id' => $targetUserId, 'school_id' => $user['school_id']]);
                AuditLogger::log('UPDATE', 'users', $targetUserId, $old, ['is_active' => $newStatus]);
                Session::flash('success', 'Status user berhasil diperbarui.');
                header('Location: settings.php');
                exit;
            }
        }
    }

    if (!$errors && $action === 'reset_password') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newPassword = (string) ($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            $errors[] = 'Password baru minimal 8 karakter.';
        } else {
            $oldStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id AND school_id = :school_id');
            $oldStmt->execute(['id' => $targetUserId, 'school_id' => $user['school_id']]);
            $old = $oldStmt->fetch();
            if (!$old) {
                $errors[] = 'User tidak ditemukan.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id AND school_id = :school_id')->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $targetUserId, 'school_id' => $user['school_id']]);
                AuditLogger::log('RESET_PASSWORD', 'users', $targetUserId, $old, ['password_hash' => 'updated']);
                Session::flash('success', 'Password user berhasil direset.');
                header('Location: settings.php');
                exit;
            }
        }
    }

    if (!$errors && $action === 'backup') {
        $backupDir = dirname(__DIR__) . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }
        $filename = $backupDir . '/backup-asikssd-' . date('Ymd-His') . '.sql';
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $sql = "-- Backup ASIKSSD " . date('c') . "\n\n";
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_map(fn($col) => "`{$col}`", array_keys($row));
                $vals = array_map(fn($val) => $val === null ? 'NULL' : $pdo->quote((string) $val), array_values($row));
                $sql .= "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }
        file_put_contents($filename, $sql);
        AuditLogger::log('BACKUP', 'settings', null, [], ['file' => basename($filename)]);
        Session::flash('success', 'Backup database berhasil dibuat.');
        header('Location: settings.php');
        exit;
    }

    if (!$errors && $action === 'clear_audit_logs') {
        $mode = $_POST['clear_mode'] ?? 'old';
        if ($mode === 'all') {
            $deleted = $pdo->prepare('DELETE FROM audit_logs WHERE school_id = :school_id');
            $deleted->execute(['school_id' => $user['school_id']]);
            $message = 'Semua audit log berhasil dibersihkan.';
        } else {
            $deleted = $pdo->prepare('DELETE FROM audit_logs WHERE school_id = :school_id AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
            $deleted->execute(['school_id' => $user['school_id']]);
            $message = 'Audit log lebih dari 30 hari berhasil dibersihkan.';
        }

        $deletedCount = $deleted->rowCount();
        AuditLogger::log('CLEAR_AUDIT_LOGS', 'audit_logs', null, [], ['mode' => $mode, 'deleted' => $deletedCount]);
        Session::flash('success', $message . ' Total terhapus: ' . $deletedCount . '.');
        header('Location: settings.php');
        exit;
    }

    if (!$errors && $action === 'restore') {
        if (empty($_FILES['restore_file']['tmp_name']) || ($_FILES['restore_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'File restore wajib dipilih.';
        } elseif (($_FILES['restore_file']['size'] ?? 0) > 10 * 1024 * 1024) {
            $errors[] = 'Ukuran file restore maksimal 10 MB.';
        } elseif (strtolower(pathinfo($_FILES['restore_file']['name'], PATHINFO_EXTENSION)) !== 'sql') {
            $errors[] = 'File restore harus berformat .sql.';
        } else {
            $sql = file_get_contents($_FILES['restore_file']['tmp_name']);
            if ($sql === false || trim($sql) === '') {
                $errors[] = 'File restore kosong atau tidak dapat dibaca.';
            } elseif (!str_starts_with(ltrim($sql), '-- Backup ASIKSSD')) {
                $errors[] = 'Restore ditolak. Gunakan file backup yang dibuat oleh ASIKSSD.';
            } else {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                $pdo->exec($sql);
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                AuditLogger::log('RESTORE', 'settings', null, [], ['file' => $_FILES['restore_file']['name']]);
                Session::flash('success', 'Restore database berhasil dijalankan.');
                header('Location: settings.php');
                exit;
            }
        }
    }
}

$rule = $pdo->prepare('SELECT * FROM graduation_rules WHERE school_id = :school_id AND is_active = 1 ORDER BY id DESC LIMIT 1');
$rule->execute(['school_id' => $user['school_id']]);
$rule = $rule->fetch();
$roles = $pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll();
$users = $pdo->prepare('SELECT users.*, roles.name AS role_name FROM users INNER JOIN roles ON roles.id = users.role_id WHERE users.school_id = :school_id ORDER BY users.name');
$users->execute(['school_id' => $user['school_id']]);
$users = $users->fetchAll();
$logs = $pdo->prepare('SELECT audit_logs.*, users.name AS user_name FROM audit_logs LEFT JOIN users ON users.id = audit_logs.user_id WHERE audit_logs.school_id = :school_id ORDER BY audit_logs.created_at DESC LIMIT 30');
$logs->execute(['school_id' => $user['school_id']]);
$logs = $logs->fetchAll();
$auditCountStmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE school_id = :school_id');
$auditCountStmt->execute(['school_id' => $user['school_id']]);
$auditLogCount = (int) $auditCountStmt->fetchColumn();
$backupFiles = glob(dirname(__DIR__) . '/storage/backups/*.sql') ?: [];
$schoolStmt = $pdo->prepare('SELECT logo_path FROM schools WHERE id = :school_id');
$schoolStmt->execute(['school_id' => $user['school_id']]);
$schoolLogo = $schoolStmt->fetchColumn() ?: 'assets/img/logo-asikssd.png';
$announcementStmt = $pdo->prepare('SELECT setting_value FROM settings WHERE school_id = :school_id AND setting_key = "announcement_at" LIMIT 1');
$announcementStmt->execute(['school_id' => $user['school_id']]);
$announcementAt = $announcementStmt->fetchColumn() ?: '2026-06-15 10:00:00';
$announcementInputValue = date('Y-m-d\TH:i', strtotime($announcementAt));
$sklHeaderStmt = $pdo->prepare('SELECT setting_value FROM settings WHERE school_id = :school_id AND setting_key = "skl_header_path" LIMIT 1');
$sklHeaderStmt->execute(['school_id' => $user['school_id']]);
$sklHeaderPath = $sklHeaderStmt->fetchColumn() ?: '';
$adminLogo = 'assets/img/logo-asikssd.png';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengaturan - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="admin-body">
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="sidebar-logo-image"><img src="<?= Security::e($adminLogo) ?>" alt="Logo ASIKSSD"></div><div><strong>ASIKSSD</strong><span>Kelulusan SD</span></div></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
            <a href="school.php"><i class="fa-solid fa-school"></i><span>Data Sekolah</span></a>
            <a href="students.php"><i class="fa-solid fa-users"></i><span>Data Siswa</span></a>
            <a href="grades.php"><i class="fa-solid fa-clipboard-list"></i><span>Data Nilai</span></a>
            <a href="grade_recap.php"><i class="fa-solid fa-chart-column"></i><span>Rekap Nilai</span></a>
            <a href="graduation.php"><i class="fa-solid fa-user-graduate"></i><span>Kelulusan</span></a>
            <a href="reports.php"><i class="fa-solid fa-file-lines"></i><span>Laporan</span></a>
            <a class="active" href="settings.php"><i class="fa-solid fa-gear"></i><span>Pengaturan</span></a>
        </nav>
        <div class="sidebar-credit">Contributor: By. Rojali</div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <button class="icon-button" id="sidebarToggle" type="button" aria-label="Ciutkan sidebar"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Pengaturan</h1><p>Konfigurasi kelulusan, user, role, audit log, dan backup.</p></div>
            <div class="topbar-actions"><button class="icon-button" id="themeToggle" type="button"><i class="fa-solid fa-moon"></i></button><button class="icon-button" id="fullscreenToggle" type="button"><i class="fa-solid fa-expand"></i></button><a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a></div>
        </header>
        <?php if ($success): ?><div class="toast-message success"><i class="fa-solid fa-circle-check"></i><?= Security::e($success) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger mt-3"><?php foreach ($errors as $error): ?><div><?= Security::e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
        <section class="dashboard-grid mt-3">
            <article class="panel">
                <div class="panel-header"><div><h2>Logo Login Siswa</h2><p>Logo ini tampil pada halaman login siswa.</p></div></div>
                <div class="logo-preview"><img src="<?= Security::e($schoolLogo) ?>" alt="Logo login siswa"></div>
                <form method="post" enctype="multipart/form-data" class="data-form">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <input type="hidden" name="action" value="upload_logo">
                    <input class="form-control mb-2" name="logo_file" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                    <button class="btn btn-primary" type="submit">Upload Logo</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-header"><div><h2>Kop SKL</h2><p>Gambar kop resmi untuk Surat Keterangan Lulus.</p></div></div>
                <?php if ($sklHeaderPath): ?><div class="skl-header-preview"><img src="<?= Security::e($sklHeaderPath) ?>" alt="Kop SKL"></div><?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="data-form">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <input type="hidden" name="action" value="upload_skl_header">
                    <input class="form-control mb-2" name="skl_header_file" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                    <small class="text-muted d-block mb-3">Gunakan gambar kop lebar, rasio disarankan sekitar 6:1 sampai 8:1.</small>
                    <button class="btn btn-primary" type="submit">Upload Kop SKL</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-header"><div><h2>Jadwal Pengumuman</h2><p>Atur timer yang tampil pada login siswa.</p></div></div>
                <form method="post" class="data-form">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <input type="hidden" name="action" value="announcement_timer">
                    <label class="form-label" for="announcement_at">Tanggal dan Jam Pengumuman</label>
                    <input class="form-control mb-2" id="announcement_at" name="announcement_at" type="datetime-local" value="<?= Security::e($announcementInputValue) ?>" required>
                    <small class="text-muted d-block mb-3">Jika waktu sudah lewat, login siswa menampilkan status Telah Dimulai.</small>
                    <button class="btn btn-primary" type="submit">Simpan Jadwal</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-header"><div><h2>Kriteria Kelulusan</h2><p>Pastikan kriteria kelulusan telah disesuaikan dengan kebijakan sekolah yang berlaku.</p></div></div>
                <form method="post" class="data-form">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><input type="hidden" name="action" value="graduation_rule">
                    <label class="form-label">Nilai Minimum</label><input class="form-control mb-2" name="minimum_score" type="number" step="0.01" value="<?= Security::e($rule['minimum_score'] ?? 75) ?>">
                    <label class="form-label">Bobot Nilai Rapor Semester 7-11 (%)</label><input class="form-control mb-2" name="report_weight" type="number" step="0.01" value="<?= Security::e($rule['report_weight'] ?? 70) ?>">
                    <label class="form-label">Bobot ASAJ / Sumatif Akhir Jenjang (%)</label><input class="form-control mb-2" name="assessment_weight" type="number" step="0.01" value="<?= Security::e($rule['assessment_weight'] ?? 30) ?>">
                    <label class="form-check mb-2"><input class="form-check-input" name="require_complete_grades" type="checkbox" <?= !empty($rule['require_complete_grades']) ? 'checked' : '' ?>> Syarat kelengkapan nilai</label>
                    <label class="form-check mb-3"><input class="form-check-input" name="require_administration" type="checkbox" <?= !empty($rule['require_administration']) ? 'checked' : '' ?>> Syarat administrasi</label>
                    <button class="btn btn-primary" type="submit">Simpan Kriteria</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-header"><div><h2>User & Role</h2><p>Tambah akun pengguna sesuai hak akses.</p></div></div>
                <form method="post" class="data-form">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><input type="hidden" name="action" value="create_user">
                    <input class="form-control mb-2" name="name" placeholder="Nama pengguna">
                    <input class="form-control mb-2" name="email" type="email" placeholder="Email">
                    <input class="form-control mb-2" name="password" type="password" placeholder="Password minimal 8 karakter">
                    <select class="form-select mb-3" name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= Security::e($role['name']) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-primary" type="submit">Tambah User</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-header"><div><h2>Backup Data</h2><p>Buat backup SQL database.</p></div></div>
                <form method="post" class="confirm-form mb-3"><input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><input type="hidden" name="action" value="backup"><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-database me-2"></i>Buat Backup</button></form>
                <?php foreach (array_reverse($backupFiles) as $file): ?><div><a href="../storage/backups/<?= Security::e(basename($file)) ?>" download><?= Security::e(basename($file)) ?></a></div><?php endforeach; ?>
                <hr>
                <form method="post" enctype="multipart/form-data" class="confirm-form">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <input type="hidden" name="action" value="restore">
                    <label class="form-label">Restore Database (.sql)</label>
                    <input class="form-control mb-2" name="restore_file" type="file" accept=".sql">
                    <button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-rotate-left me-2"></i>Restore</button>
                </form>
            </article>
        </section>
        <section class="panel mt-3">
            <div class="panel-header"><div><h2>Daftar User</h2><p>Akun aktif pada sekolah ini.</p></div></div>
            <div class="table-responsive"><table class="table admin-table"><thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead><tbody><?php foreach ($users as $item): ?><tr><td><?= Security::e($item['name']) ?></td><td><?= Security::e($item['email']) ?></td><td><span class="badge text-bg-primary"><?= Security::e($item['role_name']) ?></span></td><td><span class="badge text-bg-<?= (int) $item['is_active'] ? 'success' : 'secondary' ?>"><?= (int) $item['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td><td><?= Security::e($item['last_login_at']) ?></td><td class="table-actions"><form method="post" class="d-inline confirm-form"><input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-outline-secondary btn-sm" type="submit"><?= (int) $item['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form><button class="btn btn-outline-warning btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-user-id="<?= (int) $item['id'] ?>" data-name="<?= Security::e($item['name']) ?>">Reset Password</button></td></tr><?php endforeach; ?></tbody></table></div>
        </section>
        <section class="panel mt-3">
            <div class="panel-header">
                <div><h2>Audit Log</h2><p>Menampilkan 30 aktivitas terbaru dari total <?= $auditLogCount ?> log.</p></div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" class="confirm-form">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="action" value="clear_audit_logs">
                        <input type="hidden" name="clear_mode" value="old">
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Hapus &gt; 30 Hari</button>
                    </form>
                    <form method="post" class="confirm-form">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="action" value="clear_audit_logs">
                        <input type="hidden" name="clear_mode" value="all">
                        <button class="btn btn-outline-danger btn-sm" type="submit">Bersihkan Semua</button>
                    </form>
                </div>
            </div>
            <div class="table-responsive"><table class="table admin-table"><thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Tabel</th><th>IP</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= Security::e($log['created_at']) ?></td><td><?= Security::e($log['user_name'] ?? '-') ?></td><td><?= Security::e($log['action']) ?></td><td><?= Security::e($log['table_name']) ?></td><td><?= Security::e($log['ip_address']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        </section>
    </main>
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id">
                <p class="text-muted" id="resetPasswordUserName"></p>
                <label class="form-label">Password Baru</label>
                <input class="form-control" name="new_password" type="password" minlength="8" required>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Simpan Password</button></div>
        </form></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
