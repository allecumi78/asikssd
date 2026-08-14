<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Utilities/Security.php';
require_once dirname(__DIR__) . '/app/Services/AuditLogger.php';
require_once dirname(__DIR__) . '/app/Services/AuthService.php';

use App\Core\Session;
use App\Core\Database;
use App\Services\AuthService;
use App\Utilities\Security;

Session::start();
$app = require dirname(__DIR__) . '/config/app.php';
$auth = new AuthService();
$error = null;
$adminLogo = 'assets/img/logo-asikssd.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $npsn = trim($_POST['npsn'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);
    $rateKey = 'admin_login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'local');
    $attempt = $_SESSION[$rateKey] ?? ['count' => 0, 'until' => 0];

    if (($attempt['until'] ?? 0) > time()) {
        $error = 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.';
    } elseif (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($npsn === '' || $password === '') {
        $error = 'NPSN dan password wajib diisi.';
    } elseif ($auth->attempt($npsn, $password, $remember)) {
        unset($_SESSION[$rateKey]);
        header('Location: dashboard.php');
        exit;
    } else {
        $attempt['count'] = (int) ($attempt['count'] ?? 0) + 1;
        if ($attempt['count'] >= 5) {
            $attempt = ['count' => 0, 'until' => time() + 300];
        }
        $_SESSION[$rateKey] = $attempt;
        $error = 'NPSN atau password tidak sesuai.';
    }
}

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="admin-login-brand">
                <div class="admin-login-logo"><img src="<?= Security::e($adminLogo) ?>" alt="Logo ASIKSSD"></div>
                <h1>Login Admin</h1>
                <p><?= Security::e($app['subtitle']) ?></p>
            </div>
            <?php if ($error): ?><div class="alert alert-danger"><?= Security::e($error) ?></div><?php endif; ?>
            <form method="post" class="auth-form" novalidate>
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <label class="form-label" for="npsn">NPSN</label>
                <input class="form-control mb-3" id="npsn" name="npsn" maxlength="20" required autofocus>
                <label class="form-label" for="password">Password</label>
                <input class="form-control mb-3" id="password" name="password" type="password" required>
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <label class="form-check"><input class="form-check-input" name="remember" type="checkbox"> Remember me</label>
                    <a href="index.php" class="small text-decoration-none">Login Siswa</a>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-right-to-bracket me-2"></i>Login Admin</button>
            </form>
            <div class="app-credit auth-credit">Contributor: By. Rojali</div>
        </section>
    </main>
</body>
</html>
