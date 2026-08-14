<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';

use App\Core\Database;
use App\Core\Session;
use App\Utilities\Security;

Session::start();
if (empty($_SESSION['student'])) {
    header('Location: index.php');
    exit;
}

$app = require dirname(__DIR__) . '/config/app.php';
$student = $_SESSION['student'];
$pdo = Database::connection();
$schoolStmt = $pdo->prepare('SELECT name, logo_path FROM schools WHERE id = :school_id LIMIT 1');
$schoolStmt->execute(['school_id' => $student['school_id']]);
$school = $schoolStmt->fetch() ?: [];
$schoolName = $school['name'] ?: ($student['school_name'] ?? 'ASIKSSD');
$schoolLogo = $school['logo_path'] ?: 'assets/img/logo-asikssd.png';
$stmt = $pdo->prepare(
    'SELECT graduation_results.final_score, graduation_results.status, graduation_results.notes, graduation_results.finalized_at
     FROM graduation_results
     WHERE graduation_results.school_id = :school_id AND graduation_results.student_id = :student_id
     LIMIT 1'
);
$stmt->execute(['school_id' => $student['school_id'], 'student_id' => $student['id']]);
$result = $stmt->fetch();
$status = $result['status'] ?? 'BELUM DIPROSES';
$isPassed = in_array($status, ['LULUS', 'MEMENUHI SYARAT'], true);
$studentName = mb_strtoupper($student['name'], 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beranda Siswa - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="student-result-body">
    <main class="student-result-page">
        <section class="student-result-card">
            <header class="result-header">
                <div class="result-brand">
                    <div class="result-logo"><img src="<?= Security::e($schoolLogo) ?>" alt="Logo Sekolah"></div>
                    <div>
                        <h1><?= Security::e(mb_strtoupper($schoolName, 'UTF-8')) ?></h1>
                        <p>JAKARTA KEPULAUAN SERIBU</p>
                    </div>
                </div>
                <div class="result-actions">
                    <?php if ($status === 'LULUS' && !empty($result['finalized_at'])): ?>
                        <a class="result-print" href="print_skl.php" target="_blank"><i class="fa-solid fa-print"></i>Cetak SKL</a>
                    <?php endif; ?>
                    <a class="result-logout" href="student_logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
                </div>
            </header>

            <div class="result-status-panel <?= $isPassed ? 'is-passed' : 'is-pending' ?>">
                <span class="result-eyebrow">Hasil Pengumuman Kelulusan</span>
                <h2><?= Security::e($status) ?></h2>
                <strong class="result-student-name"><?= Security::e($studentName) ?></strong>
                <p><?= $isPassed ? 'Selamat, Anda dinyatakan lulus berdasarkan hasil final satuan pendidikan.' : 'Status kelulusan Anda belum final atau memerlukan verifikasi.' ?></p>
            </div>

            <div class="result-content-grid">
                <article class="result-identity">
                    <h3>Identitas Siswa</h3>
                    <dl>
                        <dt>Nama Lengkap</dt>
                        <dd><?= Security::e($studentName) ?></dd>
                        <dt>NISN</dt>
                        <dd><?= Security::e($student['nisn']) ?></dd>
                        <dt>Kelas</dt>
                        <dd><?= Security::e($student['class_name'] ?? '-') ?></dd>
                        <dt>Sekolah</dt>
                        <dd><?= Security::e($student['school_name']) ?></dd>
                    </dl>
                </article>

                <article class="result-score-card">
                    <span>Nilai Akhir</span>
                    <strong><?= Security::e($result['final_score'] ?? '-') ?></strong>
                    <?php if (!empty($result['finalized_at'])): ?>
                        <small>Difinalisasi pada <?= Security::e($result['finalized_at']) ?></small>
                    <?php else: ?>
                        <small>Belum difinalisasi</small>
                    <?php endif; ?>
                </article>
            </div>

            <?php if (!empty($result['notes'])): ?>
                <aside class="result-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><?= Security::e($result['notes']) ?></span>
                </aside>
            <?php endif; ?>

            <footer class="result-footer">
                <span>Dokumen ini ditampilkan melalui sistem ASIKSSD.</span>
                <strong><?= date('d/m/Y H:i') ?> WIB</strong>
            </footer>
        </section>
    </main>
</body>
</html>
