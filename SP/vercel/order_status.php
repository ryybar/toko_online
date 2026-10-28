<?php
require __DIR__ . '/includes/bootstrap.php';
$current=require_role('owner','seller');
require_marketplace_schema();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Metode tidak didukung.');}
verify_csrf();$itemId=(int)($_POST['item_id']??0);$status=$_POST['status']??'';
if($itemId<1||!in_array($status,['baru','diproses','dikirim','selesai','dibatalkan'],true)){flash('Status barang tidak valid.','error');redirect('orders.php');}
$sql='SELECT i.id,i.order_id,i.seller_id,i.product_name,o.customer_id,o.customer_name FROM order_items i JOIN orders o ON o.id=i.order_id WHERE i.id=?';
$args=[$itemId];if($current['role']==='seller'){$sql.=' AND i.seller_id=?';$args[]=(int)$current['id'];}
$stmt=$pdo->prepare($sql);$stmt->execute($args);$item=$stmt->fetch();
if(!$item){http_response_code(403);exit('Barang ini tidak dapat dikelola oleh akunmu.');}
$update=$pdo->prepare('UPDATE order_items SET status=?,status_updated_at=NOW() WHERE id=?');$update->execute([$status,$itemId]);sync_order_status((int)$item['order_id']);
notify_user((int)$item['customer_id'],'order_status','Status barang berubah',$item['product_name'].' pada pesanan #'.$item['order_id'].' sekarang berstatus '.$status.'.','orders.php');
if($current['role']==='seller')notify_role('owner','order_status','Status barang diperbarui',$current['name'].' mengubah '.$item['product_name'].' di pesanan #'.$item['order_id'].' menjadi '.$status.'.','orders.php');
else if((int)$item['seller_id']>0)notify_user((int)$item['seller_id'],'order_status','Owner memperbarui status barang',$item['product_name'].' di pesanan #'.$item['order_id'].' sekarang '.$status.'.','orders.php');
flash('Status barang diperbarui dan pembeli terkait diberi notifikasi.');redirect('orders.php');
?>



