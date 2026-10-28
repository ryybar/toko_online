<?php
require __DIR__ . '/includes/bootstrap.php';
$current=require_role('owner');
require_marketplace_schema();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Metode tidak didukung.');}
verify_csrf();$id=(int)($_POST['product_id']??0);$decision=$_POST['decision']??'';$note=trim($_POST['review_note']??'');
if($id<1||!in_array($decision,['approved','rejected'],true)){flash('Keputusan produk tidak valid.','error');redirect('dashboard.php#produk-menunggu');}
$stmt=$pdo->prepare("SELECT id,seller_id,name,review_status FROM products WHERE id=? FOR UPDATE");$stmt->execute([$id]);$product=$stmt->fetch();
if(!$product||$product['review_status']!=='pending'){flash('Produk sudah ditinjau atau tidak ditemukan.','error');redirect('dashboard.php#produk-menunggu');}
$pdo->beginTransaction();
try{
 $update=$pdo->prepare("UPDATE products SET review_status=?,review_note=?,reviewed_by=?,reviewed_at=NOW(),is_active=? WHERE id=? AND review_status='pending'");
 $update->execute([$decision,$note?:null,(int)$current['id'],$decision==='approved'?1:0,$id]);
 $pdo->commit();
 if($product['seller_id'])notify_user((int)$product['seller_id'],'product_'.$decision,$decision==='approved'?'Produk disetujui':'Produk perlu diperbaiki',$decision==='approved'?"{$product['name']} telah disetujui dan tampil di toko.":"{$product['name']} belum disetujui. Catatan owner: ".($note?:'Silakan cek kembali informasi barang.'),'dashboard.php#produk');
 flash($decision==='approved'?'Produk disetujui dan sekarang tayang di toko.':'Produk ditolak; penjual menerima catatan pemeriksaan.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('Perubahan persetujuan produk gagal disimpan.','error');}
redirect('dashboard.php#produk-menunggu');
?>

