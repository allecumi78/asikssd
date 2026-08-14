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
AuthMiddleware::requirePermission('dashboard');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();

$schoolStmt = $pdo->prepare('SELECT * FROM schools WHERE id = :id');
$schoolStmt->execute(['id' => $user['school_id']]);
$school = $schoolStmt->fetch();
$adminLogo = 'assets/img/logo-asikssd.png';

$counts = [
    'students' => 0,
    'classes' => 0,
    'subjects' => 0,
    'audit_logs' => 0,
    'final_students' => 0,
    'students_with_grades' => 0,
    'eligible' => 0,
    'not_eligible' => 0,
];

$simpleCountTables = ['students', 'classes', 'subjects', 'audit_logs'];
foreach ($simpleCountTables as $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE school_id = :school_id");
    $stmt->execute(['school_id' => $user['school_id']]);
    $counts[$table] = (int) $stmt->fetchColumn();
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM students
     INNER JOIN classes ON classes.id = students.class_id
     WHERE students.school_id = :school_id AND classes.is_final_grade = 1'
);
$stmt->execute(['school_id' => $user['school_id']]);
$counts['final_students'] = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(DISTINCT student_id) FROM grades WHERE school_id = :school_id AND score IS NOT NULL');
$stmt->execute(['school_id' => $user['school_id']]);
$counts['students_with_grades'] = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM graduation_results WHERE school_id = :school_id AND status = 'LULUS'");
$stmt->execute(['school_id' => $user['school_id']]);
$counts['eligible'] = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM graduation_results WHERE school_id = :school_id AND status = 'TIDAK LULUS'");
$stmt->execute(['school_id' => $user['school_id']]);
$counts['not_eligible'] = (int) $stmt->fetchColumn();

$completionPercent = $counts['students'] > 0 ? round(($counts['students_with_grades'] / $counts['students']) * 100) : 0;
$classDistribution = $pdo->prepare(
    'SELECT classes.name, COUNT(students.id) AS total
     FROM classes
     LEFT JOIN students ON students.class_id = classes.id
     WHERE classes.school_id = :school_id
     GROUP BY classes.id, classes.name
     ORDER BY classes.level DESC, classes.name ASC'
);
$classDistribution->execute(['school_id' => $user['school_id']]);
$classRows = $classDistribution->fetchAll();

$logs = $pdo->prepare(
    'SELECT audit_logs.*, users.name AS user_name
     FROM audit_logs
     LEFT JOIN users ON users.id = audit_logs.user_id
     WHERE audit_logs.school_id = :school_id
     ORDER BY audit_logs.created_at DESC
     LIMIT 6'
);
$logs->execute(['school_id' => $user['school_id']]);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status"></div>
        <span>Memuat ASIKSSD...</span>
    </div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo-image"><img src="<?= Security::e($adminLogo) ?>" alt="Logo ASIKSSD"></div>
            <div>
                <strong>ASIKSSD</strong>
                <span>Kelulusan SD</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php
            $menus = [
                ['Dashboard', 'fa-gauge-high', 'active', 'dashboard.php'],
                ['Data Sekolah', 'fa-school', '', 'school.php'],
                ['Data Siswa', 'fa-users', '', 'students.php'],
                ['Data Nilai', 'fa-clipboard-list', '', 'grades.php'],
                ['Rekap Nilai', 'fa-chart-column', '', 'grade_recap.php'],
                ['Kelulusan', 'fa-user-graduate', '', 'graduation.php'],
                ['Laporan', 'fa-file-lines', '', 'reports.php'],
                ['Pengaturan', 'fa-gear', '', 'settings.php'],
            ];
            foreach ($menus as [$label, $icon, $state, $href]): ?>
                <a class="<?= $state ?>" href="<?= Security::e($href) ?>">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <span><?= Security::e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-credit">Contributor: By. Rojali</div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="icon-button" id="sidebarToggle" type="button" aria-label="Ciutkan sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h1>Dashboard</h1>
                <p><?= Security::e($school['name'] ?? '-') ?> · NPSN <?= Security::e($school['npsn'] ?? '-') ?></p>
            </div>
            <div class="topbar-actions">
                <button class="icon-button" id="themeToggle" type="button" aria-label="Mode gelap">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <button class="icon-button" id="fullscreenToggle" type="button" aria-label="Layar penuh">
                    <i class="fa-solid fa-expand"></i>
                </button>
                <div class="user-chip">
                    <span><?= Security::e($user['name']) ?></span>
                    <small><?= Security::e($user['role']) ?></small>
                </div>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="content-grid">
            <article class="stat-card">
                <span>Total Siswa</span>
                <strong><?= $counts['students'] ?></strong>
                <small>Seluruh data siswa aktif dan arsip</small>
            </article>
            <article class="stat-card">
                <span>Kelas Akhir</span>
                <strong><?= $counts['final_students'] ?></strong>
                <small>Siswa pada kelas yang ditandai akhir</small>
            </article>
            <article class="stat-card">
                <span>Data Lengkap</span>
                <strong><?= $counts['students_with_grades'] ?></strong>
                <small><?= $completionPercent ?>% kelengkapan nilai</small>
            </article>
            <article class="stat-card">
                <span>Lulus</span>
                <strong><?= $counts['eligible'] ?></strong>
                <small><?= $counts['not_eligible'] ?> belum memenuhi syarat</small>
            </article>
        </section>

        <section class="dashboard-grid">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Distribusi Siswa</h2>
                        <p>Jumlah siswa berdasarkan kelas.</p>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="classChart"></canvas>
                </div>
            </article>
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Kelengkapan Nilai</h2>
                        <p>Perbandingan data nilai tersedia.</p>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="gradeChart"></canvas>
                </div>
            </article>
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Status Kelulusan</h2>
                        <p>Ringkasan hasil proses kelulusan.</p>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="graduationChart"></canvas>
                </div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Aktivitas Terbaru</h2>
                    <p>Jejak aktivitas pengguna pada sistem.</p>
                </div>
                <span class="badge text-bg-success">Phase 1 Aktif</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aksi</th>
                            <th>Tabel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= Security::e($log['created_at']) ?></td>
                                <td><?= Security::e($log['user_name'] ?? '-') ?></td>
                                <td><span class="badge text-bg-primary"><?= Security::e($log['action']) ?></span></td>
                                <td><?= Security::e($log['table_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($logs->rowCount() === 0): ?>
                            <tr>
                                <td colspan="4" class="empty-state">Belum ada aktivitas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        window.asikssdDashboard = {
            classLabels: <?= json_encode(array_column($classRows, 'name'), JSON_UNESCAPED_UNICODE) ?>,
            classTotals: <?= json_encode(array_map('intval', array_column($classRows, 'total'))) ?>,
            gradeTotals: [<?= (int) $counts['students_with_grades'] ?>, <?= max(0, (int) $counts['students'] - (int) $counts['students_with_grades']) ?>],
            graduationTotals: [<?= (int) $counts['eligible'] ?>, <?= (int) $counts['not_eligible'] ?>, <?= max(0, (int) $counts['final_students'] - (int) $counts['eligible'] - (int) $counts['not_eligible']) ?>]
        };
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
