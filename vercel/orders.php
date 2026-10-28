<?php
require __DIR__ . '/includes/bootstrap.php';
$current=require_login();
require_marketplace_schema();
if($current['role']==='owner'){
 $orders=$pdo->query("SELECT o.*,u.email customer_email FROM orders o JOIN users u ON u.id=o.customer_id ORDER BY o.created_at DESC")->fetchAll();
 $itemStmt=$pdo->prepare("SELECT i.*,u.name seller_name FROM order_items i LEFT JOIN users u ON u.id=i.seller_id WHERE i.order_id=? ORDER BY i.id");
}elseif($current['role']==='seller'){
 $stmt=$pdo->prepare("SELECT DISTINCT o.*,u.email customer_email FROM orders o JOIN users u ON u.id=o.customer_id JOIN order_items i ON i.order_id=o.id WHERE i.seller_id=? ORDER BY o.created_at DESC");$stmt->execute([$current['id']]);$orders=$stmt->fetchAll();
 $itemStmt=$pdo->prepare("SELECT i.*,u.name seller_name FROM order_items i LEFT JOIN users u ON u.id=i.seller_id WHERE i.order_id=? AND i.seller_id=? ORDER BY i.id");
}else{
 $stmt=$pdo->prepare('SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC');$stmt->execute([$current['id']]);$orders=$stmt->fetchAll();
 $itemStmt=$pdo->prepare("SELECT i.*,u.name seller_name FROM order_items i LEFT JOIN users u ON u.id=i.seller_id WHERE i.order_id=? ORDER BY i.id");
}
layout_start('Pesanan');show_flash();
?>
<section class="dashboard-heading"><div><span class="eyebrow">PELACAKAN TRANSAKSI</span><h1><?=$current['role']==='customer'?'Pesanan saya':'Pesanan toko'?></h1><p>Setiap barang memiliki status sendiri sehingga pengiriman dari beberapa toko dapat dipantau terpisah.</p></div></section>
<?php if(!$orders): ?><section class="panel"><p>Belum ada pesanan.</p></section><?php endif; ?>
<?php foreach($orders as $order): $itemStmt->execute($current['role']==='seller'?[$order['id'],$current['id']]:[$order['id']]);$items=$itemStmt->fetchAll(); ?>
<section class="panel order-detail"><div class="panel-heading"><div><span class="eyebrow">PESANAN #<?=(int)$order['id']?> · <?=h($order['created_at'])?></span><h2><?=h($order['customer_name'])?></h2><p><?=h($order['customer_email']??'')?> · <?=h($order['phone'])?> · <?=h($order['address'])?></p></div><?php if($current['role']!=='customer'): ?><strong><?=rupiah((int)$order['total'])?></strong><?php endif; ?></div>
<div class="table-wrap"><table><thead><tr><th>Barang</th><th>Toko penjual</th><th>Jumlah</th><th>Harga</th><th>Status barang</th><th>Riwayat</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?=h($item['product_name'])?></td><td><?=h($item['seller_name']??'Toko utama')?></td><td><?=(int)$item['quantity']?></td><td><?=rupiah((int)$item['line_total'])?></td><td><span class="status-pill"><?=h($item['status'])?></span><?php if(in_array($current['role'],['owner','seller'],true)): ?><form method="post" action="order_status.php" class="inline-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="item_id" value="<?=(int)$item['id']?>"><select name="status"><?php foreach(['baru','diproses','dikirim','selesai','dibatalkan'] as $status): ?><option value="<?=$status?>" <?=$item['status']===$status?'selected':''?>><?=ucfirst($status)?></option><?php endforeach; ?></select><button class="button button-light">Simpan</button></form><?php endif; ?></td><td><?=h($item['status_updated_at'])?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if($current['role']==='owner'): ?><p class="sp-hint">Owner dapat meninjau pembeli, alamat, toko asal tiap barang, nilai, serta perubahan status pada baris barang.</p><?php endif; ?></section>
<?php endforeach; layout_end(); ?>

