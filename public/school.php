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
AuthMiddleware::requirePermission('school');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = Session::flash('success');

$fields = [
    'npsn' => 'NPSN',
    'name' => 'Nama Sekolah',
    'principal_name' => 'Nama Kepala Sekolah',
    'principal_nip' => 'NIP Kepala Sekolah',
    'status' => 'Status Sekolah',
    'level' => 'Jenjang',
    'address' => 'Alamat',
    'rt_rw' => 'RT/RW',
    'village' => 'Desa/Kelurahan',
    'district' => 'Kecamatan',
    'city' => 'Kabupaten/Kota',
    'province' => 'Provinsi',
    'postal_code' => 'Kode Pos',
    'email' => 'Email',
    'phone' => 'Nomor Telepon',
    'curriculum' => 'Kurikulum',
];

$schoolStmt = $pdo->prepare('SELECT * FROM schools WHERE id = :id');
$schoolStmt->execute(['id' => $user['school_id']]);
$school = $schoolStmt->fetch();
$adminLogo = 'assets/img/logo-asikssd.png';

$yearStmt = $pdo->prepare('SELECT * FROM academic_years WHERE school_id = :school_id AND is_active = 1 LIMIT 1');
$yearStmt->execute(['school_id' => $user['school_id']]);
$academicYear = $yearStmt->fetch() ?: ['name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    $payload = [];
    foreach ($fields as $field => $label) {
        $payload[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $yearName = trim((string) ($_POST['academic_year'] ?? ''));
    $semester = 'Genap';

    foreach (['npsn', 'name', 'principal_name', 'status', 'level', 'city', 'province', 'curriculum'] as $required) {
        if ($payload[$required] === '') {
            $errors[] = $fields[$required] . ' wajib diisi.';
        }
    }

    if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($yearName === '') {
        $errors[] = 'Tahun pelajaran wajib diisi.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $oldSchool = $school;
            $stmt = $pdo->prepare(
                'UPDATE schools SET
                    npsn = :npsn,
                    name = :name,
                    principal_name = :principal_name,
                    principal_nip = :principal_nip,
                    status = :status,
                    level = :level,
                    address = :address,
                    rt_rw = :rt_rw,
                    village = :village,
                    district = :district,
                    city = :city,
                    province = :province,
                    postal_code = :postal_code,
                    email = :email,
                    phone = :phone,
                    curriculum = :curriculum
                 WHERE id = :id'
            );
            $payload['id'] = $user['school_id'];
            $stmt->execute($payload);

            if (!empty($academicYear['id'])) {
                $yearUpdate = $pdo->prepare('UPDATE academic_years SET name = :name, semester = :semester, is_active = 1 WHERE id = :id');
                $yearUpdate->execute(['name' => $yearName, 'semester' => $semester, 'id' => $academicYear['id']]);
            } else {
                $yearInsert = $pdo->prepare('INSERT INTO academic_years (school_id, name, semester, is_active) VALUES (:school_id, :name, :semester, 1)');
                $yearInsert->execute(['school_id' => $user['school_id'], 'name' => $yearName, 'semester' => $semester]);
            }

            $_SESSION['user']['school_name'] = $payload['name'];
            $_SESSION['user']['npsn'] = $payload['npsn'];
            AuditLogger::log('UPDATE', 'schools', (int) $user['school_id'], $oldSchool, $payload);
            $pdo->commit();
            Session::flash('success', 'Data sekolah berhasil diperbarui.');
            header('Location: school.php');
            exit;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = 'Data gagal disimpan: ' . $exception->getMessage();
        }
    }

    $school = array_merge($school, $payload);
    $academicYear = ['name' => $yearName];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Sekolah - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="admin-body">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo-image"><img src="<?= Security::e($adminLogo) ?>" alt="Logo ASIKSSD"></div>
            <div><strong>ASIKSSD</strong><span>Kelulusan SD</span></div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
            <a class="active" href="school.php"><i class="fa-solid fa-school"></i><span>Data Sekolah</span></a>
            <a href="students.php"><i class="fa-solid fa-users"></i><span>Data Siswa</span></a>
            <a href="grades.php"><i class="fa-solid fa-clipboard-list"></i><span>Data Nilai</span></a>
            <a href="grade_recap.php"><i class="fa-solid fa-chart-column"></i><span>Rekap Nilai</span></a>
            <a href="graduation.php"><i class="fa-solid fa-user-graduate"></i><span>Kelulusan</span></a>
            <a href="reports.php"><i class="fa-solid fa-file-lines"></i><span>Laporan</span></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i><span>Pengaturan</span></a>
        </nav>
        <div class="sidebar-credit">Contributor: By. Rojali</div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <button class="icon-button" id="sidebarToggle" type="button" aria-label="Ciutkan sidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Data Sekolah</h1>
                <p>Kelola identitas sekolah dan tahun pelajaran aktif.</p>
            </div>
            <div class="topbar-actions">
                <button class="icon-button" id="themeToggle" type="button" aria-label="Mode gelap"><i class="fa-solid fa-moon"></i></button>
                <button class="icon-button" id="fullscreenToggle" type="button" aria-label="Layar penuh"><i class="fa-solid fa-expand"></i></button>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="toast-message success"><i class="fa-solid fa-circle-check"></i><?= Security::e($success) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger mt-3">
                <?php foreach ($errors as $error): ?>
                    <div><?= Security::e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="panel mt-3">
            <div class="panel-header">
                <div>
                    <h2>Identitas Sekolah</h2>
                    <p>Data ini digunakan pada dashboard, laporan, dan dokumen administrasi kelulusan.</p>
                </div>
                <span class="badge text-bg-primary">Tersimpan di Database</span>
            </div>

            <form method="post" class="data-form" id="schoolForm" novalidate>
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <div class="form-grid">
                    <?php foreach ($fields as $field => $label): ?>
                        <div class="<?= $field === 'address' ? 'form-span-2' : '' ?>">
                            <label class="form-label" for="<?= Security::e($field) ?>"><?= Security::e($label) ?></label>
                            <?php if ($field === 'address'): ?>
                                <textarea class="form-control" id="<?= Security::e($field) ?>" name="<?= Security::e($field) ?>" rows="3"><?= Security::e($school[$field] ?? '') ?></textarea>
                            <?php elseif ($field === 'status'): ?>
                                <select class="form-select" id="status" name="status">
                                    <?php foreach (['Negeri', 'Swasta'] as $option): ?>
                                        <option value="<?= $option ?>" <?= ($school[$field] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field === 'level'): ?>
                                <select class="form-select" id="level" name="level">
                                    <?php foreach (['SD', 'MI'] as $option): ?>
                                        <option value="<?= $option ?>" <?= ($school[$field] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input class="form-control" id="<?= Security::e($field) ?>" name="<?= Security::e($field) ?>" value="<?= Security::e($school[$field] ?? '') ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <label class="form-label" for="academic_year">Tahun Pelajaran</label>
                        <input class="form-control" id="academic_year" name="academic_year" value="<?= Security::e($academicYear['name'] ?? '') ?>" placeholder="2025/2026">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Simpan</button>
                    <a class="btn btn-outline-secondary" href="school.php">Batal</a>
                    <button class="btn btn-outline-warning" type="reset">Reset</button>
                </div>
            </form>
        </section>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
