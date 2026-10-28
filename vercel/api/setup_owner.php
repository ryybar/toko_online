<?php
declare(strict_types=1);

// Jalankan halaman PHP root melalui runtime Vercel di dalam api/.
$_SERVER['SCRIPT_NAME'] = '/setup_owner.php';
require dirname(__DIR__) . '/setup_owner.php';
