<?php

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Services/AuditLogger.php';
require_once dirname(__DIR__) . '/app/Services/AuthService.php';

use App\Core\Session;
use App\Services\AuthService;

Session::start();
(new AuthService())->logout();
header('Location: admin_login.php');
exit;
