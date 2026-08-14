<?php

require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Services/StudentAuthService.php';

use App\Core\Session;
use App\Services\StudentAuthService;

Session::start();
(new StudentAuthService())->logout();
header('Location: index.php');
exit;
