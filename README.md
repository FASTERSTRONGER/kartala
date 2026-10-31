# Kartala - Task & Schedule Manager

Kartala adalah aplikasi web manajemen jadwal dan tenggat waktu berbasis **PHP Native** dan **SQLite**. Aplikasi ini dirancang ringan, cepat, dan tanpa dependensi rumit, memudahkan pelacakan tugas harian, prioritas tingkat kesulitan, serta visualisasi tenggat waktu dalam kalender bulanan interaktif.

---

## Fitur Utama

- **Visual Kalender Bulanan**: Tampilan kalender interaktif untuk memantau beban tugas dan tenggat waktu harian secara visual (`kalender.php`).
- **Daftar Tugas & Filter Prioritas**: Manajemen tugas dengan penanda tingkat kesulitan (*Rendah*, *Sedang*, *Tinggi*) dan tenggat waktu (`index.php`).
- **Zero Configuration Database**: Menggunakan SQLite yang otomatis membuat skema tabel saat aplikasi pertama kali dijalankan tanpa perlu setup DBMS eksternal.
- **Autentikasi Sederhana**: Proteksi akses menggunakan sesi login admin.
- **Tampilan Bersih & Responsif**: Antarmuka ramah pengguna yang nyaman diakses melalui perangkat desktop maupun mobile.

---

## Struktur Berkas

```text
kartala/
├── index.php          # Halaman utama manajemen tugas, formulir input, dan autentikasi
├── kalender.php       # Tampilan visual kalender bulanan dan integrasi jadwal
├── .env.example       # Template konfigurasi kredensial login
├── jadwal.sqlite      # Basis data SQLite lokal (dikecualikan dari pelacakan Git)
├── .gitignore         # Konfigurasi pengabaian berkas sementara, database, dan catatan lokal
└── README.md          # Dokumentasi proyek
```

---

## Persyaratan Sistem

- **PHP**: Versi 7.4 atau lebih baru (direkomendasikan PHP 8.x).
- **Ekstensi PHP**: `pdo_sqlite` aktif di `php.ini`.
- **Web Server**: Apache, Nginx, Laragon, atau PHP Built-in CLI Server.

---

## Panduan Menjalankan Proyek

### 0. Konfigurasi Environment
Salin `.env.example` menjadi `.env`, lalu sesuaikan kredensial login:
```bash
cp .env.example .env
```

### 1. Menggunakan PHP Built-in Server
Jalankan perintah berikut di direktori proyek:
```bash
php -S localhost:8000
```
Buka peramban di `http://localhost:8000`.

### 2. Menggunakan Web Server Lokal (Apache / Nginx / Laragon)
1. Letakkan folder proyek ke dalam direktori document root web server (misal: `www/kartala` atau `htdocs/kartala`).
2. Akses melalui localhost atau virtual host lokal yang telah dikonfigurasi (misal: `http://localhost/kartala` atau `http://kartala.test`).

---

## Skema Database

Tabel utama `jadwal` dibuat secara otomatis jika berkas basis data belum tersedia:

```sql
CREATE TABLE IF NOT EXISTS jadwal (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    judul TEXT NOT NULL,
    deskripsi TEXT,
    tenggat_waktu DATE,
    tingkat_kesulitan TEXT,
    dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## Lisensi & Pemeliharaan

Dikembangkan dan dipelihara oleh [FASTERSTRONGER](https://github.com/FASTERSTRONGER).
