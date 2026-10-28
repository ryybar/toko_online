<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

set_exception_handler(static function (Throwable $exception): void {
    error_log('ShopPoliteknik health check failed: ' . $exception);
    if (!headers_sent()) http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Pemeriksaan server gagal. Periksa Vercel Runtime Logs.'], JSON_UNESCAPED_UNICODE);
});

require __DIR__ . '/../includes/bootstrap.php';

$requiredTables = ['users', 'products', 'orders', 'app_sessions'];
$stmt = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (?, ?, ?, ?)');
$stmt->execute($requiredTables);
$existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$missingTables = array_values(array_diff($requiredTables, $existingTables));

if ($missingTables) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'php' => PHP_VERSION,
        'database' => 'connected',
        'schema' => 'incomplete',
        'missingTables' => $missingTables,
        'message' => 'Koneksi TiDB berhasil, tetapi skema belum lengkap. Periksa database_vercel.sql di TiDB Cloud.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'php' => PHP_VERSION,
    'database' => 'connected',
    'schema' => 'ready',
    'message' => 'Runtime PHP Vercel dan koneksi database berjalan.',
], JSON_UNESCAPED_UNICODE);
