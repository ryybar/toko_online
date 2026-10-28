<?php
$role = urlencode($_GET['role'] ?? 'customer');
header('Location: daftar.html?role=' . $role);
exit;
