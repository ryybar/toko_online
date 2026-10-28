<?php
require __DIR__ . '/includes/bootstrap.php';
$current=require_role('seller');
require_marketplace_schema();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Metode tidak didukung.');}
verify_csrf();
$name=trim($_POST['name']??''); $category=trim($_POST['category']??''); $description=trim($_POST['description']??'');
$price=filter_var($_POST['price']??null,FILTER_VALIDATE_INT); $stock=filter_var($_POST['stock']??null,FILTER_VALIDATE_INT);
$weight=filter_var($_POST['weight_grams']??0,FILTER_VALIDATE_INT); $days=filter_var($_POST['shipping_days']??3,FILTER_VALIDATE_INT);
$condition=$_POST['product_condition']??'baru'; $image=trim($_POST['image_url']??'');
$brand=trim($_POST['brand']??'');$sku=trim($_POST['sku']??'');$material=trim($_POST['material']??'');$variant=trim($_POST['color_variant']??'');$dimensions=trim($_POST['dimensions']??'');
if($name===''||$category===''||$description===''||$price===false||$price<1||$stock===false||$stock<0||$weight===false||$weight<0||$days===false||$days<1||$days>60||!in_array($condition,['baru','preloved'],true)||($image!==''&&!filter_var($image,FILTER_VALIDATE_URL))){
 flash('Lengkapi nama, kategori, deskripsi, harga, stok, berat, kondisi, dan estimasi kirim dengan benar. URL gambar harus valid.','error');redirect('dashboard.php#produk-baru');
}
$stmt=$pdo->prepare("INSERT INTO products(seller_id,name,category,description,brand,sku,material,color_variant,dimensions,weight_grams,product_condition,shipping_days,image_url,price,stock,review_status,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',0)");
$stmt->execute([(int)$current['id'],$name,$category,$description,$brand?:null,$sku?:null,$material?:null,$variant?:null,$dimensions?:null,$weight,$condition,$days,$image?:null,$price,$stock]);
$productId=(int)$pdo->lastInsertId();
notify_role('owner','product_review','Produk menunggu pemeriksaan',"{$current['name']} mengajukan {$name} (ID {$productId}). Periksa informasi dan kelayakannya.",'dashboard.php#produk-menunggu');
flash('Produk berhasil dikirim untuk ditinjau owner. Produk akan tampil di toko setelah disetujui.');
redirect('dashboard.php#produk-baru');
?>

