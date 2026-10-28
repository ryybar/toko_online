<?php
require __DIR__ . '/includes/bootstrap.php';
$current=require_role('owner');
require_marketplace_schema();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$id=(int)($_POST['product_id']??0);$decision=$_POST['decision']??'';$note=trim($_POST['review_note']??'');
 if($id<1||!in_array($decision,['approved','rejected'],true)){flash('Keputusan produk tidak valid.','error');redirect('owner_products.php');}
 $stmt=$pdo->prepare("SELECT seller_id,name FROM products WHERE id=? AND review_status='pending'");$stmt->execute([$id]);$product=$stmt->fetch();
 if(!$product){flash('Produk sudah ditinjau atau tidak ditemukan.','error');redirect('owner_products.php');}
 $update=$pdo->prepare("UPDATE products SET review_status=?,review_note=?,reviewed_by=?,reviewed_at=NOW(),is_active=? WHERE id=? AND review_status='pending'");
 $update->execute([$decision,$note?:null,(int)$current['id'],$decision==='approved'?1:0,$id]);
 if($product['seller_id'])notify_user((int)$product['seller_id'],'product_'.$decision,$decision==='approved'?'Produk disetujui':'Produk perlu diperbaiki',$decision==='approved'?$product['name'].' sudah tayang di toko.':$product['name'].' belum disetujui. Catatan owner: '.($note?:'Silakan periksa kembali informasi barang.'),'seller_products.php');
 flash($decision==='approved'?'Produk disetujui dan tayang di toko.':'Produk ditolak; penjual menerima catatan.');redirect('owner_products.php');
}
$pending=$pdo->query("SELECT p.*,u.name seller_name,u.email seller_email FROM products p JOIN users u ON u.id=p.seller_id WHERE p.review_status='pending' ORDER BY p.created_at ASC")->fetchAll();
$recent=$pdo->query('SELECT p.*,u.name seller_name FROM products p LEFT JOIN users u ON u.id=p.seller_id ORDER BY p.created_at DESC LIMIT 100')->fetchAll();
layout_start('Persetujuan Produk');show_flash();
?>
<section class="dashboard-heading"><div><span class="eyebrow">MODERASI TOKO</span><h1>Persetujuan barang</h1><p>Periksa barang yang diajukan penjual sebelum tampil pada etalase publik.</p></div><a class="button button-light" href="dashboard.php">Dashboard owner →</a></section>
<section class="panel"><div class="panel-heading"><div><span class="eyebrow">ANTREAN PEMERIKSAAN</span><h2><?=count($pending)?> barang menunggu</h2></div></div><?php if(!$pending): ?><p>Belum ada pengajuan menunggu.</p><?php endif; ?><?php foreach($pending as $p): ?><article class="product-review-card"><div class="product-review-grid"><div><h2><?=h($p['name'])?></h2><p><?=h($p['category'])?> · <?=h($p['product_condition'])?></p><small>Penjual: <?=h($p['seller_name'])?> · <?=h($p['seller_email'])?></small></div><div><strong><?=rupiah((int)$p['price'])?></strong><small>Stok: <?=(int)$p['stock']?> · Berat: <?=(int)$p['weight_grams']?> g</small><small>Kirim: <?=(int)$p['shipping_days']?> hari</small></div><div><small>Merek: <?=h($p['brand']??'—')?> · SKU: <?=h($p['sku']??'—')?></small><small>Bahan: <?=h($p['material']??'—')?> · Varian: <?=h($p['color_variant']??'—')?></small><small>Ukuran: <?=h($p['dimensions']??'—')?></small><?php if($p['image_url']): ?><a href="<?=h($p['image_url'])?>" target="_blank" rel="noopener">Periksa foto</a><?php endif; ?></div></div><p><?=h($p['description'])?></p><form method="post" class="seller-review-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><label>Catatan owner (isi jika menolak / minta perbaikan)<input name="review_note" maxlength="500"></label><button class="button button-dark" name="decision" value="approved">Setujui & tayangkan</button><button class="button button-light" name="decision" value="rejected">Tolak / minta perbaikan</button></form></article><?php endforeach; ?></section>
<section class="panel"><div class="panel-heading"><div><span class="eyebrow">KATALOG</span><h2>Riwayat pengajuan barang</h2></div></div><div class="table-wrap"><table><thead><tr><th>Barang</th><th>Penjual</th><th>Harga</th><th>Status</th><th>Catatan owner</th></tr></thead><tbody><?php foreach($recent as $p): ?><tr><td><?=h($p['name'])?><small><?=h($p['category'])?></small></td><td><?=h($p['seller_name']??'Penjual tidak aktif')?></td><td><?=rupiah((int)$p['price'])?></td><td><?=h($p['review_status'])?></td><td><?=h($p['review_note']??'—')?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php layout_end(); ?>

