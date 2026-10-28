<?php
declare(strict_types=1);

$envValue = static function (string $name, ?string $fallback = null): ?string {
    $value = getenv($name);
    return $value === false || $value === '' ? $fallback : $value;
};
$tidbHost = $envValue('TIDB_HOST');
$dbHost = $envValue('DB_HOST', $tidbHost ?? '127.0.0.1');
$dbPort = $envValue('DB_PORT', $envValue('TIDB_PORT', $tidbHost ? '4000' : '3306'));
$dbName = $envValue('DB_NAME', $envValue('TIDB_DATABASE', 'toko_online'));
$dbUser = $envValue('DB_USER', $envValue('TIDB_USER', 'root'));
$dbPass = $envValue('DB_PASS', $envValue('TIDB_PASSWORD', ''));
$isApiRequest = str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/api/');

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 8,
    ];
    $remoteDatabase = !in_array(strtolower($dbHost), ['localhost', '127.0.0.1', '::1'], true);
    if ($remoteDatabase) {
        if (!defined('PDO::MYSQL_ATTR_SSL_CA')) throw new PDOException('PDO MySQL TLS support is unavailable.');
        $caPath = $envValue('DB_SSL_CA');
        if (!$caPath) {
            foreach(['/etc/ssl/certs/ca-certificates.crt','/etc/pki/tls/certs/ca-bundle.crt','/etc/ssl/cert.pem'] as $candidate) {
                if (is_file($candidate)) { $caPath = $candidate; break; }
            }
        }
        if (!$caPath || !is_file($caPath)) throw new PDOException('A trusted CA bundle is required for remote database TLS. Set DB_SSL_CA if needed.');
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $options);
} catch (PDOException $exception) {
    error_log('ShopPoliteknik database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    if ($isApiRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Database TiDB belum tersambung. Periksa TIDB_HOST, TIDB_PORT, TIDB_USER, TIDB_PASSWORD, TIDB_DATABASE, TLS, dan skema database.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    exit('Database belum tersambung. Periksa variabel koneksi TiDB di pengaturan deployment.');
}

final class PdoSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo) {}
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT payload FROM app_sessions WHERE session_id=? AND expires_at>?');
        $stmt->execute([$id, time()]);
        $payload = $stmt->fetchColumn();
        return $payload === false ? '' : (string)$payload;
    }
    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare('INSERT INTO app_sessions (session_id,payload,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)');
        return $stmt->execute([$id, $data, time() + (int)ini_get('session.gc_maxlifetime')]);
    }
    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE session_id=?');
        return $stmt->execute([$id]);
    }
    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE expires_at<?');
        $stmt->execute([time()]);
        return $stmt->rowCount();
    }
}

ini_set('session.use_strict_mode', '1');
session_name('SPSESSID');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
try {
    if (!session_set_save_handler(new PdoSessionHandler($pdo), true)) throw new RuntimeException('Unable to register persistent session handler.');
    session_start();
} catch (Throwable $exception) {
    error_log('ShopPoliteknik session initialization failed: ' . $exception->getMessage());
    http_response_code(503);
    if ($isApiRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Tabel sesi app_sessions belum tersedia. Jalankan database_vercel.sql di TiDB.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit('Tabel sesi app_sessions belum tersedia. Jalankan database_vercel.sql di TiDB.');
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity (user_id BIGINT UNSIGNED PRIMARY KEY, last_seen_at DATETIME NOT NULL, CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB");
} catch (PDOException $exception) {
    // Other application pages still run if the host blocks activity tracking.
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS seller_applications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL UNIQUE, seller_status ENUM('active','planning') NOT NULL, product_name VARCHAR(120) NOT NULL, product_category VARCHAR(60) NOT NULL, product_description VARCHAR(255) NOT NULL, expected_monthly_profit INT UNSIGNED NOT NULL DEFAULT 0, item_weight_grams INT UNSIGNED NOT NULL DEFAULT 0, monthly_plan ENUM('trial','monthly') NOT NULL DEFAULT 'monthly', monthly_budget INT UNSIGNED DEFAULT NULL, application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', reviewed_at DATETIME DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_seller_application_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB");
} catch (PDOException $exception) {
    // Registration remains available if an administrator restricts automatic table creation.
}

function h(string|int|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Formulir kedaluwarsa. Muat ulang halaman lalu coba kembali.');
    }
}

function user(): ?array
{
    global $pdo;
    $current = $_SESSION['user'] ?? null;
    if ($current && isset($pdo) && time() - (int)($_SESSION['last_seen_touch'] ?? 0) >= 60) {
        try {
            $activity = $pdo->prepare('INSERT INTO user_activity (user_id,last_seen_at) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at)');
            $activity->execute([(int)$current['id']]);
            $_SESSION['last_seen_touch'] = time();
        } catch (PDOException $exception) {
            // Dashboard still displays sales metrics if tracking is unavailable.
        }
    }
    return $current;
}

function has_current_terms_consent(array $current): bool
{
    global $pdo;
    if (!in_array($current['role'] ?? '', ['customer','seller'], true)) return true;
    try {
        $stmt = $pdo->prepare('SELECT d.id FROM terms_documents d WHERE d.audience=? AND d.is_active=1 ORDER BY d.published_at DESC,d.id DESC LIMIT 1');
        $stmt->execute([$current['role']]); $documentId = $stmt->fetchColumn();
        if (!$documentId) return true;
        $check = $pdo->prepare('SELECT 1 FROM terms_acceptances WHERE user_id=? AND terms_document_id=?');
        $check->execute([(int)$current['id'],(int)$documentId]);
        return (bool)$check->fetchColumn();
    } catch (PDOException $exception) { return true; }
}

function require_login(): array
{
    $current = user();
    if (!$current) { header('Location: masuk.html'); exit; }
    if (!has_current_terms_consent($current) && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'terms-consent.php') { header('Location: terms-consent.php'); exit; }
    return $current;
}

function require_role(string ...$roles): array
{
    $current = require_login();
    if (!in_array($current['role'], $roles, true)) {
        http_response_code(403);
        exit('Kamu tidak memiliki akses ke halaman ini.');
    }
    return $current;
}

function rupiah(int|float $amount): string
{
    return 'Rp' . number_format((float)$amount, 0, ',', '.');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function layout_start(string $title): void
{
    $current = user();
    $homeLink = $current ? 'shop.php' : 'index.html';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' · ShopPoliteknik</title><link rel="stylesheet" href="style.css"><script defer src="presence.js"></script></head><body class="app-body">';
    echo '<header class="app-topbar"><a class="brand" href="' . $homeLink . '"><span class="brand-mark">SP</span> Shop<span>Politeknik</span></a><nav>';
    if ($current && $current['role']==='customer') echo '<a href="shop.php">Toko</a>';
    if ($current && $current['role']==='seller') echo '<a href="seller-products.php">Kelola produk</a>';
    if ($current) {
        $unread=0; try{$count=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND is_read=0');$count->execute([(int)$current['id']]);$unread=(int)$count->fetchColumn();}catch(PDOException $exception){}
        echo '<a href="dashboard.php">Dashboard</a><a href="notifications.php">Notifikasi'.($unread?' <b class="notification-count">'.$unread.'</b>':'').'</a>';
    }
    if ($current) echo '<span class="user-chip">' . h($current['name']) . ' · ' . h(ucfirst($current['role'])) . '</span><form method="post" action="logout.php"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><button class="text-button">Keluar</button></form>';
    else echo '<a href="masuk.html">Masuk</a><a class="nav-cta" href="daftar.html">Daftar</a>';
    echo '</nav></header><main class="app-main">';
}

function layout_end(): void
{
    echo '</main><footer class="app-footer">ShopPoliteknik · Belanja mudah, pilihan tepat</footer></body></html>';
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function show_flash(): void
{
    if (empty($_SESSION['flash'])) return;
    $notice = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="notice ' . h($notice['type']) . '" role="status">' . h($notice['message']) . '</div>';
}
