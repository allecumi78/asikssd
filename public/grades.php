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
AuthMiddleware::requirePermission('grades');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = Session::flash('success');
$adminLogo = 'assets/img/logo-asikssd.png';

$years = $pdo->prepare('SELECT * FROM academic_years WHERE school_id = :school_id ORDER BY is_active DESC, name DESC');
$years->execute(['school_id' => $user['school_id']]);
$years = $years->fetchAll();
$classes = $pdo->prepare('SELECT * FROM classes WHERE school_id = :school_id AND is_final_grade = 1 ORDER BY level DESC, name ASC');
$classes->execute(['school_id' => $user['school_id']]);
$classes = $classes->fetchAll();
$subjects = $pdo->prepare('SELECT * FROM subjects WHERE school_id = :school_id AND is_active = 1 ORDER BY name ASC');
$subjects->execute(['school_id' => $user['school_id']]);
$subjects = $subjects->fetchAll();
$gradePeriods = [
    'S7' => 'Semester 7',
    'S8' => 'Semester 8',
    'S9' => 'Semester 9',
    'S10' => 'Semester 10',
    'S11' => 'Semester 11',
    'ASAJ' => 'Nilai ASAJ',
];

$activeYearId = (int) ($years[0]['id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$yearId = (int) ($_GET['academic_year_id'] ?? $activeYearId);
$requestedSemester = $_GET['semester'] ?? 'S7';
$semester = array_key_exists($requestedSemester, $gradePeriods) ? $requestedSemester : 'S7';
$subjectId = (int) ($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0));
$minScore = 0;
$maxScore = 100;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    $yearId = (int) ($_POST['academic_year_id'] ?? 0);
    $classId = (int) ($_POST['class_id'] ?? 0);
    $postedSemester = $_POST['semester'] ?? 'S7';
    $semester = array_key_exists($postedSemester, $gradePeriods) ? $postedSemester : 'S7';
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $scores = $_POST['scores'] ?? [];
    $action = $_POST['action'] ?? 'save';

    if (!$yearId || !$classId || !$subjectId) {
        $errors[] = 'Tahun pelajaran, kelas, dan mata pelajaran wajib dipilih.';
    }

    if ($action === 'import_grades' && !$errors) {
        $rows = json_decode((string) ($_POST['rows'] ?? '[]'), true);
        if (!is_array($rows) || !$rows) {
            $errors[] = 'Tidak ada data nilai valid untuk diimport.';
        } else {
            $nisnRows = [];
            foreach ($rows as $row) {
                $nisn = trim((string) ($row['NISN'] ?? ''));
                if ($nisn !== '') {
                    $nisnRows[$nisn] = $row;
                }
            }

            if (!$nisnRows) {
                $errors[] = 'File import tidak memiliki NISN valid.';
            } else {
                $placeholders = implode(',', array_fill(0, count($nisnRows), '?'));
                $studentLookup = $pdo->prepare("SELECT id, nisn, name FROM students WHERE school_id = ? AND nisn IN ({$placeholders})");
                $studentLookup->execute(array_merge([$user['school_id']], array_keys($nisnRows)));
                $studentMap = [];
                foreach ($studentLookup as $studentRow) {
                    $studentMap[$studentRow['nisn']] = $studentRow;
                }

                foreach ($nisnRows as $nisn => $row) {
                    if (!isset($studentMap[$nisn])) {
                        $errors[] = "Siswa dengan NISN {$nisn} tidak ditemukan.";
                        continue;
                    }
                    $nameInFile = strtoupper(trim((string) ($row['NAMA'] ?? '')));
                    if ($nameInFile !== '' && $nameInFile !== strtoupper($studentMap[$nisn]['name'])) {
                        $errors[] = "Nama siswa untuk NISN {$nisn} tidak sesuai dengan database.";
                    }
                    $score = trim((string) ($row['NILAI'] ?? ''));
                    if ($score === '' || !is_numeric($score) || (float) $score < $minScore || (float) $score > $maxScore) {
                        $errors[] = "Nilai untuk NISN {$nisn} harus angka {$minScore}-{$maxScore}.";
                    }
                }

                if (!$errors) {
                    $scores = [];
                    foreach ($nisnRows as $nisn => $row) {
                        $scores[(int) $studentMap[$nisn]['id']] = (float) $row['NILAI'];
                    }
                }
            }
        }
    }

    foreach ($scores as $studentId => $score) {
        $score = trim((string) $score);
        if ($score === '') {
            $errors[] = 'Nilai kosong terdeteksi. Lengkapi nilai atau isi 0 sesuai kebijakan sekolah.';
            break;
        }
        if (!is_numeric($score) || (float) $score < $minScore || (float) $score > $maxScore) {
            $errors[] = "Nilai harus berupa angka antara {$minScore} sampai {$maxScore}.";
            break;
        }
    }

    if (!$errors) {
        $studentIds = array_map('intval', array_keys($scores));
        if ($studentIds) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $ownershipStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND id IN ({$placeholders})");
            $ownershipStmt->execute(array_merge([$user['school_id']], $studentIds));
            if ((int) $ownershipStmt->fetchColumn() !== count($studentIds)) {
                $errors[] = 'Terdapat data siswa yang tidak valid untuk sekolah ini.';
            }
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO grades (school_id, academic_year_id, student_id, subject_id, semester, score, created_by)
                 VALUES (:school_id, :academic_year_id, :student_id, :subject_id, :semester, :score, :created_by)
                 ON DUPLICATE KEY UPDATE score = VALUES(score), created_by = VALUES(created_by)'
            );
            foreach ($scores as $studentId => $score) {
                $stmt->execute([
                    'school_id' => $user['school_id'],
                    'academic_year_id' => $yearId,
                    'student_id' => (int) $studentId,
                    'subject_id' => $subjectId,
                    'semester' => $semester,
                    'score' => (float) $score,
                    'created_by' => $user['id'],
                ]);
            }
            AuditLogger::log($action === 'import_grades' ? 'IMPORT' : 'UPSERT', 'grades', null, [], ['rows' => count($scores), 'subject_id' => $subjectId]);
            $pdo->commit();
            Session::flash('success', $action === 'import_grades' ? 'Import nilai berhasil disimpan.' : 'Data nilai berhasil disimpan.');
            header('Location: grades.php?' . http_build_query(['academic_year_id' => $yearId, 'class_id' => $classId, 'semester' => $semester, 'subject_id' => $subjectId]));
            exit;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = 'Nilai gagal disimpan: ' . $exception->getMessage();
        }
    }
}

$studentsStmt = $pdo->prepare(
    'SELECT students.*, grades.score
     FROM students
     LEFT JOIN grades ON grades.student_id = students.id
        AND grades.academic_year_id = :year_id
        AND grades.subject_id = :subject_id
        AND grades.semester = :semester
     WHERE students.school_id = :school_id AND students.class_id = :class_id
     ORDER BY students.name ASC'
);
$studentsStmt->execute([
    'year_id' => $yearId,
    'subject_id' => $subjectId,
    'semester' => $semester,
    'school_id' => $user['school_id'],
    'class_id' => $classId,
]);
$students = $studentsStmt->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Nilai - <?= Security::e($app['name']) ?></title>
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
            <a class="active" href="grades.php"><i class="fa-solid fa-clipboard-list"></i><span>Data Nilai</span></a>
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
            <div><h1>Data Nilai</h1><p>Input nilai rapor semester 7 sampai 11 dan nilai ASAJ.</p></div>
            <div class="topbar-actions">
                <button class="icon-button" id="themeToggle" type="button" aria-label="Mode gelap"><i class="fa-solid fa-moon"></i></button>
                <button class="icon-button" id="fullscreenToggle" type="button" aria-label="Layar penuh"><i class="fa-solid fa-expand"></i></button>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
            </div>
        </header>

        <?php if ($success): ?><div class="toast-message success"><i class="fa-solid fa-circle-check"></i><?= Security::e($success) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger mt-3"><?php foreach ($errors as $error): ?><div><?= Security::e($error) ?></div><?php endforeach; ?></div><?php endif; ?>

        <section class="panel mt-3">
            <form class="filter-bar" method="get">
                <select class="form-select" name="academic_year_id"><?php foreach ($years as $year): ?><option value="<?= (int) $year['id'] ?>" <?= $yearId === (int) $year['id'] ? 'selected' : '' ?>><?= Security::e($year['name']) ?></option><?php endforeach; ?></select>
                <select class="form-select" name="class_id"><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= $classId === (int) $class['id'] ? 'selected' : '' ?>><?= Security::e($class['name']) ?></option><?php endforeach; ?></select>
                <select class="form-select" name="semester"><?php foreach ($gradePeriods as $value => $label): ?><option value="<?= Security::e($value) ?>" <?= $semester === $value ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?></select>
                <select class="form-select" name="subject_id"><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>><?= Security::e($subject['name']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-filter"></i></button>
            </form>
        </section>

        <section class="panel mt-3">
            <div class="panel-header">
                <div><h2>Input Nilai</h2><p>Rentang nilai valid saat ini: <?= $minScore ?> sampai <?= $maxScore ?>.</p></div>
                <div class="toolbar-actions">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#gradeImportModal"><i class="fa-solid fa-file-import me-2"></i>Import Excel</button>
                    <span class="badge text-bg-warning align-self-center">Data kosong akan ditolak</span>
                </div>
            </div>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <input type="hidden" name="semester" value="<?= Security::e($semester) ?>">
                <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                <div class="table-responsive">
                    <table class="table align-middle admin-table">
                        <thead><tr><th>No</th><th>NISN</th><th>Nama Siswa</th><th>Nilai</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= Security::e($student['nisn']) ?></td>
                                    <td><?= Security::e($student['name']) ?></td>
                                    <td><input class="form-control score-input" name="scores[<?= (int) $student['id'] ?>]" type="number" min="<?= $minScore ?>" max="<?= $maxScore ?>" step="0.01" value="<?= Security::e($student['score']) ?>" required></td>
                                    <td><span class="badge text-bg-<?= $student['score'] === null ? 'warning' : 'success' ?>"><?= $student['score'] === null ? 'Belum Lengkap' : 'Lengkap' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$students): ?><tr><td colspan="5" class="empty-state">Belum ada siswa pada kelas ini.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Simpan Nilai</button></div>
            </form>
        </section>
    </main>
    <div class="modal fade" id="gradeImportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" method="post" id="gradeImportForm">
                <div class="modal-header"><h5 class="modal-title">Import Nilai Excel</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <input type="hidden" name="action" value="import_grades">
                    <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    <input type="hidden" name="semester" value="<?= Security::e($semester) ?>">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                    <input type="hidden" name="rows" id="gradeImportRows">
                    <div class="import-steps">
                        <button class="btn btn-outline-primary" id="downloadGradeTemplate" type="button"><i class="fa-solid fa-download me-2"></i>Download Template</button>
                        <input class="form-control" id="gradeImportFile" type="file" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="alert alert-info mt-3">Kolom wajib: NISN, NAMA, NILAI. Sistem akan mengecek NISN, nama, nilai, duplikasi, dan siswa yang tidak ditemukan.</div>
                    <div id="gradeValidationSummary"></div>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm admin-table" id="gradeImportPreview"><thead></thead><tbody><tr><td class="empty-state">Pilih file Excel untuk melihat preview.</td></tr></tbody></table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" id="saveGradeImport" type="submit" disabled>Simpan Data</button></div>
            </form>
        </div>
    </div>
    <script>
        window.asikssdGradeStudents = <?= json_encode(array_map(fn($student) => ['nisn' => $student['nisn'], 'name' => $student['name']], $students), JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
