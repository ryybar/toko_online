<?php
declare(strict_types=1);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'toko_online';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(503);
    if (str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Database belum tersambung. Nyalakan MySQL XAMPP, pastikan database toko_online tersedia, lalu muat ulang.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit('Database belum tersambung. Nyalakan MySQL XAMPP, lalu periksa konfigurasi di includes/bootstrap.php.');
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

function notify_user(int $userId, string $type, string $title, string $message, string $url = 'dashboard.php'): void
{
    global $pdo;
    if ($userId < 1 || !marketplace_schema_ready()) return;
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,target_url) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId,$type,$title,$message,$url]);
    } catch (PDOException $exception) {
        error_log('ShopPoliteknik notification write failed: ' . $exception->getMessage());
    }
}

function notify_role(string $role, string $type, string $title, string $message, string $url = 'dashboard.php'): void
{
    global $pdo;
    if (!marketplace_schema_ready()) return;
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,target_url) SELECT id,?,?,?,? FROM users WHERE role=? AND is_active=1');
        $stmt->execute([$type,$title,$message,$url,$role]);
    } catch (PDOException $exception) {
        error_log('ShopPoliteknik notification write failed: ' . $exception->getMessage());
    }
}

function marketplace_schema_ready(): bool
{
    global $pdo;
    try {
        $columns=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='users' AND column_name='owner_singleton') OR (table_name='products' AND column_name='review_status') OR (table_name='order_items' AND column_name='status'))")->fetchColumn();
        $table=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='notifications'")->fetchColumn();
        $ownerIndex=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='uq_users_single_owner' AND non_unique=0")->fetchColumn();
        return (int)$columns===3 && (int)$table===1 && (int)$ownerIndex===1;
    } catch (PDOException $exception) {
        return false;
    }
}

function require_marketplace_schema(): void
{
    if (!marketplace_schema_ready()) {
        http_response_code(503);
        exit('Fitur produk, pesanan, dan notifikasi belum siap. Impor migrations/003_marketplace_workflow.sql ke database toko_online melalui phpMyAdmin, lalu muat ulang halaman.');
    }
}

function sync_order_status(int $orderId): void
{
    global $pdo;
    $stmt=$pdo->prepare("UPDATE orders o SET status=(SELECT CASE WHEN SUM(status='selesai')=COUNT(*) THEN 'selesai' WHEN SUM(status='dikirim')>0 THEN 'dikirim' WHEN SUM(status='diproses')>0 THEN 'diproses' WHEN SUM(status='dibatalkan')=COUNT(*) THEN 'dibatalkan' ELSE 'baru' END FROM order_items WHERE order_id=o.id) WHERE o.id=?");
    $stmt->execute([$orderId]);
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

function require_login(): array
{
    if (!user()) {
        header('Location: masuk.html');
        exit;
    }
    return user();
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
    global $pdo;
$current = user();
    $homeLink = $current ? 'shop.php' : 'index.html';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' · ShopPoliteknik</title><link rel="stylesheet" href="style.css"><script defer src="presence.js"></script></head><body class="app-body">';
    echo '<header class="app-topbar"><a class="brand" href="' . $homeLink . '"><span class="brand-mark">SP</span> Shop<span>Politeknik</span></a><nav>';
    if ($current) {
        $unreadNav=0;try{$navCount=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$navCount->execute([(int)$current['id']]);$unreadNav=(int)$navCount->fetchColumn();}catch(PDOException $exception){$unreadNav=0;}
        echo '<a href="shop.php">Toko</a><a class="notification-link" href="notifications.php" aria-label="Notifikasi">🔔 <span>Notifikasi</span><b class="notification-count">' . $unreadNav . '</b></a><a href="orders.php">Pesanan</a>';
        if($current['role']==='seller') echo '<a href="seller_products.php">Produk saya</a>';
        if($current['role']==='owner') echo '<a href="owner_products.php">Persetujuan produk</a>';
        echo '<a href="dashboard.php">Dashboard</a><span class="user-chip">' . h($current['name']) . ' · ' . h(ucfirst($current['role'])) . '</span><form method="post" action="logout.php"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><button class="text-button">Keluar</button></form>';
    } else echo '<a href="masuk.html">Masuk</a><a class="nav-cta" href="daftar.html">Daftar</a>';
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











