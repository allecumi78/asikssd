<?php

require_once dirname(__DIR__) . '/app/Utilities/Security.php';

use App\Utilities\Security;

$app = require dirname(__DIR__) . '/config/app.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Ditolak - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-card text-center">
            <div class="brand-mark mx-auto mb-3">403</div>
            <h1>Akses Ditolak</h1>
            <p class="text-muted">Role Anda tidak memiliki izin untuk membuka modul ini.</p>
            <a class="btn btn-primary w-100" href="dashboard.php">Kembali ke Dashboard Admin</a>
        </section>
    </main>
</body>
</html>
