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
AuthMiddleware::requirePermission('graduation');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = Session::flash('success');
$adminLogo = 'assets/img/logo-asikssd.png';

$yearStmt = $pdo->prepare('SELECT * FROM academic_years WHERE school_id = :school_id AND is_active = 1 LIMIT 1');
$yearStmt->execute(['school_id' => $user['school_id']]);
$year = $yearStmt->fetch();

$ruleStmt = $pdo->prepare('SELECT * FROM graduation_rules WHERE school_id = :school_id AND is_active = 1 ORDER BY id DESC LIMIT 1');
$ruleStmt->execute(['school_id' => $user['school_id']]);
$rule = $ruleStmt->fetch();

$subjectsStmt = $pdo->prepare('SELECT * FROM subjects WHERE school_id = :school_id AND is_active = 1 ORDER BY name ASC');
$subjectsStmt->execute(['school_id' => $user['school_id']]);
$subjects = $subjectsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    $action = $_POST['action'] ?? '';
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if (!$rule || !$year) {
        $errors[] = 'Tahun pelajaran aktif atau aturan kelulusan belum tersedia.';
    }

    if (!$errors && $action === 'process') {
        $achievementScores = $_POST['achievement_scores'] ?? [];
        $extracurricularScores = $_POST['extracurricular_scores'] ?? [];

        if (!$subjects) {
            $errors[] = 'Mata pelajaran aktif belum tersedia.';
        }

        $automaticScores = [];
        if (!$errors) {
            $autoStmt = $pdo->prepare(
                "SELECT
                    grades.subject_id,
                    ROUND(AVG(CASE WHEN grades.semester IN ('S7','S8','S9','S10','S11') THEN grades.score END), 2) AS report_average,
                    MAX(CASE WHEN grades.semester = 'ASAJ' THEN grades.score END) AS assessment_score,
                    COUNT(CASE WHEN grades.semester IN ('S7','S8','S9','S10','S11') THEN 1 END) AS report_count
                 FROM grades
                 WHERE grades.school_id = :school_id
                    AND grades.academic_year_id = :year_id
                    AND grades.student_id = :student_id
                 GROUP BY grades.subject_id"
            );
            $autoStmt->execute([
                'school_id' => $user['school_id'],
                'year_id' => $year['id'],
                'student_id' => $studentId,
            ]);
            foreach ($autoStmt as $score) {
                $automaticScores[(int) $score['subject_id']] = $score;
            }
        }

        $subjectFinals = [];
        foreach ($subjects as $subject) {
            $subjectId = (int) $subject['id'];
            $autoScore = $automaticScores[$subjectId] ?? null;
            $reportScore = $autoScore['report_average'] ?? null;
            $assessmentScore = $autoScore['assessment_score'] ?? null;
            $achievementScore = trim((string) ($achievementScores[$subjectId] ?? '0'));
            $extracurricularScore = trim((string) ($extracurricularScores[$subjectId] ?? '0'));

            if ($reportScore === null || (int) ($autoScore['report_count'] ?? 0) < 5 || $assessmentScore === null) {
                $errors[] = 'Nilai rapor Semester 7-11 dan ASAJ untuk mapel ' . $subject['name'] . ' belum lengkap di Data Nilai.';
                break;
            }

            foreach ([$reportScore, $assessmentScore, $achievementScore, $extracurricularScore] as $score) {
                if ($score === '' || !is_numeric($score) || (float) $score < 0 || (float) $score > 100) {
                    $errors[] = 'Semua nilai kelulusan harus berupa angka 0 sampai 100.';
                    break 2;
                }
            }

            $subjectFinal = (((float) $reportScore * (float) $rule['report_weight']) + ((float) $assessmentScore * (float) $rule['assessment_weight'])) / 100;
            $subjectFinal += (float) $achievementScore + (float) $extracurricularScore;
            $subjectFinals[$subjectId] = [
                'report_average' => (float) $reportScore,
                'assessment_score' => (float) $assessmentScore,
                'achievement_score' => (float) $achievementScore,
                'extracurricular_score' => (float) $extracurricularScore,
                'final_score' => min(100, round($subjectFinal, 2)),
            ];
        }

        if (!$errors) {
                $reportAverage = round(array_sum(array_column($subjectFinals, 'report_average')) / count($subjectFinals), 2);
                $assessmentAverage = round(array_sum(array_column($subjectFinals, 'assessment_score')) / count($subjectFinals), 2);
                $finalScore = round(array_sum(array_column($subjectFinals, 'final_score')) / count($subjectFinals), 2);
                $status = $finalScore >= (float) $rule['minimum_score'] ? 'MEMENUHI SYARAT' : 'TIDAK MEMENUHI SYARAT';
                $pdo->beginTransaction();
                try {
                    $subjectStmt = $pdo->prepare(
                        'INSERT INTO graduation_subject_scores (school_id, student_id, subject_id, graduation_rule_id, report_average, assessment_score, achievement_score, extracurricular_score, final_score)
                         VALUES (:school_id, :student_id, :subject_id, :rule_id, :report_average, :assessment_score, :achievement_score, :extracurricular_score, :final_score)
                         ON DUPLICATE KEY UPDATE graduation_rule_id = VALUES(graduation_rule_id), report_average = VALUES(report_average),
                         assessment_score = VALUES(assessment_score), achievement_score = VALUES(achievement_score),
                         extracurricular_score = VALUES(extracurricular_score), final_score = VALUES(final_score)'
                    );
                    foreach ($subjectFinals as $subjectId => $score) {
                        $subjectStmt->execute([
                            'school_id' => $user['school_id'],
                            'student_id' => $studentId,
                            'subject_id' => $subjectId,
                            'rule_id' => $rule['id'],
                            'report_average' => $score['report_average'],
                            'assessment_score' => $score['assessment_score'],
                            'achievement_score' => $score['achievement_score'],
                            'extracurricular_score' => $score['extracurricular_score'],
                            'final_score' => $score['final_score'],
                        ]);
                    }
                $stmt = $pdo->prepare(
                    'INSERT INTO graduation_results (school_id, student_id, graduation_rule_id, report_average, assessment_score, final_score, status, notes)
                     VALUES (:school_id, :student_id, :rule_id, :report_average, :assessment_score, :final_score, :status, :notes)
                     ON DUPLICATE KEY UPDATE graduation_rule_id = VALUES(graduation_rule_id), report_average = VALUES(report_average),
                     assessment_score = VALUES(assessment_score), final_score = VALUES(final_score), status = VALUES(status), notes = VALUES(notes)'
                );
                $stmt->execute([
                    'school_id' => $user['school_id'],
                    'student_id' => $studentId,
                    'rule_id' => $rule['id'],
                    'report_average' => $reportAverage,
                    'assessment_score' => $assessmentAverage,
                    'final_score' => $finalScore,
                    'status' => $status,
                    'notes' => $notes,
                ]);
                AuditLogger::log('PROCESS', 'graduation_results', $studentId, [], ['status' => $status, 'final_score' => $finalScore, 'basis' => 'Nilai rapor semester 7-11 + ASAJ per mata pelajaran']);
                $pdo->commit();
                Session::flash('success', 'Data kelulusan berhasil diproses.');
                header('Location: graduation.php');
                exit;
                } catch (Throwable $exception) {
                    $pdo->rollBack();
                    $errors[] = 'Data kelulusan gagal diproses: ' . $exception->getMessage();
                }
            }
    }

    if (!$errors && in_array($action, ['verify_operator', 'verify_principal', 'finalize'], true)) {
        $resultStmt = $pdo->prepare('SELECT * FROM graduation_results WHERE school_id = :school_id AND student_id = :student_id');
        $resultStmt->execute(['school_id' => $user['school_id'], 'student_id' => $studentId]);
        $old = $resultStmt->fetch();
        if (!$old) {
            $errors[] = 'Data kelulusan belum diproses.';
        } elseif (!empty($old['finalized_at'])) {
            $errors[] = 'Data sudah difinalisasi dan tidak dapat diubah sembarangan.';
        } else {
            if ($action === 'verify_operator') {
                $pdo->prepare('UPDATE graduation_results SET verification_operator_at = NOW(), status = "PERLU VERIFIKASI" WHERE id = :id')->execute(['id' => $old['id']]);
            }
            if ($action === 'verify_principal') {
                $pdo->prepare('UPDATE graduation_results SET verification_principal_at = NOW() WHERE id = :id')->execute(['id' => $old['id']]);
            }
            if ($action === 'finalize') {
                $finalStatus = $old['status'] === 'MEMENUHI SYARAT' ? 'LULUS' : 'TIDAK LULUS';
                $pdo->prepare('UPDATE graduation_results SET finalized_at = NOW(), status = :status WHERE id = :id')->execute(['status' => $finalStatus, 'id' => $old['id']]);
            }
            AuditLogger::log(strtoupper($action), 'graduation_results', (int) $old['id'], $old, ['action' => $action]);
            Session::flash('success', 'Status kelulusan berhasil diperbarui.');
            header('Location: graduation.php');
            exit;
        }
    }
}

$studentsStmt = $pdo->prepare(
    'SELECT students.id, students.nisn, students.name, classes.name AS class_name,
        gr.report_average, gr.assessment_score, gr.final_score, gr.status, gr.notes, gr.verification_operator_at, gr.verification_principal_at, gr.finalized_at
     FROM students
     INNER JOIN classes ON classes.id = students.class_id AND classes.is_final_grade = 1
     LEFT JOIN graduation_results gr ON gr.student_id = students.id
     WHERE students.school_id = :school_id
     ORDER BY classes.name ASC, students.name ASC'
);
$studentsStmt->execute(['school_id' => $user['school_id']]);
$students = $studentsStmt->fetchAll();

$scoreDefaults = [];
if ($year) {
    $scoreStmt = $pdo->prepare(
        "SELECT
            grades.student_id,
            grades.subject_id,
            ROUND(AVG(CASE WHEN grades.semester IN ('S7','S8','S9','S10','S11') THEN grades.score END), 2) AS report_average,
            MAX(CASE WHEN grades.semester = 'ASAJ' THEN grades.score END) AS assessment_score
         FROM grades
         INNER JOIN students ON students.id = grades.student_id
         INNER JOIN classes ON classes.id = students.class_id AND classes.is_final_grade = 1
         WHERE grades.school_id = :school_id AND grades.academic_year_id = :year_id
         GROUP BY grades.student_id, grades.subject_id"
    );
    $scoreStmt->execute(['school_id' => $user['school_id'], 'year_id' => $year['id']]);
    foreach ($scoreStmt as $score) {
        $scoreDefaults[(int) $score['student_id']][(int) $score['subject_id']] = [
            'report_average' => $score['report_average'] !== null ? (float) $score['report_average'] : '',
            'assessment_score' => $score['assessment_score'] !== null ? (float) $score['assessment_score'] : '',
        ];
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelulusan - <?= Security::e($app['name']) ?></title>
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
            <a class="active" href="graduation.php"><i class="fa-solid fa-user-graduate"></i><span>Kelulusan</span></a>
            <a href="reports.php"><i class="fa-solid fa-file-lines"></i><span>Laporan</span></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i><span>Pengaturan</span></a>
        </nav>
        <div class="sidebar-credit">Contributor: By. Rojali</div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <button class="icon-button" id="sidebarToggle" type="button" aria-label="Ciutkan sidebar"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Pengolahan Kelulusan</h1><p>Pastikan kriteria kelulusan telah disesuaikan dengan kebijakan sekolah yang berlaku.</p></div>
            <div class="topbar-actions">
                <button class="icon-button" id="themeToggle" type="button" aria-label="Mode gelap"><i class="fa-solid fa-moon"></i></button>
                <button class="icon-button" id="fullscreenToggle" type="button" aria-label="Layar penuh"><i class="fa-solid fa-expand"></i></button>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
            </div>
        </header>

        <?php if ($success): ?><div class="toast-message success"><i class="fa-solid fa-circle-check"></i><?= Security::e($success) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger mt-3"><?php foreach ($errors as $error): ?><div><?= Security::e($error) ?></div><?php endforeach; ?></div><?php endif; ?>

        <section class="panel mt-3">
            <div class="panel-header">
                <div><h2>Konfigurasi Aktif</h2><p>Rumus nilai ijazah per mapel: nilai rapor semester 7-11 <?= Security::e($rule['report_weight'] ?? '-') ?>% + ASAJ <?= Security::e($rule['assessment_weight'] ?? '-') ?>%. Nilai minimum kelulusan <?= Security::e($rule['minimum_score'] ?? '-') ?>.</p></div>
                <span class="badge text-bg-primary"><?= Security::e($rule['name'] ?? 'Belum Ada Aturan') ?></span>
            </div>
        </section>

        <section class="panel mt-3">
            <div class="table-responsive">
                <table class="table align-middle admin-table">
                    <thead><tr><th>No</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>Rata-rata Rapor</th><th>Nilai Asesmen</th><th>Nilai Akhir</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= Security::e($student['nisn']) ?></td>
                                <td><?= Security::e($student['name']) ?></td>
                                <td><?= Security::e($student['class_name']) ?></td>
                                <td><?= Security::e($student['report_average'] ?? '-') ?></td>
                                <td><?= Security::e($student['assessment_score'] ?? '-') ?></td>
                                <td><?= Security::e($student['final_score'] ?? '-') ?></td>
                                <td><span class="badge graduation-badge"><?= Security::e($student['status'] ?? 'BELUM DIPROSES') ?></span></td>
                                <td><?= Security::e($student['notes'] ?? '-') ?></td>
                                <td class="table-actions">
                                    <button class="action-chip" data-bs-toggle="modal" data-bs-target="#processModal" data-student-id="<?= (int) $student['id'] ?>" data-name="<?= Security::e($student['name']) ?>" data-notes="<?= Security::e($student['notes']) ?>" data-scores='<?= Security::e(json_encode($scoreDefaults[(int) $student['id']] ?? [], JSON_UNESCAPED_UNICODE)) ?>' aria-label="Proses">
                                        <i class="fa-solid fa-calculator"></i><span>Proses</span>
                                    </button>
                                    <?php foreach ([
                                        'verify_operator' => ['fa-user-check', 'Verif Operator'],
                                        'verify_principal' => ['fa-stamp', 'Verif KS'],
                                        'finalize' => ['fa-lock', 'Final']
                                    ] as $action => [$icon, $label]): ?>
                                        <form method="post" class="d-inline confirm-form">
                                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                                            <input type="hidden" name="action" value="<?= $action ?>">
                                            <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
                                            <button class="action-chip" type="submit" aria-label="<?= Security::e($label) ?>">
                                                <i class="fa-solid <?= $icon ?>"></i><span><?= Security::e($label) ?></span>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                    <?php if (($student['status'] ?? '') === 'LULUS' && !empty($student['finalized_at'])): ?>
                                        <a class="action-chip" href="print_skl.php?student_id=<?= (int) $student['id'] ?>" target="_blank" aria-label="Cetak SKL">
                                            <i class="fa-solid fa-print"></i><span>SKL</span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$students): ?><tr><td colspan="10" class="empty-state">Belum ada siswa kelas akhir.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="modal fade" id="processModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Proses Kelulusan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <input type="hidden" name="action" value="process">
                <input type="hidden" name="student_id">
                <p class="text-muted" id="processStudentName"></p>
                <div class="alert alert-info">Nilai rapor semester 7 sampai 11 dan nilai ASAJ dihitung otomatis dari Data Nilai. Admin hanya dapat mengisi Prestasi dan Ekstrakurikuler jika digunakan oleh kebijakan sekolah.</div>
                <div class="table-responsive">
                    <table class="table table-sm admin-table">
                        <thead><tr><th>Mata Pelajaran</th><th>Nilai Rapor Semester 7-11</th><th>ASAJ</th><th>Prestasi</th><th>Ekstrakurikuler</th></tr></thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?= Security::e($subject['name']) ?></td>
                                    <td><input class="form-control" name="report_scores[<?= (int) $subject['id'] ?>]" type="number" min="0" max="100" step="0.01" readonly></td>
                                    <td><input class="form-control" name="assessment_scores[<?= (int) $subject['id'] ?>]" type="number" min="0" max="100" step="0.01" readonly></td>
                                    <td><input class="form-control" name="achievement_scores[<?= (int) $subject['id'] ?>]" type="number" min="0" max="100" step="0.01" value="0"></td>
                                    <td><input class="form-control" name="extracurricular_scores[<?= (int) $subject['id'] ?>]" type="number" min="0" max="100" step="0.01" value="0"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <label class="form-label mt-3">Keterangan</label>
                <textarea class="form-control" name="notes" rows="3"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Hitung & Simpan</button></div>
        </form></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
