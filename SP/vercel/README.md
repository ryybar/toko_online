# ShopPoliteknik untuk Vercel

Paket ini terpisah dari proyek XAMPP utama. Halaman awal berada di `beranda.php`; tidak ada `index.php` atau `index.html`. Vercel mengarahkan `/` ke halaman itu. Router PHP tunggal meneruskan halaman dan aksi PHP agar deployment tidak membuat satu Function untuk setiap berkas.

## Database

Vercel tidak menyediakan database SQL pertamanya sendiri. Paket ini memakai TiDB Cloud Serverless dari Vercel Marketplace, yang menyediakan protokol MySQL-compatible, sehingga koneksi PDO dan SQL MySQL proyek tetap bisa digunakan. Integrasi TiDB memiliki opsi klaster gratis yang ketentuan dan batasnya mengikuti penyedia. Lihat [TiDB di Vercel Marketplace](https://vercel.com/marketplace/tidb-cloud).

1. Buat atau sambungkan klaster TiDB Cloud dari Marketplace di proyek Vercel.
2. Di TiDB, buat/pilih database bernama `toko_online` (atau samakan nilai variabel lingkungan).
3. Jalankan isi `database.vercel.sql` sekali pada database kosong. Berkas ini membuat tabel aplikasi dan tabel sesi persisten. Jangan jalankan ulang di atas database berisi data tanpa backup.
4. Pada **Vercel → Project → Settings → Environment Variables**, pastikan tersedia salah satu set berikut:
   - `TIDB_HOST`, `TIDB_PORT`, `TIDB_USER`, `TIDB_PASSWORD`, `TIDB_DATABASE` (nama yang diberikan integrasi Marketplace), atau
   - `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`.
5. Untuk TiDB Cloud Starter, koneksi publik mewajibkan TLS. Kode memakai bundle CA sistem bila tersedia. Jika runtime memerlukan CA khusus, tambahkan `DB_SSL_CA` yang menunjuk ke file CA yang tersedia di Function. Jangan menaruh kata sandi di berkas PHP atau Git.

Database lokal `toko_online` di XAMPP tetap terpisah. Berkas ini tidak menyalin data lokal secara otomatis. Sebelum memindahkan akun/transaksi, ekspor dan simpan cadangan, lalu rencanakan impor data ke TiDB dan pastikan skema cocok.

## Deploy

1. Unggah proyek ke repository Git yang terhubung dengan Vercel.
2. Buat proyek Vercel dari repository tersebut, lalu set **Root Directory** ke folder `vercel`.
3. Biarkan **Build Command** dan **Output Directory** kosong/default. Runtime PHP komunitas dikonfigurasi di `vercel.json`.
4. Tambahkan environment variable database untuk Production, Preview, dan Development sesuai kebutuhan, lalu deploy.
5. Buka domain Vercel. Buat akun owner pertama melalui **Buat akun → Owner pertama**, kemudian daftarkan penjual dan pelanggan.

## Struktur

- `beranda.php`: halaman depan dan formulir interaktif.
- `api/router.php`: satu entrypoint PHP untuk route halaman dan endpoint akun.
- `includes/bootstrap.php`: PDO melalui environment variables, cookie aman, dan sesi yang disimpan di database agar login/keranjang tidak hilang antar Function.
- `database.vercel.sql`: skema baru untuk TiDB/MySQL-compatible.
- `frontend.css`, `style.css`, `auth.js`, `dashboard.js`, `presence.js`: aset antarmuka.

Gambar promosi statis dapat diletakkan di `images/`. Berkas unggahan baru tidak boleh disimpan permanen ke filesystem Function Vercel karena filesystem Function bersifat sementara/read-only; gunakan URL gambar eksternal atau object storage.