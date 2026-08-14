<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';
require_once dirname(__DIR__) . '/app/Utilities/StudentPassword.php';
require_once dirname(__DIR__) . '/app/Services/AuditLogger.php';

use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Services\AuditLogger;
use App\Utilities\Security;
use App\Utilities\StudentPassword;

Session::start();
AuthMiddleware::requirePermission('students');

$app = require dirname(__DIR__) . '/config/app.php';
$user = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = Session::flash('success');
$adminLogo = 'assets/img/logo-asikssd.png';
$allowedSorts = ['nis', 'nisn', 'name', 'gender', 'birth_place', 'birth_date', 'parent_name', 'class_name', 'status'];
$requestedSort = $_GET['sort'] ?? '';
$sort = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'name';
$direction = ($_GET['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = trim((string) ($_GET['search'] ?? ''));
$genderFilter = trim((string) ($_GET['gender'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$finalClassesStmt = $pdo->prepare('SELECT id, name FROM classes WHERE school_id = :school_id AND is_final_grade = 1 ORDER BY name ASC');
$finalClassesStmt->execute(['school_id' => $user['school_id']]);
$classes = $finalClassesStmt->fetchAll();
$finalClassIds = array_map(fn($class) => (int) $class['id'], $classes);

if (($_GET['export'] ?? '') !== '') {
    $type = $_GET['export'] === 'xls' ? 'xls' : 'csv';
    $exportStmt = $pdo->prepare(
        'SELECT students.nis, students.nisn, students.name, students.gender, students.birth_place, students.birth_date, students.parent_name, classes.name AS class_name, students.status
         FROM students
         LEFT JOIN classes ON classes.id = students.class_id
         WHERE students.school_id = :school_id AND classes.is_final_grade = 1
         ORDER BY students.name ASC'
    );
    $exportStmt->execute(['school_id' => $user['school_id']]);
    $filename = 'data-siswa-asikssd-' . date('Ymd-His');

    if ($type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, ['NIS', 'NISN', 'NAMA', 'JENIS_KELAMIN', 'TEMPAT_LAHIR', 'TANGGAL_LAHIR', 'NAMA_ORANG_TUA', 'KELAS', 'STATUS']);
        foreach ($exportStmt as $row) {
            fputcsv($out, [$row['nis'], $row['nisn'], $row['name'], $row['gender'], $row['birth_place'], $row['birth_date'], $row['parent_name'], $row['class_name'], $row['status']]);
        }
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.xls\"");
    echo "<table border=\"1\"><tr><th>NIS</th><th>NISN</th><th>NAMA</th><th>JENIS_KELAMIN</th><th>TEMPAT_LAHIR</th><th>TANGGAL_LAHIR</th><th>NAMA_ORANG_TUA</th><th>KELAS</th><th>STATUS</th></tr>";
    foreach ($exportStmt as $row) {
        echo '<tr><td>' . Security::e($row['nis']) . '</td><td>' . Security::e($row['nisn']) . '</td><td>' . Security::e($row['name']) . '</td><td>' . Security::e($row['gender']) . '</td><td>' . Security::e($row['birth_place']) . '</td><td>' . Security::e($row['birth_date']) . '</td><td>' . Security::e($row['parent_name']) . '</td><td>' . Security::e($row['class_name']) . '</td><td>' . Security::e($row['status']) . '</td></tr>';
    }
    echo '</table>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    if ($action === 'delete' && !$errors) {
        $studentId = (int) ($_POST['id'] ?? 0);
        $oldStmt = $pdo->prepare('SELECT * FROM students WHERE id = :id AND school_id = :school_id');
        $oldStmt->execute(['id' => $studentId, 'school_id' => $user['school_id']]);
        $old = $oldStmt->fetch();

        if (!$old) {
            $errors[] = 'Data siswa tidak ditemukan.';
        } else {
            $pdo->prepare('DELETE FROM students WHERE id = :id AND school_id = :school_id')->execute(['id' => $studentId, 'school_id' => $user['school_id']]);
            AuditLogger::log('DELETE', 'students', $studentId, $old, []);
            Session::flash('success', 'Data siswa berhasil dihapus.');
            header('Location: students.php');
            exit;
        }
    }

    if (in_array($action, ['create', 'update'], true) && !$errors) {
        $payload = [
            'school_id' => $user['school_id'],
            'class_id' => ($_POST['class_id'] ?? '') !== '' ? (int) $_POST['class_id'] : ($finalClassIds[0] ?? null),
            'nis' => trim((string) ($_POST['nis'] ?? '')),
            'nisn' => trim((string) ($_POST['nisn'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'birth_place' => trim((string) ($_POST['birth_place'] ?? '')),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')) ?: null,
            'parent_name' => trim((string) ($_POST['parent_name'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'Aktif')),
        ];
        $studentPassword = (string) ($_POST['student_password'] ?? '');

        foreach (['nis' => 'NIS', 'nisn' => 'NISN', 'name' => 'Nama Siswa', 'gender' => 'Jenis Kelamin', 'class_id' => 'Kelas', 'birth_date' => 'Tanggal Lahir', 'status' => 'Status'] as $field => $label) {
            if ($payload[$field] === '' || $payload[$field] === null) {
                $errors[] = "{$label} wajib diisi.";
            }
        }

        if (!in_array($payload['gender'], ['L', 'P'], true)) {
            $errors[] = 'Jenis kelamin tidak valid.';
        }

        if (!in_array($payload['status'], ['Aktif', 'Mutasi', 'Lulus', 'Tidak Aktif'], true)) {
            $errors[] = 'Status siswa tidak valid.';
        }

        if (!$errors) {
            try {
                if ($action === 'create') {
                    $defaultPassword = StudentPassword::defaultFromBirthDate($payload['birth_date']);
                    if ($studentPassword === '' && $defaultPassword === null) {
                        throw new RuntimeException('Tanggal lahir tidak valid untuk membuat password default.');
                    }
                    $payload['password_hash'] = password_hash($studentPassword !== '' ? $studentPassword : $defaultPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO students (school_id, class_id, nis, nisn, name, gender, birth_place, birth_date, parent_name, password_hash, status)
                         VALUES (:school_id, :class_id, :nis, :nisn, :name, :gender, :birth_place, :birth_date, :parent_name, :password_hash, :status)'
                    );
                    $stmt->execute($payload);
                    $id = (int) $pdo->lastInsertId();
                    $auditPayload = $payload;
                    unset($auditPayload['password_hash']);
                    AuditLogger::log('CREATE', 'students', $id, [], $auditPayload);
                    Session::flash('success', 'Data siswa berhasil ditambahkan.');
                } else {
                    $id = (int) ($_POST['id'] ?? 0);
                    $oldStmt = $pdo->prepare('SELECT * FROM students WHERE id = :id AND school_id = :school_id');
                    $oldStmt->execute(['id' => $id, 'school_id' => $user['school_id']]);
                    $old = $oldStmt->fetch();
                    if (!$old) {
                        throw new RuntimeException('Data siswa tidak ditemukan.');
                    }

                    $payload['id'] = $id;
                    if ($studentPassword !== '') {
                        $payload['password_hash'] = password_hash($studentPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare(
                            'UPDATE students SET class_id = :class_id, nis = :nis, nisn = :nisn, name = :name, gender = :gender,
                             birth_place = :birth_place, birth_date = :birth_date, parent_name = :parent_name, password_hash = :password_hash, status = :status
                             WHERE id = :id AND school_id = :school_id'
                        );
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE students SET class_id = :class_id, nis = :nis, nisn = :nisn, name = :name, gender = :gender,
                             birth_place = :birth_place, birth_date = :birth_date, parent_name = :parent_name, status = :status
                             WHERE id = :id AND school_id = :school_id'
                        );
                    }
                    $stmt->execute($payload);
                    $auditPayload = $payload;
                    unset($old['password_hash'], $auditPayload['password_hash']);
                    AuditLogger::log('UPDATE', 'students', $id, $old, $auditPayload);
                    Session::flash('success', 'Data siswa berhasil diperbarui.');
                }
                header('Location: students.php');
                exit;
            } catch (Throwable $exception) {
                $errors[] = 'Data gagal disimpan: ' . $exception->getMessage();
            }
        }
    }

    if ($action === 'import' && !$errors) {
        $rows = json_decode((string) ($_POST['rows'] ?? '[]'), true);
        if (!is_array($rows) || !$rows) {
            $errors[] = 'Tidak ada data valid untuk diimport.';
        } else {
            $classMap = [];
            foreach ($classes as $class) {
                $classMap[strtoupper($class['name'])] = (int) $class['id'];
            }

            $inserted = 0;
            $pdo->beginTransaction();
            try {
                $importStmt = $pdo->prepare(
                    'INSERT INTO imports (school_id, user_id, type, filename, total_rows, valid_rows, error_rows, status)
                     VALUES (:school_id, :user_id, "SISWA", :filename, :total_rows, :valid_rows, 0, "IMPORTED")'
                );
                $importStmt->execute([
                    'school_id' => $user['school_id'],
                    'user_id' => $user['id'],
                    'filename' => trim((string) ($_POST['filename'] ?? 'import-siswa.xlsx')),
                    'total_rows' => count($rows),
                    'valid_rows' => count($rows),
                ]);
                $importId = (int) $pdo->lastInsertId();

                foreach ($rows as $row) {
                    $classId = $classMap[strtoupper(trim((string) ($row['KELAS'] ?? '')))] ?? ($finalClassIds[0] ?? null);
                    if (!$classId) {
                        continue;
                    }

                    $payload = [
                        'school_id' => $user['school_id'],
                        'class_id' => $classId,
                        'nis' => trim((string) ($row['NIS'] ?? '')),
                        'nisn' => trim((string) ($row['NISN'] ?? '')),
                        'name' => trim((string) ($row['NAMA'] ?? '')),
                        'gender' => strtoupper(trim((string) ($row['JENIS_KELAMIN'] ?? ''))),
                        'birth_place' => trim((string) ($row['TEMPAT_LAHIR'] ?? '')),
                        'birth_date' => trim((string) ($row['TANGGAL_LAHIR'] ?? '')) ?: null,
                        'parent_name' => trim((string) ($row['NAMA_ORANG_TUA'] ?? '')),
                        'status' => trim((string) ($row['STATUS'] ?? 'Aktif')),
                    ];
                    $defaultPassword = StudentPassword::defaultFromBirthDate($payload['birth_date']);
                    if ($defaultPassword === null) {
                        throw new RuntimeException('Tanggal lahir pada file import harus format yyyy-mm-dd.');
                    }
                    $payload['password_hash'] = password_hash($defaultPassword, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare(
                        'INSERT INTO students (school_id, class_id, nis, nisn, name, gender, birth_place, birth_date, parent_name, password_hash, status)
                         VALUES (:school_id, :class_id, :nis, :nisn, :name, :gender, :birth_place, :birth_date, :parent_name, :password_hash, :status)
                         ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), nis = VALUES(nis), name = VALUES(name), gender = VALUES(gender),
                         birth_place = VALUES(birth_place), birth_date = VALUES(birth_date), parent_name = VALUES(parent_name), status = VALUES(status)'
                    );
                    $stmt->execute($payload);
                    $inserted++;
                }

                AuditLogger::log('IMPORT', 'students', $importId, [], ['valid_rows' => $inserted]);
                $pdo->commit();
                Session::flash('success', "Import berhasil. {$inserted} data valid disimpan.");
                header('Location: students.php');
                exit;
            } catch (Throwable $exception) {
                $pdo->rollBack();
                $errors[] = 'Import gagal: ' . $exception->getMessage();
            }
        }
    }
}

$where = ['students.school_id = :school_id', 'classes.is_final_grade = 1'];
$params = ['school_id' => $user['school_id']];

if ($search !== '') {
    $where[] = '(students.nis LIKE :search OR students.nisn LIKE :search OR students.name LIKE :search)';
    $params['search'] = "%{$search}%";
}
if (in_array($genderFilter, ['L', 'P'], true)) {
    $where[] = 'students.gender = :gender';
    $params['gender'] = $genderFilter;
}
if (in_array($statusFilter, ['Aktif', 'Mutasi', 'Lulus', 'Tidak Aktif'], true)) {
    $where[] = 'students.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = implode(' AND ', $where);
$sortColumn = $sort === 'class_name' ? 'classes.name' : 'students.' . $sort;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students LEFT JOIN classes ON classes.id = students.class_id WHERE {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$listSql = "SELECT students.*, classes.name AS class_name
    FROM students
    LEFT JOIN classes ON classes.id = students.class_id
    WHERE {$whereSql}
    ORDER BY {$sortColumn} {$direction}
    LIMIT {$perPage} OFFSET {$offset}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$students = $listStmt->fetchAll();

function sortUrl(string $field): string
{
    $query = $_GET;
    $query['sort'] = $field;
    $query['direction'] = (($_GET['sort'] ?? '') === $field && ($_GET['direction'] ?? 'asc') === 'asc') ? 'desc' : 'asc';
    return 'students.php?' . http_build_query($query);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Siswa - <?= Security::e($app['name']) ?></title>
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
            <a class="active" href="students.php"><i class="fa-solid fa-users"></i><span>Data Siswa</span></a>
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
            <div><h1>Data Siswa</h1><p>Kelola biodata siswa untuk administrasi kelulusan.</p></div>
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
                <div><h2>Daftar Siswa Kelas VI</h2><p>Menampilkan siswa kelas akhir dengan 10 siswa per halaman.</p></div>
                <div class="toolbar-actions">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal" type="button"><i class="fa-solid fa-file-import me-2"></i>Import Excel</button>
                    <a class="btn btn-outline-success" href="students.php?export=xls"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</a>
                    <a class="btn btn-outline-secondary" href="students.php?export=csv"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal" data-mode="create"><i class="fa-solid fa-plus me-2"></i>Tambah Siswa</button>
                </div>
            </div>

            <form class="filter-bar" method="get">
                <input class="form-control" name="search" value="<?= Security::e($search) ?>" placeholder="Cari NIS, NISN, atau nama">
                <select class="form-select" name="gender">
                    <option value="">Semua JK</option>
                    <option value="L" <?= $genderFilter === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= $genderFilter === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <?php foreach (['Aktif', 'Mutasi', 'Lulus', 'Tidak Aktif'] as $status): ?><option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>

            <div class="table-responsive mt-3">
                <table class="table align-middle admin-table">
                    <thead><tr>
                        <th>No</th>
                        <th><a href="<?= Security::e(sortUrl('nis')) ?>">NIS</a></th>
                        <th><a href="<?= Security::e(sortUrl('nisn')) ?>">NISN</a></th>
                        <th><a href="<?= Security::e(sortUrl('name')) ?>">Nama Siswa</a></th>
                        <th><a href="<?= Security::e(sortUrl('gender')) ?>">JK</a></th>
                        <th><a href="<?= Security::e(sortUrl('birth_place')) ?>">Tempat Lahir</a></th>
                        <th><a href="<?= Security::e(sortUrl('birth_date')) ?>">Tanggal Lahir</a></th>
                        <th><a href="<?= Security::e(sortUrl('class_name')) ?>">Kelas</a></th>
                        <th><a href="<?= Security::e(sortUrl('status')) ?>">Status</a></th>
                        <th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td><?= Security::e($student['nis']) ?></td>
                                <td><?= Security::e($student['nisn']) ?></td>
                                <td><?= Security::e($student['name']) ?></td>
                                <td><?= Security::e($student['gender']) ?></td>
                                <td><?= Security::e($student['birth_place']) ?></td>
                                <td><?= Security::e($student['birth_date']) ?></td>
                                <td><?= Security::e($student['class_name'] ?? '-') ?></td>
                                <td><span class="badge text-bg-<?= $student['status'] === 'Aktif' ? 'success' : 'secondary' ?>"><?= Security::e($student['status']) ?></span></td>
                                <td class="table-actions">
                                    <button class="icon-button" data-bs-toggle="modal" data-bs-target="#detailModal" data-student='<?= Security::e(json_encode($student, JSON_UNESCAPED_UNICODE)) ?>' aria-label="Detail"><i class="fa-solid fa-eye"></i></button>
                                    <button class="icon-button" data-bs-toggle="modal" data-bs-target="#studentModal" data-mode="update" data-student='<?= Security::e(json_encode($student, JSON_UNESCAPED_UNICODE)) ?>' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                                    <form method="post" class="d-inline delete-form">
                                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                        <button class="icon-button danger" type="submit" aria-label="Hapus"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$students): ?><tr><td colspan="10" class="empty-state">Belum ada data siswa sesuai filter.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-row">
                <span>Menampilkan <?= count($students) ?> dari <?= $totalRows ?> data</span>
                <div class="btn-group">
                    <a class="btn btn-outline-secondary btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="students.php?<?= Security::e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Sebelumnya</a>
                    <a class="btn btn-outline-secondary btn-sm <?= $page >= $totalPages ? 'disabled' : '' ?>" href="students.php?<?= Security::e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Berikutnya</a>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Tambah Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="id">
                <div class="form-grid">
                    <div><label class="form-label">NIS</label><input class="form-control" name="nis" required></div>
                    <div><label class="form-label">NISN</label><input class="form-control" name="nisn" required></div>
                    <div class="form-span-2"><label class="form-label">Nama Siswa</label><input class="form-control" name="name" required></div>
                    <div><label class="form-label">Jenis Kelamin</label><select class="form-select" name="gender" required><option value="">Pilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                    <div><label class="form-label">Kelas</label><select class="form-select" name="class_id" required><option value="">Pilih Kelas</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>"><?= Security::e($class['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="form-label">Tempat Lahir</label><input class="form-control" name="birth_place"></div>
                    <div><label class="form-label">Tanggal Lahir</label><input class="form-control" name="birth_date" type="date"></div>
                    <div class="form-span-2"><label class="form-label">Nama Orang Tua/Wali</label><input class="form-control" name="parent_name"></div>
                    <div><label class="form-label">Password Siswa</label><input class="form-control" name="student_password" type="password" placeholder="Kosongkan untuk default/tidak diubah"></div>
                    <div><label class="form-label">Status</label><select class="form-select" name="status"><option>Aktif</option><option>Mutasi</option><option>Lulus</option><option>Tidak Aktif</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
        </form></div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Detail Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body" id="detailContent"></div>
        </div></div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="post" class="modal-content" id="importForm">
            <div class="modal-header"><h5 class="modal-title">Import Data Excel</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="rows" id="importRows">
                <input type="hidden" name="filename" id="importFilename">
                <div class="import-steps">
                    <button class="btn btn-outline-primary" id="downloadTemplate" type="button"><i class="fa-solid fa-download me-2"></i>Unduh Template Excel</button>
                    <input class="form-control" id="importFile" type="file" accept=".xlsx,.xls,.csv">
                </div>
                <div class="alert alert-info mt-3">Kolom wajib: NIS, NISN, NAMA, JENIS_KELAMIN, TEMPAT_LAHIR, TANGGAL_LAHIR, KELAS, STATUS.</div>
                <div class="validation-summary" id="validationSummary"></div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm admin-table" id="importPreview">
                        <thead></thead>
                        <tbody><tr><td class="empty-state">Pilih file Excel untuk melihat preview.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" id="saveImport" type="submit" disabled>Simpan Data Valid</button></div>
        </form></div>
    </div>

    <script>
        window.asikssdClasses = <?= json_encode(array_column($classes, 'name'), JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
