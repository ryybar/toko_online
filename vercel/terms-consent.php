<?php
require __DIR__ . '/includes/bootstrap.php';
$current = user();
if (!$current || !in_array($current['role'], ['customer','seller'], true)) redirect('masuk.html');
$stmt = $pdo->prepare('SELECT id,version,title,body FROM terms_documents WHERE audience=? AND is_active=1 ORDER BY published_at DESC,id DESC LIMIT 1');
$stmt->execute([$current['role']]); $terms = $stmt->fetch();
if (!$terms) { http_response_code(503); exit('Ketentuan aktif belum tersedia. Hubungi owner.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['terms_accepted'] ?? '') !== '1' || (int)($_POST['terms_document_id'] ?? 0) !== (int)$terms['id']) { flash('Pilih persetujuan untuk versi ketentuan yang tampil.','error'); redirect('terms-consent.php'); }
    $save = $pdo->prepare('INSERT IGNORE INTO terms_acceptances(user_id,terms_document_id,accepted_ip) VALUES(?,?,?)');
    $save->execute([(int)$current['id'],(int)$terms['id'],$_SERVER['REMOTE_ADDR'] ?? null]);
    redirect($current['role'] === 'customer' ? 'shop.php' : 'dashboard.php');
}
layout_start('Persetujuan syarat dan ketentuan');
?>
<section class="panel terms-panel"><span class="eyebrow">TINJAUAN DIPERLUKAN</span><h1><?=h($terms['title'])?></h1><p>Versi <?=h($terms['version'])?> · <?=h(ucfirst($current['role']))?></p><div class="terms-document"><?=nl2br(h($terms['body']))?></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="terms_document_id" value="<?=(int)$terms['id']?>"><label class="terms-check"><input type="checkbox" name="terms_accepted" value="1" required><span>Saya telah membaca dan menyetujui versi ketentuan ini.</span></label><button class="button button-dark">Setujui dan lanjutkan</button></form></section>
<?php layout_end(); ?>