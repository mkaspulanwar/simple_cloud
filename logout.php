<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Support\Flash;

AuthManager::logout();
Flash::add('success', 'Logout berhasil', 'Sesi Anda sudah ditutup.');

header('Location: index.php');
exit;
