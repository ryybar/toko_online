<?php
require __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$cart = $_SESSION['cart'] ?? [];
$marketplaceReady=marketplace_schema_ready();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['product_id'] ?? 0);
    if ($action === 'add') {
        $check = $pdo->prepare($marketplaceReady ? "SELECT id,stock FROM products WHERE id=? AND is_active=1 AND review_status='approved'" : 'SELECT id,stock FROM products WHERE id=? AND is_active=1');
        $check->execute([$id]);
        $product = $check->fetch();
        if ($product && (int)$product['stock'] > 0) {
            $cart[$id] = min(99, (int)($cart[$id] ?? 0) + 1);
            $_SESSION['cart'] = $cart;
            flash('Produk ditambahkan ke keranjang.');
        } else flash('Produk sedang tidak tersedia.','error');
    } elseif ($action === 'quantity') {
        $quantity = max(0,min(99,(int)($_POST['quantity'] ?? 0)));
        if ($quantity === 0) unset($cart[$id]); else $cart[$id]=$quantity;
        $_SESSION['cart']=$cart;
    } elseif ($action === 'checkout') {
        $current = require_login();
        if ($current['role'] !== 'customer') { flash('Checkout hanya tersedia untuk akun pelanggan.','error'); redirect('dashboard.php'); }
        $name=trim($_POST['customer_name'] ?? ''); $phone=trim($_POST['phone'] ?? ''); $address=trim($_POST['address'] ?? '');
        if (!$cart || $name==='' || $phone==='' || $address==='') { flash('Lengkapi data pengiriman dan isi keranjang.','error'); redirect('shop.php#cart'); }
        try {
            $pdo->beginTransaction(); $lines=[]; $total=0;
            foreach ($cart as $productId=>$quantity) {
                $stmt=$pdo->prepare($marketplaceReady ? "SELECT id,seller_id,name,price,stock FROM products WHERE id=? AND is_active=1 AND review_status='approved' FOR UPDATE" : 'SELECT id,seller_id,name,price,stock FROM products WHERE id=? AND is_active=1 FOR UPDATE');
                $stmt->execute([(int)$productId]); $product=$stmt->fetch(); $quantity=(int)$quantity;
                if (!$product || $quantity<1 || (int)$product['stock']<$quantity) throw new DomainException('Stok produk berubah. Periksa keranjang lalu ulangi.');
                $lines[]=[$product,$quantity]; $total+=(int)$product['price']*$quantity;
            }
            $stmt=$pdo->prepare("INSERT INTO orders (customer_id,customer_name,phone,address,total) VALUES (?,?,?,?,?)");
            $stmt->execute([$current['id'],$name,$phone,$address,$total]); $orderId=(int)$pdo->lastInsertId();
            $lineStmt=$pdo->prepare('INSERT INTO order_items (order_id,product_id,seller_id,product_name,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?,?)');
            $stockStmt=$pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?');
            foreach($lines as [$product,$quantity]) {
                $lineStmt->execute([$orderId,$product['id'],$product['seller_id'],$product['name'],$quantity,$product['price'],$product['price']*$quantity]);
                $stockStmt->execute([$quantity,$product['id']]);
            }
            $pdo->commit(); $_SESSION['cart']=[]; notify_user((int)$current['id'],'order_placed','Pesanan berhasil dibuat',"Pesanan #{$orderId} tersimpan dan sedang menunggu diproses.",'dashboard.php#pesanan'); notify_role('owner','new_order','Pesanan baru',"Pesanan #{$orderId} dari {$name} masuk. Periksa pembeli, toko, dan status barang.",'dashboard.php#pesanan'); foreach(array_unique(array_filter(array_map(fn($line)=>(int)$line[0]['seller_id'],$lines))) as $sellerId){notify_user($sellerId,'new_order','Pesanan produk tokomu',"Pesanan #{$orderId} berisi barang dari tokomu.",'dashboard.php#pesanan');} flash("Pesanan #{$orderId} berhasil dibuat. Kamu dapat melihat statusnya di halaman pesanan."); redirect($marketplaceReady ? 'orders.php' : 'dashboard.php');
        } catch(Throwable $exception) {
            if($pdo->inTransaction()) $pdo->rollBack();
            flash($exception instanceof DomainException ? $exception->getMessage() : 'Pesanan belum tersimpan. Coba lagi.','error'); redirect('shop.php#cart');
        }
    }
redirect('shop.php#cart');
}
$products=$marketplaceReady ? $pdo->query("SELECT id,name,category,description,price,stock,emoji,color FROM products WHERE is_active=1 AND review_status='approved' ORDER BY id DESC")->fetchAll() : $pdo->query('SELECT id,name,category,description,price,stock,emoji,color FROM products WHERE is_active=1 ORDER BY id DESC')->fetchAll();
$cartProducts=[]; $cartTotal=0; $cartCount=0;
if($cart){$ids=array_map('intval',array_keys($cart));$marks=implode(',',array_fill(0,count($ids),'?'));$stmt=$pdo->prepare($marketplaceReady ? "SELECT * FROM products WHERE id IN ($marks) AND is_active=1 AND review_status=?" : "SELECT * FROM products WHERE id IN ($marks) AND is_active=1");$stmt->execute($marketplaceReady ? array_merge($ids,['approved']) : $ids);$cartProducts=$stmt->fetchAll();foreach($cartProducts as $item){$qty=(int)$cart[$item['id']];$cartTotal+=(int)$item['price']*$qty;$cartCount+=$qty;}}
layout_start('Toko online');
?>
<div class="announcement">✨ PROMO PEMBUKAAN · Gratis ongkir untuk belanja min. Rp100.000 <a href="#produk">Belanja sekarang →</a></div>
<?php show_flash(); ?>
<section class="hero" id="beranda"><div class="hero-copy"><span class="eyebrow"><i></i> TOKO PILIHAN HARI INI</span><h1>Hal kecil yang<br>buat hari <em>lebih baik.</em></h1><p>Temukan barang pilihan untuk menemani keseharianmu. Masuk sebagai pelanggan untuk memesan dan melacak belanjaan.</p><a class="button button-dark" href="#produk">Jelajahi produk <span>↗</span></a><div class="hero-proof"><span>Produk pilihan · Belanja mudah · Pesanan tercatat</span></div></div><div class="hero-art"><div class="sun"></div><div class="shape shape-one">👜</div><div class="shape shape-two">🥤</div><div class="shape shape-three">📒</div><div class="promo-sticker"><small>HEMAT</small><strong>20%</strong><small>PRODUK PILIHAN</small></div><div class="art-caption">Dibuat untuk hari-harimu <span>↗</span></div></div></section>
<section class="benefits"><div><span>♡</span><p><strong>Pilihan berkualitas</strong><small>Dikurasi dengan teliti</small></p></div><div><span>↗</span><p><strong>Belanja praktis</strong><small>Pesanan tersimpan aman</small></p></div><div><span>♧</span><p><strong>Ramah di kantong</strong><small>Harga jujur setiap hari</small></p></div></section>
<section class="products-section" id="produk"><div class="section-heading"><div><span class="eyebrow">PILIHAN UNTUKMU</span><h2>Produk favorit</h2></div><div class="shop-controls"><label class="search-box"><span>⌕</span><input type="search" id="productSearch" placeholder="Cari produk..." aria-label="Cari produk"></label><button class="cart-trigger" id="toggleCart" type="button" aria-label="Buka keranjang">♧ <span>Keranjang</span><b><?=$cartCount?></b></button></div></div><div class="category-filters"><button type="button" class="filter active" data-category="*">Semua produk</button><?php foreach(array_values(array_unique(array_column($products,'category'))) as $category): ?><button type="button" class="filter" data-category="<?=h($category)?>"><?=h($category)?></button><?php endforeach; ?></div><div class="product-grid" id="productGrid">
<?php foreach($products as $product): ?><article class="product-card" data-category="<?=h($product['category'])?>"><div class="product-image <?=h($product['color'])?>"><span class="product-emoji"><?=h($product['emoji'])?></span><?php if((int)$product['stock']<=5 && (int)$product['stock']>0): ?><span class="tag">Sisa <?= (int)$product['stock'] ?></span><?php elseif((int)$product['stock']<1): ?><span class="tag">Stok habis</span><?php endif; ?></div><div class="product-info"><span class="category"><?=h($product['category'])?></span><h3><?=h($product['name'])?></h3><p><?=h($product['description'])?></p><div class="product-bottom"><strong><?=rupiah((int)$product['price'])?></strong><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><button class="add-button" aria-label="Tambah ke keranjang" <?=(int)$product['stock']<1?'disabled':''?>>+</button></form></div></div></article><?php endforeach; ?>
<?php if(!$products): ?><p>Produk belum tersedia.</p><?php endif; ?></div><p id="noProducts" class="no-products" hidden>Produk tidak ditemukan. Coba kata kunci atau kategori lain.</p></section>
<section class="promo-banner"><div><span class="eyebrow">KABAR BAIK UNTUK KAMU</span><h2>Temukan pilihan<br><em>yang pas untukmu.</em></h2><p>Promo dan produk pilihan tersedia di toko hari ini.</p></div><div class="ticket">PILIHAN<small>HARI INI</small></div></section>
<section class="gif-promo"><div class="gif-copy"><span class="eyebrow">PROMOSI BERGERAK</span><h2>GIF promosi tokomu</h2><p>Simpan GIF sebagai <code>images/promo.gif</code>.</p></div><div class="gif-frame"><div class="gif-placeholder" id="gifPlaceholder"><span>✳</span><strong>Area GIF promosi</strong><small>Taruh file GIF di folder images</small></div><img id="promoGif" src="images/promo.gif" alt="GIF promosi" hidden></div></section>
<div class="cart-backdrop" id="cartBackdrop"></div><aside class="cart-drawer" id="cart" aria-label="Keranjang belanja"><div class="drawer-head"><div><span class="eyebrow">PILIHANMU</span><h2>Keranjang <span>(<?=$cartCount?>)</span></h2></div><button class="close-cart" id="closeCart">×</button></div><div class="cart-items">
<?php if(!$cartProducts): ?><div class="empty-cart"><span>🛍️</span><h3>Keranjangmu masih kosong</h3><p>Yuk, pilih barang favoritmu.</p></div><?php else: foreach($cartProducts as $item): ?><div class="cart-item"><div class="cart-thumb <?=h($item['color'])?>"><?=h($item['emoji'])?></div><div class="cart-details"><strong><?=h($item['name'])?></strong><small><?=rupiah((int)$item['price'])?></small><form method="post" class="quantity-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="quantity"><input type="hidden" name="product_id" value="<?=(int)$item['id']?>"><button name="quantity" value="<?=max(0,(int)$cart[$item['id']]-1)?>">−</button><span><?=(int)$cart[$item['id']]?></span><button name="quantity" value="<?=(int)$cart[$item['id']]+1?>">+</button></form></div><strong class="line-total"><?=rupiah((int)$item['price']*(int)$cart[$item['id']])?></strong></div><?php endforeach; endif; ?></div>
<div class="drawer-footer"><div class="subtotal"><span>Subtotal</span><strong><?=rupiah($cartTotal)?></strong></div><small>Masuk sebagai pelanggan untuk menyimpan pesanan ke database.</small><?php if(user() && user()['role']==='customer'): ?><form method="post" class="checkout-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="checkout"><label>Nama penerima<input name="customer_name" required value="<?=h(user()['name'])?>"></label><label>Nomor telepon<input name="phone" required value="<?=h(user()['phone']??'')?>"></label><label>Alamat pengiriman<textarea name="address" required placeholder="Alamat lengkap"></textarea></label><button class="button button-dark checkout-button" type="submit" <?=$cartCount<1?'disabled':''?>>Buat pesanan <span>→</span></button></form><?php elseif(!user()): ?><a class="button button-dark checkout-button" href="masuk.html">Masuk untuk checkout →</a><?php else: ?><p>Akun staf tidak dapat membuat pesanan pelanggan.</p><?php endif; ?></div></aside>
<script>
const cart=document.getElementById('cart'),backdrop=document.getElementById('cartBackdrop');
function openCart(){document.body.classList.add('cart-open');}function closeCart(){document.body.classList.remove('cart-open');}
document.getElementById('toggleCart').addEventListener('click',openCart);
document.querySelectorAll('.add-button').forEach(button=>button.form?.addEventListener('submit',()=>openCart()));
document.querySelectorAll('.quantity-form').forEach(form=>form.addEventListener('submit',()=>openCart()));
document.getElementById('closeCart').addEventListener('click',closeCart);backdrop.addEventListener('click',closeCart);document.addEventListener('keydown',e=>{if(e.key==='Escape')closeCart();});
if(location.hash==='#cart')openCart();
let activeCategory='*';const search=document.getElementById('productSearch');
function filterProducts(){const term=search.value.trim().toLocaleLowerCase('id');let visible=0;document.querySelectorAll('.product-card').forEach(card=>{const categoryMatches=activeCategory==='*'||card.dataset.category===activeCategory;const textMatches=card.textContent.toLocaleLowerCase('id').includes(term);card.hidden=!(categoryMatches&&textMatches);if(!card.hidden)visible++;});document.getElementById('noProducts').hidden=visible>0;}
search.addEventListener('input',filterProducts);document.querySelectorAll('.category-filters .filter').forEach(button=>button.addEventListener('click',()=>{activeCategory=button.dataset.category;document.querySelector('.category-filters .filter.active')?.classList.remove('active');button.classList.add('active');filterProducts();}));
const gif=document.getElementById('promoGif');gif.addEventListener('load',()=>{gif.hidden=false;document.getElementById('gifPlaceholder').hidden=true;});gif.addEventListener('error',()=>{gif.hidden=true;document.getElementById('gifPlaceholder').hidden=false;});
</script>
<?php layout_end(); ?>





