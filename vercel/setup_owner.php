<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Provisioning owner hanya boleh dijalankan dari terminal lokal.'); }
require __DIR__ . '/tools/create_owner.php';
