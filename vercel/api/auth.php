<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(static function (Throwable $exception): void {
    error_log('ShopPoliteknik API uncaught exception: ' . $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    echo json_encode(['ok' => false, 'message' => 'Server gagal memproses permintaan. Periksa koneksi database dan tabel proyek.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ownerExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='owner'")->fetchColumn() > 0;
    $current = user();
    $terms = [];
    foreach (['customer','seller'] as $audience) {
        $termsStmt = $pdo->prepare('SELECT id,version,title,body FROM terms_documents WHERE audience=? AND is_active=1 ORDER BY published_at DESC,id DESC LIMIT 1');
        $termsStmt->execute([$audience]);
        $terms[$audience] = $termsStmt->fetch() ?: null;
    }
    respond(200, ['csrf' => csrf_token(), 'ownerExists' => $ownerExists, 'signedIn' => $current !== null, 'redirect' => $current && !has_current_terms_consent($current) ? 'terms-consent.php' : ($current && $current['role'] === 'customer' ? 'shop.php' : 'dashboard.php'), 'terms' => $terms]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['ok' => false, 'message' => 'Metode tidak didukung.']);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) respond(400, ['ok' => false, 'message' => 'Data formulir tidak terbaca.']);
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($input['csrf'] ?? ''))) respond(419, ['ok' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.']);

$action = (string)($input['action'] ?? '');
if ($action === 'logout') {
    if (!empty($_SESSION['user']['id'])) { $removeActivity = $pdo->prepare('DELETE FROM user_activity WHERE user_id=?'); $removeActivity->execute([(int)$_SESSION['user']['id']]); }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    }
    session_destroy();
    respond(200, ['ok' => true, 'redirect' => 'index.html']);
}

$role = (string)($input['role'] ?? 'customer');
if (!in_array($role, ['owner', 'seller', 'customer'], true)) respond(422, ['ok' => false, 'message' => 'Pilih jenis akun yang tersedia.']);
$email = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');

if ($action === 'login') {
    $ipHash = hash('sha256',(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')); $emailHash = hash('sha256',$email);
    $rate = $pdo->prepare('SELECT SUM(ip_hash=?) ip_total,SUM(ip_hash=? AND email_hash=?) pair_total FROM auth_attempts WHERE attempted_at>=NOW()-INTERVAL 15 MINUTE');
    $rate->execute([$ipHash,$ipHash,$emailHash]); $rateRow=$rate->fetch();
    if ((int)($rateRow['ip_total']??0)>=40 || (int)($rateRow['pair_total']??0)>=8) respond(429,['ok'=>false,'message'=>'Terlalu banyak percobaan login. Tunggu 15 menit sebelum mencoba kembali.']);
    $stmt = $pdo->prepare('SELECT id,name,email,phone,password_hash,role,is_active FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $account = $stmt->fetch();
    if (!$account || !password_verify($password, $account['password_hash'])) {
        $attempt=$pdo->prepare('INSERT INTO auth_attempts(ip_hash,email_hash) VALUES(?,?)');$attempt->execute([$ipHash,$emailHash]);
        respond(401, ['ok' => false, 'message' => 'Email atau kata sandi tidak cocok.']);
    }
    $pdo->prepare('DELETE FROM auth_attempts WHERE ip_hash=? AND email_hash=?')->execute([$ipHash,$emailHash]);
    if ($account['role'] !== $role) respond(403, ['ok' => false, 'message' => 'Akun ini terdaftar sebagai ' . ucfirst($account['role']) . '. Pilih jenis akun yang sesuai.']);
    if ((int)$account['is_active'] !== 1) respond(403, ['ok' => false, 'message' => $account['role'] === 'seller' ? 'Akun penjual belum aktif. Periksa keputusan permohonanmu kepada owner.' : 'Akun ini sedang tidak aktif.']);
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$account['id'], 'name' => $account['name'], 'email' => $account['email'], 'phone' => $account['phone'], 'role' => $account['role']];
    $currentUser = $_SESSION['user'];
    $next = has_current_terms_consent($currentUser) ? ($account['role'] === 'customer' ? 'shop.php' : 'dashboard.php') : 'terms-consent.php';
    respond(200, ['ok' => true, 'message' => 'Berhasil masuk.', 'redirect' => $next]);
}

if ($action !== 'register') respond(422, ['ok' => false, 'message' => 'Aksi formulir tidak dikenal.']);
$name = trim((string)($input['name'] ?? ''));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(422, ['ok' => false, 'message' => 'Isi nama dan alamat email dengan benar.']);

if ($role === 'seller') {
    if (strlen($password) < 8) respond(422, ['ok' => false, 'message' => 'Kata sandi minimal 8 karakter.']);
    if ($password !== (string)($input['password_confirmation'] ?? '')) respond(422, ['ok' => false, 'message' => 'Konfirmasi kata sandi tidak sama.']);
    $termsStmt = $pdo->prepare("SELECT id,version FROM terms_documents WHERE audience='seller' AND is_active=1 ORDER BY published_at DESC,id DESC LIMIT 1"); $termsStmt->execute(); $activeTerms = $termsStmt->fetch();
    if (!$activeTerms || empty($input['terms_accepted']) || (int)($input['terms_document_id'] ?? 0) !== (int)$activeTerms['id']) respond(422, ['ok' => false, 'message' => 'Baca dan setujui syarat penjual versi aktif sebelum mengirim permohonan.']);
    $storeName = trim((string)($input['store_name'] ?? '')); $storeDescription = trim((string)($input['store_description'] ?? '')); $storeAddress = trim((string)($input['store_address'] ?? '')); $storePhone = trim((string)($input['store_phone'] ?? ''));
    if ($storeName === '' || $storeDescription === '' || $storeAddress === '' || $storePhone === '') respond(422, ['ok' => false, 'message' => 'Lengkapi nama, deskripsi, alamat, dan kontak toko.']);
    $sellerStatus = (string)($input['seller_status'] ?? '');
    $productName = trim((string)($input['product_name'] ?? ''));
    $productCategory = trim((string)($input['product_category'] ?? ''));
    $productDescription = trim((string)($input['product_description'] ?? ''));
    $monthlyProfit = filter_var($input['expected_monthly_profit'] ?? null, FILTER_VALIDATE_INT);
    $itemWeight = filter_var($input['item_weight_grams'] ?? null, FILTER_VALIDATE_INT);
    $monthlyPlan = (string)($input['monthly_plan'] ?? '');
    $monthlyBudgetInput = trim((string)($input['monthly_budget'] ?? ''));
    $monthlyBudget = $monthlyBudgetInput === '' ? null : filter_var($monthlyBudgetInput, FILTER_VALIDATE_INT);
    if (!in_array($sellerStatus, ['active','planning'], true) || $productName === '' || $productCategory === '' || $productDescription === '' || $monthlyProfit === false || $monthlyProfit < 0 || $itemWeight === false || $itemWeight < 1 || !in_array($monthlyPlan, ['trial','monthly'], true) || ($monthlyBudgetInput !== '' && ($monthlyBudget === false || $monthlyBudget < 0))) {
        respond(422, ['ok' => false, 'message' => 'Lengkapi semua data penjual, produk, keuntungan, berat, dan pilihan paket dengan benar.']);
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role,is_active) VALUES (?,?,?,?, 'seller',0)");
        $phone = trim((string)($input['phone'] ?? ''));
        $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        $sellerId = (int)$pdo->lastInsertId();
        $application = $pdo->prepare('INSERT INTO seller_applications (user_id,seller_status,product_name,product_category,product_description,expected_monthly_profit,item_weight_grams,monthly_plan,monthly_budget) VALUES (?,?,?,?,?,?,?,?,?)');
        $application->execute([$sellerId,$sellerStatus,$productName,$productCategory,$productDescription,$monthlyProfit,$itemWeight,$monthlyPlan,$monthlyBudget]);
        $profileStmt = $pdo->prepare("INSERT INTO seller_business_profiles (user_id,store_name,store_description,store_address,store_phone,application_status,seller_status,expected_monthly_profit,item_weight_grams,monthly_plan,monthly_budget) VALUES (?,?,?,?,?,'pending',?,?,?,?,?)");
        $profileStmt->execute([$sellerId,$storeName,$storeDescription,$storeAddress,$storePhone,$sellerStatus,$monthlyProfit,$itemWeight,$monthlyPlan,$monthlyBudget]);
        $accept = $pdo->prepare('INSERT INTO terms_acceptances (user_id,terms_document_id,accepted_ip) VALUES (?,?,?)');
        $accept->execute([$sellerId,(int)$activeTerms['id'],$_SERVER['REMOTE_ADDR'] ?? null]);
        $pdo->commit();
        respond(201, ['ok' => true, 'message' => 'Permohonan penjual berhasil dikirim. Akun dapat digunakan setelah disetujui owner.', 'redirect' => 'masuk.html?application=pending']);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception->getCode() === '23000') respond(409, ['ok' => false, 'message' => 'Email sudah digunakan. Coba masuk atau gunakan email lain.']);
        respond(500, ['ok' => false, 'message' => 'Permohonan belum dapat disimpan. Pastikan tabel database sudah diperbarui lalu coba lagi.']);
    }
}
if ($role === 'customer') {
    $termsStmt = $pdo->prepare("SELECT id FROM terms_documents WHERE audience='customer' AND is_active=1 ORDER BY published_at DESC,id DESC LIMIT 1"); $termsStmt->execute(); $activeTerms = $termsStmt->fetch();
    if (!$activeTerms || empty($input['terms_accepted']) || (int)($input['terms_document_id'] ?? 0) !== (int)$activeTerms['id']) respond(422, ['ok' => false, 'message' => 'Baca dan setujui syarat pembeli versi aktif sebelum mendaftar.']);
    if (strlen($password) < 8) respond(422, ['ok' => false, 'message' => 'Kata sandi minimal 8 karakter.']);
    if ($password !== (string)($input['password_confirmation'] ?? '')) respond(422, ['ok' => false, 'message' => 'Konfirmasi kata sandi tidak sama.']);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?, 'customer')");
        $stmt->execute([$name, $email, trim((string)($input['phone'] ?? '')), password_hash($password, PASSWORD_DEFAULT)]);
        $customerId = (int)$pdo->lastInsertId();
        $accept = $pdo->prepare('INSERT INTO terms_acceptances (user_id,terms_document_id,accepted_ip) VALUES (?,?,?)');
        $accept->execute([$customerId,(int)$activeTerms['id'],$_SERVER['REMOTE_ADDR'] ?? null]);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception->getCode() === '23000') respond(409, ['ok' => false, 'message' => 'Email sudah terdaftar. Coba masuk atau gunakan email lain.']);
        respond(500, ['ok' => false, 'message' => 'Akun belum dapat disimpan. Coba lagi nanti.']);
    }
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $customerId, 'name' => $name, 'email' => $email, 'phone' => trim((string)($input['phone'] ?? '')), 'role' => 'customer'];
    respond(201, ['ok' => true, 'message' => 'Akun pelanggan berhasil dibuat.', 'redirect' => 'shop.php']);
}

if (strlen($password) < 12) respond(422, ['ok' => false, 'message' => 'Kata sandi owner minimal 12 karakter.']);
if ($password !== (string)($input['password_confirmation'] ?? '')) respond(422, ['ok' => false, 'message' => 'Konfirmasi kata sandi tidak sama.']);
if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='owner'")->fetchColumn() > 0) respond(409, ['ok' => false, 'message' => 'Akun owner sudah tersedia. Silakan masuk sebagai owner.']);
$lockAcquired = (int)$pdo->query("SELECT GET_LOCK('shoppoliteknik_owner_setup', 10)")->fetchColumn() === 1;
if (!$lockAcquired) respond(503, ['ok' => false, 'message' => 'Setup owner sedang digunakan. Muat ulang dan coba lagi.']);
try {
    $pdo->beginTransaction();
    if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='owner'")->fetchColumn() > 0) throw new RuntimeException('Owner sudah dibuat.');
    $ownerPhone = trim((string)($input['phone'] ?? ''));
    $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,'owner')");
    $stmt->execute([$name, $email, $ownerPhone === '' ? null : $ownerPhone, password_hash($password, PASSWORD_DEFAULT)]);
    $ownerId = (int)$pdo->lastInsertId();
    $pdo->commit();
    $pdo->query("SELECT RELEASE_LOCK('shoppoliteknik_owner_setup')");
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $ownerId, 'name' => $name, 'email' => $email, 'phone' => ($ownerPhone === '' ? null : $ownerPhone), 'role' => 'owner'];
    respond(201, ['ok' => true, 'message' => 'Akun owner berhasil dibuat.', 'redirect' => 'dashboard.php']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->query("SELECT RELEASE_LOCK('shoppoliteknik_owner_setup')");
    if ($exception instanceof PDOException && $exception->getCode() === '23000') {
        $databaseMessage = (string)($exception->errorInfo[2] ?? '');
        if (str_contains($databaseMessage, 'uq_users_single_owner')) respond(409, ['ok' => false, 'message' => 'Akun owner sudah dibuat. Silakan masuk sebagai owner.']);
        respond(409, ['ok' => false, 'message' => 'Email sudah terdaftar. Gunakan email lain.']);
    }
    if ($exception instanceof RuntimeException) respond(409, ['ok' => false, 'message' => 'Akun owner sudah dibuat. Silakan masuk sebagai owner.']);
    respond(500, ['ok' => false, 'message' => 'Akun owner belum dapat disimpan. Coba lagi nanti.']);
}
