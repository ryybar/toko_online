# ShopPoliteknik — Toko online PHP dan MySQL

## Menjalankan di komputer dengan XAMPP

1. Salin folder proyek ke `C:\XAMPP02\htdocs\ShopPoliteknik`. Jangan simpan kode website di dalam `mysql\data`; folder itu hanya untuk berkas internal database dan tidak dilayani Apache.
2. Dari XAMPP Control Panel, nyalakan Apache dan MySQL.
3. Buka `http://localhost/phpmyadmin`, pilih **Import**, lalu impor `database.sql` jika database belum tersedia.
4. Buka `http://localhost/ShopPoliteknik`. `index.html` adalah halaman awal dengan pop-up formulir dan tautan ke `masuk.html` / `daftar.html`. `auth.js` menggerakkan interaksi; `api/auth.php` memeriksa akun dan menyimpannya ke database. Pelanggan masuk ke etalase/keranjang di `shop.php`; owner dan penjual masuk ke dashboard berbeda.
5. Daftarkan owner pertama melalui pilihan **Owner pertama** di halaman Buat akun. Setelah satu owner dibuat, pilihan pendaftaran owner ditutup otomatis. Penjual mengajukan pendaftaran untuk ditinjau owner; pelanggan dan penjual memakai formulir sesuai jenis akunnya.

Konfigurasi koneksi database berada di `includes/bootstrap.php` dan memakai database `toko_online`, pengguna `root`, kata sandi kosong. Database lokal memiliki 24 produk contoh saat ini; tabel `users` masih kosong sampai akun didaftarkan lewat website. Akun baru ditampilkan di dashboard owner. Pastikan phpMyAdmin dan konfigurasi PHP menunjuk database yang sama (`toko_online`). Di hosting, ganti semua kredensial sesuai panel.

## Fitur

- **Owner:** melihat penjualan, jumlah akun dan pengguna online (aktif 5 menit terakhir), grafik penjualan, pesanan, pendaftar, serta akun; membuat penjual dan menetapkan produk.
- **Penjual:** dapat mendaftar dengan profil usaha, produk, berat, perkiraan keuntungan, dan pilihan paket. Owner menyetujui permohonan sebelum login aktif. Dashboard penjual menampilkan grafik penjualan dan stok, pesanan, serta profil pendaftaran.
- **Paket:** pilihan uji coba atau bulanan dicatat sebagai permohonan; owner mengonfirmasi tarif dan pembayaran. Sistem belum memotong biaya otomatis.
- **Pelanggan:** mencari dan memfilter produk, menggunakan keranjang, checkout, dan melacak pesanan.
- **Promosi:** letakkan GIF milikmu di `images/promo.gif`.

## Deploy dengan Vercel dan TiDB Cloud

Untuk deployment serverless, ikuti langkah pada `README-VERCEL.md`. Vercel menjalankan PHP aplikasi, sedangkan data dan sesi login berada di TiDB Cloud. XAMPP/phpMyAdmin hanya digunakan untuk salinan lokal dan bukan bagian dari server Vercel.

## Hosting publik dan SSL

Aplikasi membutuhkan hosting yang mendukung PHP dan MySQL. Contohnya, paket gratis InfinityFree menyediakan PHP, MySQL, dan SSL; batas layanan gratis dapat berubah. Buat akun hosting, tambahkan domain atau subdomain, buat database MySQL, lalu impor `database.sql` lewat phpMyAdmin hosting. Unggah file proyek ke direktori web hosting dan sesuaikan kredensial database di `includes/bootstrap.php`.

Aktifkan atau pasang sertifikat SSL dari panel hosting terlebih dahulu. File `.htaccess` mengalihkan pengunjung hosting ke HTTPS, sedangkan alamat `localhost` tetap menggunakan HTTP. Cookie sesi otomatis memakai atribut `Secure` saat koneksi HTTPS aktif. Uji URL HTTPS sebelum membagikannya. Jangan gunakan data pribadi atau kata sandi sungguhan untuk demo sekolah.

Setelah URL HTTPS aktif, buka halaman awal dan pilih **Buat akun → Owner pertama** untuk membuat owner. Halaman dan formulir antarmuka berupa HTML/CSS/JavaScript; PHP pada `api/auth.php` berjalan di server untuk memproses login dan menyimpan akun. Pengunjung tidak mengunduh atau memasang PHP. Halaman toko interaktif berada di `shop.php` setelah login. Jangan membuka file dengan klik dua kali; akses melalui XAMPP atau alamat hosting.

## Update marketplace multi-penjual (database yang sudah berisi data)

Untuk database `toko_online` yang sudah dipakai, jangan impor ulang `database.sql` karena file itu untuk instalasi baru. Buka phpMyAdmin → pilih `toko_online` → **Import** → jalankan `migrations/001_marketplace_multi_seller.sql` lalu `migrations/002_single_owner.sql`. Migrasi menambah tabel marketplace dan batas satu owner. Tabel lama/data tetap dipertahankan. Backup database sebelum migrasi.

Daftar owner pertama melalui **Buat akun → Owner pertama**. Setelah akun itu dibuat, pilihan pendaftaran owner otomatis disembunyikan; API dan indeks unik database juga menolak owner kedua. Akun owner tetap dapat masuk melalui pilihan **Owner** pada halaman login. Untuk database lama, impor `migrations/002_single_owner.sql` setelah `001_marketplace_multi_seller.sql`. Jika sudah ada lebih dari satu owner, migrasi indeks akan berhenti tanpa menghapus data; tinjau akun duplikat terlebih dahulu. Pendaftar penjual menunggu persetujuan owner sebelum bisa masuk.

Checkout saat ini memakai COD dan memecah pesanan menjadi subpesanan tiap toko, dengan stok dan status tercatat. Toko yang bisa dibeli adalah produk milik akun penjual aktif. Sebagian produk contoh lama yang tidak memiliki `seller_id` tetap ada di database, tetapi tidak muncul di katalog publik; tautkan hanya setelah ditinjau owner.

## Catatan lokasi dan batasan verifikasi

Folder yang diperbarui berada di `C:\Users\INFINIX\Documents\Codex\2026-10-16\se\ShopPoliteknik0`. Database harus tersedia di MySQL XAMPP dan skema owner tunggal perlu diterapkan menggunakan migration. Server MySQL tidak tersedia untuk menerapkan migrasi langsung; lakukan migrasi dan uji alur akun/pesanan setelah MySQL pulih.
