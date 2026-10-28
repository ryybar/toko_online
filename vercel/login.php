<?php
$role = urlencode($_GET['role'] ?? 'customer');
header('Location: masuk.html?role=' . $role);
exit;
