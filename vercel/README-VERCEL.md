# Deploy ShopPoliteknik ke Vercel dan TiDB Cloud

Panduan ini menyiapkan **salinan proyek ini** untuk deployment. Perubahan di komputer tidak otomatis masuk ke GitHub atau Vercel; deploy hanya setelah berkas yang disebut di bawah sudah masuk ke repository yang terhubung ke proyek Vercel.

## 1. Pastikan repository dan folder proyek benar

1. Repository Vercel harus berisi `vercel.json`, `index.html`, `auth.js`, `api/`, dan `includes/` pada folder proyek yang sama.
2. Di Vercel buka **Project → Settings → Build and Deployment**. Atur **Root Directory** ke folder yang berisi `vercel.json`. Jika file itu berada langsung di akar repository, gunakan `./`; jika ada di subfolder, pilih nama subfolder tersebut.
3. Gunakan preset **Other**. Jangan isi Output Directory dengan `api` atau `public`; halaman HTML/CSS/JS ada di akar proyek. Runtime PHP Vercel hanya didaftarkan untuk `api/*.php`; halaman PHP di akar dipetakan ke wrapper di `api/` oleh aturan route `vercel.json`.
4. Pastikan GitHub/Vercel memakai branch yang sama dengan branch tempat perubahan di-commit.

## 2. Isi Environment Variables Vercel

Buka **Project → Settings → Environment Variables → Add Environment Variable**. Tambahkan satu per satu dan centang **Production** (tambahkan Preview/Development juga bila akan menguji environment tersebut):

| Key | Value |
| --- | --- |
| `TIDB_HOST` | Host dari TiDB Connect, tanpa `mysql://` dan tanpa path database. Contoh: `gateway01.ap-southeast-1.prod.aws.tidbcloud.com` |
| `TIDB_PORT` | Port dari TiDB Connect, biasanya `4000` |
| `TIDB_USER` | Username persis dari TiDB Connect |
| `TIDB_PASSWORD` | Password database yang sebenarnya; bukan teks `<PASSWORD>` |
| `TIDB_DATABASE` | `toko_online` |

Jangan tempel URI database lengkap ke satu kolom. Jika URI TiDB berakhir dengan `/sys`, jangan gunakan `sys` untuk aplikasi ini: aplikasi memilih database `toko_online`, sesuai skema marketplace. Pastikan user TiDB tersebut memiliki akses ke database itu. Simpan `TIDB_PASSWORD` sebagai **Secret**. Jangan unggah `.env` atau kredensial ke GitHub.

Runtime memakai PDO MySQL dan TLS untuk koneksi remote. Tidak diperlukan alamat `localhost`, user `root` lokal, XAMPP, atau MySQL lokal pada Vercel. XAMPP hanya untuk menjalankan salinan di komputer.

## 3. Siapkan skema TiDB satu kali

1. Buka TiDB Cloud **SQL Editor** dan pilih database `toko_online` pada pemilih database.
2. Jalankan isi file `database_vercel.sql` dari salinan proyek ini. File tersebut tidak disajikan publik pada deployment.
3. Jika editor menolak perintah `CREATE DATABASE` atau `USE`, hapus hanya dua baris pembuka itu, pastikan pemilih database tetap `toko_online`, lalu jalankan ulang.
4. `CREATE TABLE IF NOT EXISTS` yang menemukan tabel lama bisa menampilkan warning `1050 ... already exists`. Itu berarti tabelnya sudah ada; warning bukan bukti semua kolom/tabel sudah cocok. Skrip dibuat agar pembuatan tabel dan data contoh aman diulang, tetapi tidak mengubah struktur tabel lama yang sudah ada.
5. Jangan hapus database/tabel produksi untuk menghilangkan warning. Bila ada kolom yang kurang, gunakan migrasi yang sesuai dan backup terlebih dulu.

## 4. Commit, push, dan deploy

1. Masukkan perubahan dari folder proyek ini ke repository GitHub yang dipakai proyek Vercel. Pastikan setidaknya berkas berikut ikut:
   - `vercel.json` dan `.vercelignore`
   - semua berkas PHP di `api/` (termasuk wrapper halaman seperti `api/dashboard.php`)
   - `includes/bootstrap.php`
   - `auth.js`
   - file halaman, CSS, JavaScript, dan gambar yang digunakan aplikasi
2. Commit dan push ke branch yang terhubung ke Vercel.
3. Jika variabel environment baru saja disimpan, buka **Deployments** dan redeploy commit terbaru agar Function mendapatkan nilai variabel terbaru.
4. Buka **Build Logs**. Pastikan deployment berstatus **Ready** tanpa error saat memasang `vercel-php@0.9.0`.

## 5. Periksa endpoint sebelum mencoba login

Ganti domain contoh dengan domain deployment Production Anda:

1. Buka `https://DOMAIN-ANDA/api/health.php`. Hasil normal:

   ```json
   {"ok":true,"php":"...","database":"connected","schema":"ready","message":"Runtime PHP Vercel dan koneksi database berjalan."}
   ```

2. Buka `https://DOMAIN-ANDA/api/auth.php`. Hasil normal adalah JSON berisi `csrf`, `ownerExists`, `signedIn`, dan `terms`.
3. Baru setelah dua URL tersebut menghasilkan JSON, buka `https://DOMAIN-ANDA/`, buat owner pertama, lalu uji pendaftaran/login pelanggan dan penjual.

## Jika masih gagal

| Hasil | Makna dan langkah berikutnya |
| --- | --- |
| Endpoint health tampil sebagai HTML/404 atau source PHP, status 200/404 | Runtime Function belum terpasang pada deployment yang dibuka. Pastikan `vercel.json` berada di Root Directory, commit-nya benar, lalu lihat Build Logs. Ini bukan masalah yang bisa didiagnosis dari log XAMPP. |
| JSON status 503 menyebut konfigurasi TiDB | Isi/centang variabel untuk environment yang sedang diuji, terutama Production, lalu redeploy. Jangan kirim password ke chat. |
| JSON status 503 menyebut tabel `app_sessions` | Jalankan skema di database TiDB `toko_online` yang sama dengan `TIDB_DATABASE`. |
| Health `database: connected` tetapi `schema: incomplete` | Jalankan/benahi tabel yang disebut di TiDB; jangan menghapus tabel yang sudah berisi data. |
| Health normal, tetapi auth menghasilkan JSON 500 | Buka **Deployments → deployment aktif → Runtime Logs** untuk stack trace PHP. Periksa apakah struktur tabel cocok dengan `database_vercel.sql`. |
| Health dan auth normal, namun form gagal | Periksa Console browser dan respons Network untuk `api/auth.php`; server sekarang seharusnya memberi pesan JSON yang bisa dibaca. |

HTTP `200` sendiri hanya menyatakan permintaan berhasil dijawab. Jika responsnya HTML, itu tetap bukan JSON dan JavaScript form akan menolaknya. **Vercel Runtime Logs** dipakai untuk PHP yang berjalan di Vercel; **XAMPP logs** hanya untuk versi lokal.

## Catatan deployment

- PHP dijalankan sebagai Vercel Functions lewat runtime komunitas `vercel-php`; dokumentasi Vercel mencantumkan PHP melalui runtime komunitas, dan dokumentasi runtime tersebut meminta pola fungsi PHP dinyatakan di `vercel.json`.
- PHP Function bersifat serverless; sesi login disimpan di tabel TiDB `app_sessions`, bukan filesystem Function.
- Vercel memberi HTTPS pada deployment. Aturan `.htaccess` untuk Apache lokal/hosting biasa tidak mengatur routing di Vercel.
- Jangan bagikan tangkapan layar Environment Variables yang menampilkan password atau kredensial database.
