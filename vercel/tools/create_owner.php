<?php
// Run only from a local terminal: php tools/create_owner.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found'); }
require __DIR__ . '/../includes/bootstrap.php';
if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='owner'")->fetchColumn() > 0) { fwrite(STDERR, "Akun owner sudah ada.
"); exit(1); }
$name = trim((string)getenv('SHOP_OWNER_NAME'));
$email = strtolower(trim((string)getenv('SHOP_OWNER_EMAIL')));
$password = (string)getenv('SHOP_OWNER_PASSWORD');
if ($name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password) < 14) {
    fwrite(STDERR, "Set SHOP_OWNER_NAME, SHOP_OWNER_EMAIL, dan SHOP_OWNER_PASSWORD (minimal 14 karakter) di sesi terminal sementara.
"); exit(2);
}
try {
    $stmt=$pdo->prepare("INSERT INTO users(name,email,password_hash,role,is_active) VALUES(?,?,?,'owner',1)");
    $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Akun owner berhasil dibuat. Hapus variabel lingkungan setelah selesai.
");
} catch (PDOException $e) { fwrite(STDERR, $e->getCode()==='23000' ? "Email sudah dipakai akun lain.
" : "Akun owner gagal dibuat. Periksa database dan log PHP.
"); exit(3); }
