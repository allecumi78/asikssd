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
AuthMiddleware::requirePermission('reports');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();
$type = $_GET['type'] ?? 'students';
$export = $_GET['export'] ?? '';
$adminLogo = 'assets/img/logo-asikssd.png';

$reportTitles = [
    'students' => 'Daftar Siswa',
    'grades' => 'Rekap Nilai',
    'grade_completeness' => 'Rekap Kelengkapan Nilai',
    'graduation' => 'Rekap Kelulusan',
    'passed' => 'Daftar Siswa Lulus',
    'failed' => 'Daftar Siswa Tidak Lulus',
    'minutes' => 'Berita Acara/Rekap Administrasi',
];
$title = $reportTitles[$type] ?? $reportTitles['students'];

function fetchReportRows(PDO $pdo, int $schoolId, string $type): array
{
    if ($type === 'students') {
        $stmt = $pdo->prepare('SELECT students.nis, students.nisn, students.name, students.gender, classes.name AS class_name, students.status FROM students LEFT JOIN classes ON classes.id = students.class_id WHERE students.school_id = :school_id ORDER BY classes.name, students.name');
    } elseif ($type === 'grades' || $type === 'grade_completeness') {
        $stmt = $pdo->prepare('SELECT students.nisn, students.name, classes.name AS class_name, COUNT(grades.id) AS jumlah_nilai, ROUND(AVG(grades.score), 2) AS rata_rata FROM students LEFT JOIN classes ON classes.id = students.class_id LEFT JOIN grades ON grades.student_id = students.id WHERE students.school_id = :school_id GROUP BY students.id, students.nisn, students.name, classes.name ORDER BY classes.name, students.name');
    } elseif ($type === 'passed') {
        $stmt = $pdo->prepare("SELECT students.nisn, students.name, classes.name AS class_name, graduation_results.final_score, graduation_results.status FROM graduation_results INNER JOIN students ON students.id = graduation_results.student_id LEFT JOIN classes ON classes.id = students.class_id WHERE graduation_results.school_id = :school_id AND graduation_results.status = 'LULUS' ORDER BY classes.name, students.name");
    } elseif ($type === 'failed') {
        $stmt = $pdo->prepare("SELECT students.nisn, students.name, classes.name AS class_name, graduation_results.final_score, graduation_results.status FROM graduation_results INNER JOIN students ON students.id = graduation_results.student_id LEFT JOIN classes ON classes.id = students.class_id WHERE graduation_results.school_id = :school_id AND graduation_results.status = 'TIDAK LULUS' ORDER BY classes.name, students.name");
    } else {
        $stmt = $pdo->prepare('SELECT students.nisn, students.name, classes.name AS class_name, graduation_results.report_average, graduation_results.assessment_score, graduation_results.final_score, graduation_results.status FROM students LEFT JOIN classes ON classes.id = students.class_id LEFT JOIN graduation_results ON graduation_results.student_id = students.id WHERE students.school_id = :school_id ORDER BY classes.name, students.name');
    }
    $stmt->execute(['school_id' => $schoolId]);
    return $stmt->fetchAll();
}

$rows = fetchReportRows($pdo, (int) $user['school_id'], $type);
$columns = $rows ? array_keys($rows[0]) : ['Informasi'];

if (in_array($export, ['csv', 'xls'], true)) {
    $filename = 'laporan-asikssd-' . $type . '-' . date('Ymd-His');
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, $columns);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.xls\"");
    echo '<table border="1"><tr>';
    foreach ($columns as $column) {
        echo '<th>' . Security::e(str_replace('_', ' ', $column)) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<td>' . Security::e($row[$column] ?? '') . '</td>';
        }
        echo '</tr>';
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
    <title>Laporan - <?= Security::e($app['name']) ?></title>
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
            <a class="active" href="reports.php"><i class="fa-solid fa-file-lines"></i><span>Laporan</span></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i><span>Pengaturan</span></a>
        </nav>
        <div class="sidebar-credit">Contributor: By. Rojali</div>
    </aside>
    <main class="main-content">
        <header class="topbar no-print">
            <button class="icon-button" id="sidebarToggle" type="button" aria-label="Ciutkan sidebar"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Laporan</h1><p>Preview, cetak, dan export laporan administrasi sekolah.</p></div>
            <div class="topbar-actions">
                <button class="icon-button" id="themeToggle" type="button" aria-label="Mode gelap"><i class="fa-solid fa-moon"></i></button>
                <button class="icon-button" id="fullscreenToggle" type="button" aria-label="Layar penuh"><i class="fa-solid fa-expand"></i></button>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
            </div>
        </header>
        <section class="panel mt-3 no-print">
            <form class="filter-bar" method="get">
                <select class="form-select" name="type">
                    <?php foreach ($reportTitles as $key => $label): ?><option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-eye me-2"></i>Preview</button>
            </form>
        </section>
        <section class="panel mt-3 printable-area">
            <div class="panel-header">
                <div><h2><?= Security::e($title) ?></h2><p><?= count($rows) ?> baris data.</p></div>
                <div class="toolbar-actions no-print">
                    <button class="btn btn-outline-primary" onclick="window.print()" type="button"><i class="fa-solid fa-print me-2"></i>Cetak</button>
                    <button class="btn btn-outline-danger" onclick="window.print()" type="button"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
                    <a class="btn btn-outline-success" href="reports.php?<?= Security::e(http_build_query(['type' => $type, 'export' => 'xls'])) ?>"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</a>
                    <a class="btn btn-outline-secondary" href="reports.php?<?= Security::e(http_build_query(['type' => $type, 'export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle admin-table">
                    <thead><tr><th>No</th><?php foreach ($columns as $column): ?><th><?= Security::e(ucwords(str_replace('_', ' ', $column))) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $index => $row): ?><tr><td><?= $index + 1 ?></td><?php foreach ($columns as $column): ?><td><?= Security::e($row[$column] ?? '') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="<?= count($columns) + 1 ?>" class="empty-state">Belum ada data untuk laporan ini.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
