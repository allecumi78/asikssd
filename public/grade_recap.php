<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';

use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Utilities\Security;

Session::start();
AuthMiddleware::requirePermission('grade_recap');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();
$adminLogo = 'assets/img/logo-asikssd.png';

$yearsStmt = $pdo->prepare('SELECT * FROM academic_years WHERE school_id = :school_id ORDER BY is_active DESC, name DESC');
$yearsStmt->execute(['school_id' => $user['school_id']]);
$years = $yearsStmt->fetchAll();
$classesStmt = $pdo->prepare('SELECT * FROM classes WHERE school_id = :school_id AND is_final_grade = 1 ORDER BY level DESC, name ASC');
$classesStmt->execute(['school_id' => $user['school_id']]);
$classes = $classesStmt->fetchAll();
$gradePeriods = [
    'S7' => 'Semester 7',
    'S8' => 'Semester 8',
    'S9' => 'Semester 9',
    'S10' => 'Semester 10',
    'S11' => 'Semester 11',
    'ASAJ' => 'Nilai ASAJ',
];

$yearId = (int) ($_GET['academic_year_id'] ?? ($years[0]['id'] ?? 0));
$classId = trim((string) ($_GET['class_id'] ?? ''));
$requestedSemester = $_GET['semester'] ?? 'S7';
$semester = array_key_exists($requestedSemester, $gradePeriods) ? $requestedSemester : 'S7';
$status = trim((string) ($_GET['status'] ?? ''));
$export = $_GET['export'] ?? '';

$subjectCountStmt = $pdo->prepare('SELECT COUNT(*) FROM subjects WHERE school_id = :school_id AND is_active = 1');
$subjectCountStmt->execute(['school_id' => $user['school_id']]);
$subjectCount = max(1, (int) $subjectCountStmt->fetchColumn());

$where = ['students.school_id = :school_id'];
$params = ['school_id' => $user['school_id'], 'year_id' => $yearId, 'semester' => $semester];

if ($classId !== '') {
    $where[] = 'students.class_id = :class_id';
    $params['class_id'] = (int) $classId;
}

$whereSql = implode(' AND ', $where);
$sql = "SELECT
        students.id,
        students.nisn,
        students.name,
        classes.name AS class_name,
        COUNT(grades.id) AS available_scores,
        ROUND(AVG(grades.score), 2) AS average_score,
        MAX(grades.score) AS highest_score,
        MIN(grades.score) AS lowest_score
    FROM students
    LEFT JOIN classes ON classes.id = students.class_id
    LEFT JOIN grades ON grades.student_id = students.id
        AND grades.academic_year_id = :year_id
        AND grades.semester = :semester
    WHERE {$whereSql}
    GROUP BY students.id, students.nisn, students.name, classes.name
    ORDER BY classes.name ASC, students.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$rows = array_values(array_filter($rows, function (array $row) use ($status, $subjectCount): bool {
    $complete = (int) $row['available_scores'] >= $subjectCount;
    if ($status === 'Lengkap') {
        return $complete;
    }
    if ($status === 'Belum Lengkap') {
        return !$complete;
    }
    return true;
}));

if (in_array($export, ['csv', 'xls'], true)) {
    $filename = 'rekap-nilai-asikssd-' . date('Ymd-His');
    $headers = ['NISN', 'Nama', 'Kelas', 'Jumlah Nilai Tersedia', 'Rata-rata', 'Nilai Tertinggi', 'Nilai Terendah', 'Status Kelengkapan'];

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            $complete = (int) $row['available_scores'] >= $subjectCount ? 'Lengkap' : 'Belum Lengkap';
            fputcsv($out, [$row['nisn'], $row['name'], $row['class_name'], $row['available_scores'], $row['average_score'], $row['highest_score'], $row['lowest_score'], $complete]);
        }
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.xls\"");
    echo '<table border="1"><tr>';
    foreach ($headers as $header) {
        echo '<th>' . Security::e($header) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $row) {
        $complete = (int) $row['available_scores'] >= $subjectCount ? 'Lengkap' : 'Belum Lengkap';
        echo '<tr><td>' . Security::e($row['nisn']) . '</td><td>' . Security::e($row['name']) . '</td><td>' . Security::e($row['class_name']) . '</td><td>' . (int) $row['available_scores'] . '</td><td>' . Security::e($row['average_score']) . '</td><td>' . Security::e($row['highest_score']) . '</td><td>' . Security::e($row['lowest_score']) . '</td><td>' . Security::e($complete) . '</td></tr>';
    }
    echo '</table>';
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Nilai - <?= Security::e($app['name']) ?></title>
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
            <a class="active" href="grade_recap.php"><i class="fa-solid fa-chart-column"></i><span>Rekap Nilai</span></a>
            <a href="graduation.php"><i class="fa-solid fa-user-graduate"></i><span>Kelulusan</span></a>
            <a href="reports.php"><i class="fa-solid fa-file-lines"></i><span>Laporan</span></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i><span>Pengaturan</span></a>
        </nav>
        <div class="sidebar-credit">Contributor: By. Rojali</div>
    </aside>
    <main class="main-content">
        <header class="topbar no-print">
            <button class="icon-button" id="sidebarToggle" type="button" aria-label="Ciutkan sidebar"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Rekap Nilai</h1><p>Ringkasan kelengkapan dan statistik nilai siswa.</p></div>
            <div class="topbar-actions">
                <button class="icon-button" id="themeToggle" type="button" aria-label="Mode gelap"><i class="fa-solid fa-moon"></i></button>
                <button class="icon-button" id="fullscreenToggle" type="button" aria-label="Layar penuh"><i class="fa-solid fa-expand"></i></button>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="panel mt-3 no-print">
            <form class="filter-bar" method="get">
                <select class="form-select" name="academic_year_id"><?php foreach ($years as $year): ?><option value="<?= (int) $year['id'] ?>" <?= $yearId === (int) $year['id'] ? 'selected' : '' ?>><?= Security::e($year['name']) ?></option><?php endforeach; ?></select>
                <select class="form-select" name="class_id"><option value="">Semua Kelas</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= $classId == $class['id'] ? 'selected' : '' ?>><?= Security::e($class['name']) ?></option><?php endforeach; ?></select>
                <select class="form-select" name="semester"><?php foreach ($gradePeriods as $value => $label): ?><option value="<?= Security::e($value) ?>" <?= $semester === $value ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?></select>
                <select class="form-select" name="status"><option value="">Semua Status</option><option <?= $status === 'Lengkap' ? 'selected' : '' ?>>Lengkap</option><option <?= $status === 'Belum Lengkap' ? 'selected' : '' ?>>Belum Lengkap</option></select>
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-filter"></i></button>
            </form>
        </section>

        <section class="panel mt-3 printable-area">
            <div class="panel-header">
                <div><h2>Daftar Rekap Nilai</h2><p>Total mata pelajaran aktif: <?= $subjectCount ?>.</p></div>
                <div class="toolbar-actions no-print">
                    <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Cetak</button>
                    <button class="btn btn-outline-danger" type="button" onclick="window.print()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
                    <a class="btn btn-outline-success" href="grade_recap.php?<?= Security::e(http_build_query(array_merge($_GET, ['export' => 'xls']))) ?>"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</a>
                    <a class="btn btn-outline-secondary" href="grade_recap.php?<?= Security::e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle admin-table">
                    <thead><tr><th>No</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>Jumlah Nilai Tersedia</th><th>Rata-rata</th><th>Nilai Tertinggi</th><th>Nilai Terendah</th><th>Status Kelengkapan</th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $index => $row): $complete = (int) $row['available_scores'] >= $subjectCount; ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= Security::e($row['nisn']) ?></td>
                                <td><?= Security::e($row['name']) ?></td>
                                <td><?= Security::e($row['class_name'] ?? '-') ?></td>
                                <td><?= (int) $row['available_scores'] ?></td>
                                <td><?= Security::e($row['average_score'] ?? '-') ?></td>
                                <td><?= Security::e($row['highest_score'] ?? '-') ?></td>
                                <td><?= Security::e($row['lowest_score'] ?? '-') ?></td>
                                <td><span class="badge text-bg-<?= $complete ? 'success' : 'warning' ?>"><?= $complete ? 'Lengkap' : 'Belum Lengkap' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="9" class="empty-state">Belum ada data rekap sesuai filter.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
