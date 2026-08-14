<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';

use App\Core\Database;
use App\Core\Session;
use App\Utilities\Security;

Session::start();

$pdo = Database::connection();
$studentId = 0;
$schoolId = 0;
$isAdmin = !empty($_SESSION['user']);
$isStudent = !empty($_SESSION['student']);

if ($isAdmin) {
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $schoolId = (int) $_SESSION['user']['school_id'];
} elseif ($isStudent) {
    $studentId = (int) $_SESSION['student']['id'];
    $schoolId = (int) $_SESSION['student']['school_id'];
} else {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT students.id, students.nis, students.nisn, students.name AS student_name, students.birth_place, students.birth_date, students.parent_name,
        classes.name AS class_name, schools.name AS school_name, schools.npsn, schools.address, schools.village,
        schools.district, schools.city, schools.province, schools.principal_name, schools.principal_nip, schools.logo_path,
        academic_years.name AS academic_year, graduation_results.final_score, graduation_results.status, graduation_results.finalized_at
     FROM students
     INNER JOIN schools ON schools.id = students.school_id
     LEFT JOIN classes ON classes.id = students.class_id
     LEFT JOIN academic_years ON academic_years.school_id = students.school_id AND academic_years.is_active = 1
     LEFT JOIN graduation_results ON graduation_results.student_id = students.id
     WHERE students.id = :student_id AND students.school_id = :school_id
     LIMIT 1'
);
$stmt->execute(['student_id' => $studentId, 'school_id' => $schoolId]);
$row = $stmt->fetch();

$scoreStmt = $pdo->prepare(
    "SELECT subjects.name, graduation_subject_scores.final_score
     FROM graduation_subject_scores
     INNER JOIN subjects ON subjects.id = graduation_subject_scores.subject_id
     WHERE graduation_subject_scores.student_id = :student_id AND graduation_subject_scores.school_id = :school_id
     ORDER BY CASE
        WHEN subjects.name IN ('PAI', 'Pendidikan Agama Islam dan Budi Pekerti', 'Pend. Agama Islam & Budi Pekerti') THEN 1
        WHEN subjects.name = 'Pendidikan Pancasila' THEN 2
        WHEN subjects.name = 'Bahasa Indonesia' THEN 3
        WHEN subjects.name = 'Matematika' THEN 4
        WHEN subjects.name IN ('IPAS', 'Ilmu Pengetahuan Alam & Sosial', 'Ilmu Pengetahuan Alam dan Sosial') THEN 5
        WHEN subjects.name IN ('PJOK', 'Pend. Jasmani, Olahraga dan Kesehatan', 'Pendidikan Jasmani, Olahraga dan Kesehatan') THEN 6
        WHEN subjects.name = 'Seni' THEN 7
        WHEN subjects.name = 'Bahasa Inggris' THEN 8
        ELSE 99
     END, subjects.name ASC"
);
$scoreStmt->execute(['student_id' => $studentId, 'school_id' => $schoolId]);
$subjectScores = $scoreStmt->fetchAll();

$headerStmt = $pdo->prepare('SELECT setting_value FROM settings WHERE school_id = :school_id AND setting_key = "skl_header_path" LIMIT 1');
$headerStmt->execute(['school_id' => $schoolId]);
$sklHeaderPath = $headerStmt->fetchColumn() ?: '';

function indonesianDate(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

$isAvailable = $row && ($row['status'] ?? '') === 'LULUS' && !empty($row['finalized_at']);
$documentNumber = $row ? 'SKL/' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) . '/' . preg_replace('/\D+/', '', (string) ($row['npsn'] ?? '')) . '/' . date('Y') : '-';
$schoolLogo = ($row['logo_path'] ?? '') ?: 'assets/img/logo-asikssd.png';
$birthInfo = trim((string) ($row['birth_place'] ?? '')) . ', ' . indonesianDate($row['birth_date'] ?? null);
$location = ($row['city'] ?? '') ?: 'Jakarta';
$plenoDate = indonesianDate($row['finalized_at'] ?? null);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak SKL - ASIKSSD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="skl-body">
    <main class="skl-page">
        <div class="skl-actions no-print">
            <a class="btn btn-outline-secondary" href="<?= $isAdmin ? 'graduation.php' : 'student_home.php' ?>">Kembali</a>
            <?php if ($isAvailable): ?><button class="btn btn-primary" onclick="window.print()">Cetak SKL</button><?php endif; ?>
        </div>
        <?php if (!$isAvailable): ?>
            <section class="skl-paper"><div class="alert alert-warning mb-0">SKL belum tersedia. Dokumen hanya dapat dicetak jika status siswa sudah final LULUS.</div></section>
        <?php else: ?>
            <section class="skl-paper">
                <?php if ($sklHeaderPath): ?>
                    <header class="skl-uploaded-header"><img src="<?= Security::e($sklHeaderPath) ?>" alt="Kop SKL"></header>
                <?php else: ?>
                    <header class="skl-header">
                        <img src="<?= Security::e($schoolLogo) ?>" alt="Logo Sekolah">
                        <div>
                            <h1><?= Security::e(mb_strtoupper($row['school_name'], 'UTF-8')) ?></h1>
                            <p>NPSN <?= Security::e($row['npsn']) ?></p>
                            <p><?= Security::e($row['address'] ?? '-') ?></p>
                            <p><?= Security::e($row['village'] ?? '-') ?>, <?= Security::e($row['district'] ?? '-') ?>, <?= Security::e($row['city'] ?? '-') ?>, <?= Security::e($row['province'] ?? '-') ?></p>
                        </div>
                    </header>
                    <div class="skl-rule"></div>
                <?php endif; ?>
                <section class="skl-title">
                    <h2>SURAT KETERANGAN LULUS</h2>
                    <p>Nomor: <?= Security::e($documentNumber) ?></p>
                </section>
                <p class="skl-paragraph">Yang bertanda tangan di bawah ini, Kepala <?= Security::e($row['school_name']) ?>, Kabupaten/Kota <?= Security::e($row['city'] ?? '-') ?>, Provinsi <?= Security::e($row['province'] ?? '-') ?>, berdasarkan:</p>
                <ol class="skl-basis">
                    <li>Undang-Undang Nomor 20 Tahun 2003 tentang Sistem Pendidikan Nasional.</li>
                    <li>Peraturan Pemerintah Nomor 57 Tahun 2021 tentang Standar Nasional Pendidikan.</li>
                    <li>Permendikdasmen Nomor 10 Tahun 2025 tentang Standar Kompetensi Lulusan.</li>
                    <li>Rapat Pleno Dewan Guru tentang Kelulusan Siswa tanggal <?= Security::e($plenoDate) ?>.</li>
                </ol>
                <p class="skl-paragraph">Dengan ini menerangkan bahwa:</p>
                <table class="skl-identity">
                    <tr><th>Nama Lulusan</th><td><?= Security::e(mb_strtoupper($row['student_name'], 'UTF-8')) ?></td></tr>
                    <tr><th>Tempat, Tanggal Lahir</th><td><?= Security::e($birthInfo) ?></td></tr>
                    <tr><th>Nama Orang Tua/Wali</th><td><?= Security::e($row['parent_name'] ?: '-') ?></td></tr>
                    <tr><th>Nomor Induk Siswa</th><td><?= Security::e($row['nis']) ?></td></tr>
                    <tr><th>NISN</th><td><?= Security::e($row['nisn']) ?></td></tr>
                </table>

                <h3 class="skl-section-title">Mata Pelajaran / Nilai Ujian Sekolah</h3>
                <table class="skl-score-table">
                    <thead><tr><th>No</th><th>Mata Pelajaran</th><th>Nilai</th></tr></thead>
                    <tbody>
                        <?php foreach ($subjectScores as $index => $score): ?>
                            <?php
                                $displaySubject = [
                                    'PAI' => 'Pend. Agama Islam & Budi Pekerti',
                                    'Pendidikan Agama Islam dan Budi Pekerti' => 'Pend. Agama Islam & Budi Pekerti',
                                    'IPAS' => 'Ilmu Pengetahuan Alam & Sosial',
                                    'Ilmu Pengetahuan Alam dan Sosial' => 'Ilmu Pengetahuan Alam & Sosial',
                                    'PJOK' => 'Pend. Jasmani, Olahraga dan Kesehatan',
                                    'Pendidikan Jasmani, Olahraga dan Kesehatan' => 'Pend. Jasmani, Olahraga dan Kesehatan',
                                ][$score['name']] ?? $score['name'];
                            ?>
                            <tr><td><?= $index + 1 ?></td><td><?= Security::e($displaySubject) ?></td><td><?= Security::e($score['final_score']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$subjectScores): ?><tr><td colspan="3">Data nilai mata pelajaran belum tersedia.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <p class="skl-paragraph">Menyatakan bahwa siswa tersebut di atas:</p>
                <div class="skl-status"><?= Security::e($row['status']) ?></div>
                <p class="skl-paragraph">dari satuan pendidikan <?= Security::e($row['school_name']) ?> dengan nilai rata-rata <?= Security::e($row['final_score']) ?> pada Tahun Pelajaran <?= Security::e($row['academic_year'] ?? '-') ?>.</p>
                <p class="skl-paragraph">Surat keterangan ini berlaku sementara sampai dengan diterbitkannya Ijazah asli sebagai bukti sah kelulusan peserta didik.</p>
                <footer class="skl-signature">
                    <div></div>
                    <div>
                        <p><?= Security::e($location) ?>, <?= Security::e(indonesianDate(date('Y-m-d'))) ?></p>
                        <p>Kepala Sekolah,</p>
                        <div class="signature-space"></div>
                        <strong><?= Security::e($row['principal_name'] ?: '-') ?></strong>
                        <span>NIP. <?= Security::e($row['principal_nip'] ?: '-') ?></span>
                    </div>
                </footer>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
