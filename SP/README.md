# ShopPoliteknik — Toko online PHP dan MySQL

## Menjalankan di komputer dengan XAMPP

1. Salin folder proyek ke `C:\XAMPP02\htdocs\SP`. Jangan simpan kode website di dalam `mysql\data`; folder itu hanya untuk berkas internal database dan tidak dilayani Apache.
2. Dari XAMPP Control Panel, nyalakan Apache dan MySQL.
3. Buka `http://localhost/phpmyadmin`, pilih **Import**, lalu impor `database.sql` jika database belum tersedia.
4. Buka `http://localhost/SP`. `index.html` adalah halaman awal dengan pop-up formulir dan tautan ke `masuk.html` / `daftar.html`. `auth.js` menggerakkan interaksi; `api/auth.php` memeriksa akun dan menyimpannya ke database. Pelanggan masuk ke etalase/keranjang di `shop.php`; owner dan penjual masuk ke dashboard berbeda.
5. Owner dapat membuat akun penjual dan menambahkan produk. Pelanggan dapat mendaftar sendiri, masuk, berbelanja, dan melihat status pesanan.

Konfigurasi koneksi database berada di `includes/bootstrap.php` dan memakai database `toko_online`, pengguna `root`, kata sandi kosong. Database lokal memiliki 24 produk contoh saat ini; tabel `users` masih kosong sampai akun didaftarkan lewat website. Akun baru ditampilkan di dashboard owner. Pastikan phpMyAdmin dan konfigurasi PHP menunjuk database yang sama (`toko_online`). Di hosting, ganti semua kredensial sesuai panel.

## Fitur

- **Owner:** melihat penjualan, jumlah akun dan pengguna online (aktif 5 menit terakhir), grafik penjualan, pesanan, pendaftar, serta akun; membuat penjual dan menetapkan produk.
- **Penjual:** dapat mendaftar dengan profil usaha, produk, berat, perkiraan keuntungan, dan pilihan paket. Owner menyetujui permohonan sebelum login aktif. Dashboard penjual menampilkan grafik penjualan dan stok, pesanan, serta profil pendaftaran.
- **Paket:** pilihan uji coba atau bulanan dicatat sebagai permohonan; owner mengonfirmasi tarif dan pembayaran. Sistem belum memotong biaya otomatis.
- **Pelanggan:** mencari dan memfilter produk, menggunakan keranjang, checkout, dan melacak pesanan.
- **Promosi:** letakkan GIF milikmu di `images/promo.gif`.

## Hosting publik dan SSL

Aplikasi membutuhkan hosting yang mendukung PHP dan MySQL. Contohnya, paket gratis InfinityFree menyediakan PHP, MySQL, dan SSL; batas layanan gratis dapat berubah. Buat akun hosting, tambahkan domain atau subdomain, buat database MySQL, lalu impor `database.sql` lewat phpMyAdmin hosting. Unggah file proyek ke direktori web hosting dan sesuaikan kredensial database di `includes/bootstrap.php`.

Aktifkan atau pasang sertifikat SSL dari panel hosting terlebih dahulu. File `.htaccess` mengalihkan pengunjung hosting ke HTTPS, sedangkan alamat `localhost` tetap menggunakan HTTP. Cookie sesi otomatis memakai atribut `Secure` saat koneksi HTTPS aktif. Uji URL HTTPS sebelum membagikannya. Jangan gunakan data pribadi atau kata sandi sungguhan untuk demo sekolah.

Setelah URL HTTPS aktif, buka halaman awal dan pilih **Owner → Formulir owner pertama** untuk membuat owner. Halaman dan formulir antarmuka berupa HTML/CSS/JavaScript; PHP pada `api/auth.php` berjalan di server untuk memproses login dan menyimpan akun. Pengunjung tidak mengunduh atau memasang PHP. Halaman toko interaktif berada di `shop.php` setelah login. Jangan membuka file dengan klik dua kali; akses melalui XAMPP atau alamat hosting.


## Alur produk, pesanan, dan notifikasi

- Jalankan migrasi migrations/003_marketplace_workflow.sql melalui phpMyAdmin pada database toko_online setelah membuat ekspor cadangan. Migrasi menambahkan kolom barang, status per item, notifikasi, dan pembatas satu owner tanpa menghapus transaksi lama. Jika sudah ada lebih dari satu akun owner, indeks unik sengaja gagal; rapikan akun owner secara manual setelah memastikan akun yang benar.
- Penjual membuka seller_products.php, mengisi nama/kategori, deskripsi, harga, stok, berat, kondisi, estimasi pengiriman, serta detail opsional seperti merek, SKU, bahan, warna, dimensi, dan URL foto. Produk baru berstatus pending dan tidak tampil ke pelanggan.
- Owner membuka owner_products.php untuk melihat pengajuan lengkap, menyetujui agar tampil, atau menolak dengan catatan. Keputusan dikirim sebagai notifikasi ke penjual.
- orders.php menampilkan setiap barang terpisah: owner melihat pembeli dan toko asal, penjual hanya barang tokonya, pelanggan hanya pesanan sendiri. Status barang: baru, diproses, dikirim, selesai, atau dibatalkan.
- Ikon Notifikasi mengarah ke inbox per akun. Pesanan baru memberi kabar ke owner, penjual terkait, dan pembeli; perubahan status memberi kabar ke pembeli serta owner/penjual yang terkait.
- database.sql dipakai untuk instalasi database baru. Untuk database lama yang sudah berisi akun/pesanan, impor migrasi saja; jangan impor ulang berkas pembuatan database di atas data berjalan.

