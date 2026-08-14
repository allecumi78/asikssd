<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';
require_once dirname(__DIR__) . '/app/Services/StudentAuthService.php';

use App\Core\Session;
use App\Core\Database;
use App\Services\StudentAuthService;
use App\Utilities\Security;

Session::start();
$app = require dirname(__DIR__) . '/config/app.php';
$auth = new StudentAuthService();
$error = null;
$pdo = Database::connection();
$school = $pdo->query('SELECT id, name, logo_path FROM schools ORDER BY id ASC LIMIT 1')->fetch() ?: [];
$schoolLogo = $school['logo_path'] ?: 'assets/img/logo-asikssd.png';
$schoolName = $school['name'] ?: 'ASIKSSD';
$announcementStmt = $pdo->prepare('SELECT setting_value FROM settings WHERE school_id = :school_id AND setting_key = "announcement_at" LIMIT 1');
$announcementStmt->execute(['school_id' => (int) ($school['id'] ?? 0)]);
$announcementAt = $announcementStmt->fetchColumn() ?: '2026-06-15 10:00:00';
$monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$announcementTs = strtotime($announcementAt) ?: strtotime('2026-06-15 10:00:00');
$announcementAt = date('Y-m-d H:i:s', $announcementTs);
$announcementLabel = date('j', $announcementTs) . ' ' . $monthNames[(int) date('n', $announcementTs)] . ' ' . date('Y H:i', $announcementTs) . ' WIB';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisn = trim((string) ($_POST['nisn'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $rateKey = 'student_login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'local');
    $attempt = $_SESSION[$rateKey] ?? ['count' => 0, 'until' => 0];

    if (($attempt['until'] ?? 0) > time()) {
        $error = 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.';
    } elseif (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($nisn === '' || $password === '') {
        $error = 'NISN dan password wajib diisi.';
    } elseif ($auth->attempt($nisn, $password)) {
        unset($_SESSION[$rateKey]);
        header('Location: student_home.php');
        exit;
    } else {
        $attempt['count'] = (int) ($attempt['count'] ?? 0) + 1;
        if ($attempt['count'] >= 5) {
            $attempt = ['count' => 0, 'until' => time() + 300];
        }
        $_SESSION[$rateKey] = $attempt;
        $error = 'NISN atau password tidak sesuai.';
    }
}

if (!empty($_SESSION['student'])) {
    header('Location: student_home.php');
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Siswa - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="student-login-body">
    <main class="student-login-page">
        <header class="student-login-header">
            <div class="student-school-logo"><img src="<?= Security::e($schoolLogo) ?>" alt="Logo ASIKSSD"></div>
            <div>
                <h1><?= Security::e(strtoupper($schoolName)) ?></h1>
                <strong>JAKARTA KEPULAUAN SERIBU</strong>
                <p>Cerdas, Berkarakter, dan Berprestasi</p>
            </div>
        </header>

        <section class="student-login-grid">
            <div class="student-info-column">
                <article class="student-info-card">
                    <div class="school-year">Tahun Pelajaran 2025/2026</div>
                    <div class="student-brand-title">
                        <span>ASIKSSD</span>
                        <em>Sistem Informasi Kelulusan Siswa SD</em>
                    </div>
                    <p>Masukkan NISN dan password untuk melihat informasi kelulusan secara resmi, aman, dan nyaman.</p>
                    <div class="announcement-box">
                        <strong>Jadwal Pengumuman</strong>
                        <span><?= Security::e($announcementLabel) ?></span>
                        <div class="countdown-timer" id="announcementTimer" data-target="<?= Security::e($announcementAt) ?>">
                            <div class="time-cell"><b>--</b><small>Memuat</small></div>
                        </div>
                    </div>
                </article>
                <div class="app-credit announcement-credit">Contributor: By. Rojali</div>
            </div>

            <article class="student-login-card">
                <span class="student-eyebrow">Cek Kelulusan</span>
                <h2>Halo, Anak Hebat!</h2>
                <p>Isi data sesuai akun siswa yang diberikan sekolah.</p>
                <?php if ($error): ?><div class="alert alert-danger"><?= Security::e($error) ?></div><?php endif; ?>
                <form method="post" class="student-check-form" novalidate>
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <label class="form-label" for="nisn">NISN</label>
                    <input class="form-control" id="nisn" name="nisn" maxlength="40" placeholder="Contoh: 0123456789" required autofocus>
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password" placeholder="Masukkan password siswa" required>
                    <button class="btn student-submit w-100" type="submit">Lihat Pengumuman</button>
                    <small>Gunakan NISN dan password sesuai data sekolah.</small>
                </form>
            </article>
        </section>

        <footer class="student-login-footer">
            <strong>ASIKSSD v2.10</strong>
            <span>- Sistem Informasi Kelulusan Siswa Sekolah Dasar</span>
        </footer>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
