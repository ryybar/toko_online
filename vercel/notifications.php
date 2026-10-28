<?php
require __DIR__ . '/includes/bootstrap.php';
$current=require_login();
require_marketplace_schema();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $id=(int)($_POST['notification_id']??0); $action=$_POST['action']??'';
 if($action==='read_all'){$stmt=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');$stmt->execute([$current['id']]);}
 elseif($action==='read'&&$id>0){$stmt=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');$stmt->execute([$id,$current['id']]);}
 redirect('notifications.php');
}
$stmt=$pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$stmt->execute([$current['id']]);$items=$stmt->fetchAll();
$unread=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$unread->execute([$current['id']]);$unreadCount=(int)$unread->fetchColumn();
layout_start('Notifikasi');show_flash();
?>
<section class="dashboard-heading"><div><span class="eyebrow">PUSAT INFORMASI</span><h1>Notifikasi <span class="notification-count"><?=$unreadCount?></span></h1><p>Pesanan, persetujuan produk, dan kabar akunmu.</p></div><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="read_all"><button class="button button-light">Tandai semua dibaca</button></form></section>
<section class="panel notification-list"><?php if(!$items): ?><p>Belum ada notifikasi.</p><?php endif; ?><?php foreach($items as $item): ?><article class="notification-item <?=$item['is_read']?'':'unread'?>"><div><span class="eyebrow"><?=h(strtoupper(str_replace('_',' ',$item['type'])))?> · <?=h($item['created_at'])?></span><h2><?=h($item['title'])?></h2><p><?=h($item['message'])?></p></div><div class="notification-actions"><a class="button button-light" href="<?=h($item['target_url'])?>">Buka detail →</a><?php if(!$item['is_read']): ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="read"><input type="hidden" name="notification_id" value="<?=(int)$item['id']?>"><button class="text-button">Tandai dibaca</button></form><?php endif; ?></div></article><?php endforeach; ?></section>
<?php layout_end(); ?>

