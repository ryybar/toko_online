<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('beranda.php');
verify_csrf();
if (!empty($_SESSION['user']['id'])) { try { $removeActivity = $pdo->prepare('DELETE FROM user_activity WHERE user_id=?'); $removeActivity->execute([(int)$_SESSION['user']['id']]); } catch (PDOException $exception) { /* Logout must still finish if presence tracking is unavailable. */ } }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
redirect('beranda.php');
