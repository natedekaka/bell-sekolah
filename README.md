# 🔔 Bell Sekolah

Sistem bel sekolah otomatis berbasis web (kiosk mode) — pengganti bel manual,
dengan fitur setara aplikasi *Jabat Automatic School Bell*.

## Fitur

- **Manajemen Jadwal Bel** — CRUD jadwal tanpa batas (per hari Senin–Sabtu), multi-bell di jam sama,
  tipe (Masuk / Ganti Jam / Istirahat / Sholat / Pulang / Reses / Kustom), aktif/nonaktif per jadwal.
- **Kategori Jadwal** — Reguler, Ulangan/Ujian, Ramadhan, dll. Ganti kategori tanpa menghapus jadwal lama.
- **Layar Pemutar (Player / Kiosk)** — jam digital besar, jadwal hari ini, countdown bel berikutnya,
  notifikasi overlay saat bunyi, auto-play audio presisi (sinkron waktu server).
- **Bel Manual & Darurat** — tombol dari layar pemutar & admin; darurat memakai suara khusus & tak terblokir.
- **Kelola Suara** — upload MP3/WAV/OGG, rekam langsung via mikrofon browser, set default, pratinjau.
- **Hari Libur** — tanggal libur → bel dimatikan otomatis atau memakai kategori pengganti.
- **Backup & Restore** — backup manual (download JSON), restore, backup otomatis terjadwal (harian/mingguan/bulanan).
- **Keamanan** — login admin/operator, proteksi CSRF, password tersimpan `password_hash`, kunci pengaturan opsional.
- **Auto-shutdown** — opsi pengaturan jam auto mati (mode kiosk) + konfirmasi layar.

## Stack

PHP 8.2 (native) + MariaDB 10.11 + Tailwind CSS v4 + Docker Compose.

## Cara Menjalankan

```bash
docker compose up -d
```

| Layanan | URL |
|---|---|
| Aplikasi | http://localhost:9310 |
| phpMyAdmin | http://localhost:9311 |
| DB (host) | localhost:3311 |

Login bawaan: **admin / admin123** (role admin) · **operator / admin123** (role operator).
Ubah password setelah pertama login.

Layar pemutar: http://localhost:9310/player.php (buka di monitor/pc yang terhubung speaker,
tekan tombol layar penuh / F11, klik sekali agar audio aktif sesuai kebijakan autoplay browser).

## Struktur

```
bell-sekolah/
├── docker-compose.yml      # web :9310, pma :9311, db :3311
├── Dockerfile              # php:8.2-apache + mysqli
├── db/schema.sql           # skema + seed (dieksekusi saat volume db pertama)
├── config/database.php     # environmen + fallback
├── core/                   # Database.php (singleton mysqli), init.php, helpers.php
├── views/                  # layout.php + footer.php (partial UI)
├── admin/                  # modul: jadwal, kategori, suara, hari_libur, pengaturan, backup
├── api/                    # JSON API utk player (status, log, manual, sinyal, confirm, waktu)
├── assets/js/player.js     # logika kiosk (sync waktu server, auto bunyi, countdown)
├── scripts/generate_suara.py  # generator suara WAV bawaan
└── uploads/bel/            # file audio (.htaccess anti-listing)
```

## Konvensi Penting

- Semua label & pesan Bahasa Indonesia; waktu zona **Asia/Jakarta** (server + DB `+07:00`).
- Prepared statement untuk semua query input; CSRF untuk semua form.
- Skema DB di-boot sekali saat volume db baru dibuat; migrasi tambahan wajib file SQL baru.

## Auto Backup

Atur di **Pengaturan** (`backup_otomatis_aktif`, `backup_periode`, `backup_jumlah_simpan`).
Backup otomatis dijalankan saat halaman backup/admin dibuka (lazy scheduler) —
untuk cron sungguhan bisa panggil `admin/backup/auto_backup.php` via cron harian.