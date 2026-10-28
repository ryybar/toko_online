<?php
declare(strict_types=1);

// Jalankan halaman PHP root melalui runtime Vercel di dalam api/.
$_SERVER['SCRIPT_NAME'] = '/shop.php';
require dirname(__DIR__) . '/shop.php';
