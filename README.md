# Sistem Absensi PT Jafran Indonesia

Website absensi berbasis PHP native + MySQL, tampilan pakai template **Meridian**
(dari Stisla, lisensi MIT — lihat `assets/NOTICE-ICON-LICENSE.txt` untuk kredit ikon).

## Fitur

- Login admin (1 role saja: Admin/HR)
- Dashboard ringkasan absensi hari ini
- CRUD Karyawan (NIK, nama, departemen, jabatan, shift, ID mesin fingerprint)
- CRUD Departemen
- CRUD Shift Kerja (jam masuk/pulang, toleransi telat, hari kerja, penanda lembur)
- Log Absensi dengan filter tanggal/departemen/status + input manual
- Laporan rekap bulanan + export CSV
- Ganti profil & password admin

**Catatan:** integrasi otomatis ke mesin fingerprint REVO WF-206BNC BELUM disambungkan
di versi ini. Tabel `attendance_raw_logs` sudah disiapkan untuk menampung data mentah
dari mesin nantinya. Sementara ini, data absensi bisa diisi manual lewat menu **Log Absensi**.

## Instalasi (Windows + XAMPP)

1. **Install XAMPP** kalau belum ada, aktifkan Apache dan MySQL dari XAMPP Control Panel.
2. **Copy folder ini** ke `C:\xampp\htdocs\absensi-jafran` (atau nama folder lain sesukamu).
3. **Buat database:**
   - Buka `http://localhost/phpmyadmin`
   - Klik menu Import, pilih file `database/schema.sql`, klik Go.
   - Ini akan otomatis membuat database `absensi_jafran` beserta tabel dan data awal.
4. **Cek konfigurasi koneksi** di `config/database.php` — kalau MySQL XAMPP kamu default
   (user `root`, tanpa password), tidak perlu diubah apa-apa.
5. **Akses websitenya** lewat browser: `http://localhost/absensi-jafran/login.php`

## Login pertama kali

- Username: `admin`
- Password: `admin123`

**⚠️ PENTING:** segera ganti password ini lewat menu **Pengaturan** setelah login pertama.

## Supaya bisa diakses dari HP/PC lain di jaringan WiFi kantor

1. Cari IP address komputer server (jalankan `ipconfig` di Command Prompt Windows, lihat "IPv4 Address").
2. Dari perangkat lain yang terhubung ke WiFi yang sama, buka browser lalu akses:
   `http://<IP-server>/absensi-jafran/login.php` (ganti `<IP-server>` dengan IP tadi).
3. Kalau tidak bisa diakses, cek Windows Firewall — pastikan port 80 (Apache) diizinkan
   untuk koneksi masuk dari jaringan lokal.
4. Disarankan set IP address komputer server jadi **statis** (tidak berubah-ubah), supaya
   alamat aksesnya tidak berubah setiap kali komputer restart.

## Struktur folder

```
absensi-jafran/
├── assets/            (CSS, JS dari template)
├── config/
│   └── database.php   (koneksi database — edit di sini kalau perlu)
├── database/
│   └── schema.sql      (import ini ke phpMyAdmin)
├── includes/           (bagian layout & helper, jangan diakses langsung)
├── index.php           (dashboard)
├── login.php / logout.php
├── karyawan.php
├── departemen.php
├── shift.php
├── absensi.php
├── laporan.php
└── pengaturan.php
```

## Langkah selanjutnya (belum dikerjakan di versi ini)

1. **Tes konektivitas mesin fingerprint** — coba library gratis (`zkteco-php` atau sejenis)
   untuk connect ke IP mesin REVO WF-206BNC di port 4370, seperti yang sudah didiskusikan.
2. **Buat script sinkronisasi** yang mengambil data dari mesin lalu menyimpannya ke tabel
   `attendance_raw_logs`, lalu memindahkannya ke `attendance_daily` (mencocokkan dengan shift
   karyawan untuk menentukan status hadir/telat/alpha — logika ini sudah ada di
   `includes/functions.php` fungsi `hitung_status()`, tinggal dipanggil dari script sync).
3. Pertimbangkan menjalankan script sync itu terus-menerus (loop) atau terjadwal
   (Windows Task Scheduler tiap beberapa menit).
