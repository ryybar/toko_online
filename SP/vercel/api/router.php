<?php
declare(strict_types=1);

$route = (string)($_GET['route'] ?? '');
$root = dirname(__DIR__);

switch ($route) {
    case 'beranda.php': require $root . '/beranda.php'; break;
    case 'dashboard.php': require $root . '/dashboard.php'; break;
    case 'shop.php': require $root . '/shop.php'; break;
    case 'login.php': require $root . '/login.php'; break;
    case 'register.php': require $root . '/register.php'; break;
    case 'logout.php': require $root . '/logout.php'; break;
    case 'setup_owner.php': require $root . '/setup_owner.php'; break;
    case 'notifications.php': require $root . '/notifications.php'; break;
    case 'orders.php': require $root . '/orders.php'; break;
    case 'order_status.php': require $root . '/order_status.php'; break;
    case 'owner_products.php': require $root . '/owner_products.php'; break;
    case 'seller_products.php': require $root . '/seller_products.php'; break;
    case 'product_review.php': require $root . '/product_review.php'; break;
    case 'product_submit.php': require $root . '/product_submit.php'; break;
    case 'api/auth.php': require $root . '/api/auth.php'; break;
    default:
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Halaman tidak ditemukan.';
}