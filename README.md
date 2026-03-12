# Sistem Monitoring Indikator Kinerja — Ditjen Migas

## Teknologi
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Bootstrap 5.3
- XAMPP

---

## Instalasi

### 1. Copy project ke htdocs
```
C:\xampp\htdocs\migas-kinerja\
```

### 2. Import database
Buka phpMyAdmin → Import → pilih file `database.sql`

Atau via command line:
```bash
mysql -u root -p < database.sql
```

### 3. Konfigurasi koneksi
Edit file `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // sesuaikan password MySQL kamu
define('DB_NAME', 'db_kinerja_migas');
```

### 4. Akses sistem
Buka browser: `http://localhost/migas-kinerja`

---

## Akun Default

| Username | Password   | Role  | Kelompok |
|----------|------------|-------|----------|
| admin    | password   | Admin | —        |
| dmee     | password   | User  | DMEE     |
| dmen     | password   | User  | DMEN     |
| dmo      | password   | User  | DMO      |

> **Ganti password** segera setelah login pertama!

Password di-hash dengan bcrypt. Untuk generate hash baru:
```php
echo password_hash('passwordbaru', PASSWORD_BCRYPT);
```

---

## Struktur Folder

```
migas-kinerja/
├── config/
│   ├── database.php       ← koneksi DB
│   └── session.php        ← auth helpers, fungsi utilitas
├── includes/
│   ├── header.php         ← HTML head + CSS global
│   ├── sidebar.php        ← navigasi sidebar
│   └── footer.php         ← JS global + closing tags
├── admin/
│   ├── dashboard.php      ← dashboard admin
│   ├── indikator.php      ← tabel semua indikator + filter
│   ├── edit_realisasi.php ← form edit realisasi (admin)
│   ├── monitoring.php     ← rekap capaian per kelompok
│   ├── export.php         ← download laporan Excel (.xls)
│   ├── import.php         ← upload CSV indikator
│   ├── users.php          ← manajemen pengguna
│   ├── kelompok.php       ← manajemen kelompok
│   ├── periode.php        ← buka/tutup periode TW
│   └── template_import.csv
├── user/
│   ├── dashboard.php      ← dashboard user
│   ├── indikator.php      ← daftar indikator milik user
│   └── realisasi.php      ← form input realisasi TW
├── index.php              ← halaman login
├── logout.php             ← logout
└── database.sql           ← schema + data awal
```

---

## Logika Akses PIC

| Panjang Kode | Query            | Contoh                        |
|-------------|------------------|-------------------------------|
| 4+ huruf    | `pic = 'DMEE'`   | DMEE hanya lihat PIC=DMEE     |
| 3 huruf     | `pic LIKE 'DME%'`| DME lihat DMEE, DMEN, DMEP…  |
| 2 huruf     | `pic LIKE 'DM%'` | DM lihat semua sub-DM         |

---

## Import Indikator (CSV)

Format CSV 4 kolom:
```
nama_indikator,leveling,satuan,pic
Fasilitasi Seismik 2D,IKSK-3,Rekomendasi,DMEE
Laporan Evaluasi Seismik 2D,IKSK-4,Laporan,DMEE
```

Upload via **Admin → Import Indikator**

---

## Export Excel

Admin → Export Excel (download semua atau filter per kelompok)

Format file: `.xls` (HTML-based, bisa dibuka Excel/LibreOffice)

---

## Fitur Utama

- ✅ Login multi-role (admin & user kelompok)
- ✅ Filter PIC otomatis berdasarkan panjang kode kelompok
- ✅ Input realisasi TW I–IV dengan validasi periode buka/tutup
- ✅ Total realisasi dihitung otomatis di database (generated column)
- ✅ Dashboard admin: rekap semua kelompok + progress bar
- ✅ Dashboard user: progress pengisian + quick access
- ✅ Export laporan ke Excel
- ✅ Import indikator dari CSV
- ✅ Manajemen user, kelompok, periode TW
- ✅ UI responsive dengan tema Kuning–Hitam–Putih Ditjen Migas
