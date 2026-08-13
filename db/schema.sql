-- ============================================================
-- Bell Sekolah — Skema Database (MariaDB 10.11 / MySQL 8)
-- Otomatis dieksekusi saat volume DB pertama kali dibuat
-- (docker-entrypoint-initdb.d)
-- ============================================================

CREATE DATABASE IF NOT EXISTS bell_sekolah
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE bell_sekolah;

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Tabel: users — akun admin & operator
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL DEFAULT '',
  role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabel: kategori_jadwal — Reguler, Ulangan/Ujian, Ramadhan, dll.
-- Hanya satu kategori yang aktif (is_active=1) dalam satu waktu.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kategori_jadwal (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(100) NOT NULL,
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  warna VARCHAR(20) NOT NULL DEFAULT '#2563eb',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kategori_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabel: suara_bel — library audio bel (bawaan / upload / rekaman)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suara_bel (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  format ENUM('mp3','wav','ogg') NOT NULL DEFAULT 'wav',
  ukuran_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sumber ENUM('bawaan','upload','rekaman') NOT NULL DEFAULT 'upload',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabel: jadwal_bel — jadwal bel per hari per kategori.
-- Multi-bell di jam yang sama DIPERBOLEHKAN (tanpa unique key).
-- hari: 1=Senin, 2=Selasa, 3=Rabu, 4=Kamis, 5=Jumat, 6=Sabtu
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jadwal_bel (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_kategori INT UNSIGNED NOT NULL,
  hari TINYINT NOT NULL COMMENT '1=Senin .. 6=Sabtu',
  jam TIME NOT NULL,
  tipe ENUM('masuk','ganti_jam','istirahat','sholat','pulang','reses','kustom') NOT NULL DEFAULT 'kustom',
  id_suara INT UNSIGNED NULL,
  durasi_bunyi INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'durasi bunyi (detik)',
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jadwal_kategori (id_kategori),
  KEY idx_jadwal_hari (hari),
  KEY idx_jadwal_jam (jam),
  CONSTRAINT fk_jadwal_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori_jadwal (id) ON DELETE CASCADE,
  CONSTRAINT fk_jadwal_suara FOREIGN KEY (id_suara)
    REFERENCES suara_bel (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabel: hari_libur — tanggal tidak efektif.
-- Saat hari libur, bel mati otomatis ATAU memakai kategori
-- pengganti yang ditentukan di pengaturan.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hari_libur (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tanggal DATE NOT NULL,
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabel: pengaturan — key-value pengaturan aplikasi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengaturan (
  kunci VARCHAR(50) NOT NULL,
  nilai TEXT NULL,
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (kunci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabel: log_bel — jejak setiap bel berbunyi (otomatis/manual/darurat)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS log_bel (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  waktu DATETIME NOT NULL,
  jenis ENUM('otomatis','manual','darurat') NOT NULL DEFAULT 'otomatis',
  id_jadwal INT UNSIGNED NULL,
  id_kategori INT UNSIGNED NULL,
  id_suara INT UNSIGNED NULL,
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  diputar TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=yet, 1=claimed/sudah diputar',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_waktu (waktu),
  KEY idx_log_diputar (diputar),
  CONSTRAINT fk_log_jadwal FOREIGN KEY (id_jadwal)
    REFERENCES jadwal_bel (id) ON DELETE SET NULL,
  CONSTRAINT fk_log_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori_jadwal (id) ON DELETE SET NULL,
  CONSTRAINT fk_log_suara FOREIGN KEY (id_suara)
    REFERENCES suara_bel (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabel: backup_files — catatan file backup (manual & otomatis)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS backup_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_file VARCHAR(255) NOT NULL,
  ukuran_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tipe ENUM('manual','otomatis') NOT NULL DEFAULT 'manual',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_backup_tipe (tipe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Akun default: admin / admin123, operator / admin123
-- (hash bcrypt untuk "admin123", cocok dengan password_verify PHP)
INSERT INTO users (username, password_hash, nama_lengkap, role, aktif) VALUES
  ('admin', '$2y$10$NKnl8jdhI6z468/xmz85K.mtilygpIPC4vcs/dVwuSBa.wtQDytbK', 'Administrator', 'admin', 1),
  ('operator', '$2y$10$NKnl8jdhI6z468/xmz85K.mtilygpIPC4vcs/dVwuSBa.wtQDytbK', 'Operator Bel', 'operator', 1);

-- Kategori jadwal default (Reguler aktif)
INSERT INTO kategori_jadwal (nama, keterangan, warna, is_default, is_active) VALUES
  ('Reguler', 'Jadwal bel normal hari sekolah', '#2563eb', 1, 1),
  ('Ulangan/Ujian', 'Jadwal bel saat ulangan / ujian', '#dc2626', 0, 0),
  ('Ramadhan', 'Jadwal bel saat bulan Ramadhan', '#16a34a', 0, 0);

-- Suara bawaan (file WAV digenerate oleh scripts/generate_suara.py)
INSERT INTO suara_bel (nama, file_path, format, ukuran_bytes, sumber, is_default) VALUES
  ('Bel Masuk (bawaan)',   'uploads/bel/bel_masuk.wav',     'wav', 0, 'bawaan', 1),
  ('Bel Ganti Jam (bawaan)','uploads/bel/bel_ganti_jam.wav', 'wav', 0, 'bawaan', 0),
  ('Bel Istirahat (bawaan)','uploads/bel/bel_istirahat.wav', 'wav', 0, 'bawaan', 0),
  ('Bel Pulang (bawaan)',  'uploads/bel/bel_pulang.wav',     'wav', 0, 'bawaan', 0),
  ('Bel Darurat (bawaan)', 'uploads/bel/bel_darurat.wav',    'wav', 0, 'bawaan', 0);

-- Contoh jadwal reguler (Senin–Sabtu) agar langsung bisa dicoba
INSERT INTO jadwal_bel (id_kategori, hari, jam, tipe, id_suara, durasi_bunyi, keterangan, aktif) VALUES
  (1, 1, '07:00:00', 'masuk',     1, 10, 'Bel masuk sekolah', 1),
  (1, 1, '07:30:00', 'ganti_jam', 2, 8,  'Ganti jam pelajaran 1', 1),
  (1, 1, '09:00:00', 'istirahat', 3, 8,  'Istirahat', 1),
  (1, 1, '09:30:00', 'ganti_jam', 2, 8,  'Ganti jam pelajaran', 1),
  (1, 1, '12:00:00', 'pulang',    4, 10, 'Bel pulang sekolah', 1),
  (1, 2, '07:00:00', 'masuk',     1, 10, 'Bel masuk sekolah', 1),
  (1, 2, '12:00:00', 'pulang',    4, 10, 'Bel pulang sekolah', 1),
  (1, 3, '07:00:00', 'masuk',     1, 10, 'Bel masuk sekolah', 1),
  (1, 3, '12:00:00', 'pulang',    4, 10, 'Bel pulang sekolah', 1),
  (1, 4, '07:00:00', 'masuk',     1, 10, 'Bel masuk sekolah', 1),
  (1, 4, '12:00:00', 'pulang',    4, 10, 'Bel pulang sekolah', 1),
  (1, 5, '07:00:00', 'masuk',     1, 10, 'Bel masuk sekolah', 1),
  (1, 5, '11:00:00', 'pulang',    4, 10, 'Bel pulang sekolah (Jumat)', 1),
  (1, 6, '07:00:00', 'masuk',     1, 10, 'Bel masuk sekolah (Sabtu)', 1),
  (1, 6, '11:00:00', 'pulang',    4, 10, 'Bel pulang sekolah (Sabtu)', 1);

-- Pengaturan default aplikasi
INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES
  ('toleransi_lag',          '1',      'Toleransi keterlambatan bunyi (detik)'),
  ('volume_default',         '80',     'Volume bawaan pemutar bel (0-100)'),
  ('kunci_pengaturan',       '',       'Password kunci pengaturan (kosong = tidak terkunci)'),
  ('auto_shutdown_aktif',    '0',      'Aktifkan auto shutdown PC pemutar (1/0)'),
  ('auto_shutdown_jam',      '00:00',  'Jam auto shutdown (HH:MM)'),
  ('kategori_hari_libur',    '',       'ID kategori pengganti saat hari libur (kosong = bel mati)'),
  ('backup_otomatis_aktif',  '0',      'Aktifkan backup otomatis (1/0)'),
  ('backup_periode',         'weekly', 'Periode backup otomatis: daily / weekly / monthly'),
  ('backup_jumlah_simpan',   '10',     'Jumlah backup terakhir yang disimpan'),
  ('max_upload_mb',          '20',     'Batas maksimum upload file audio (MB)'),
  ('url_publik',             '',       'URL publik aplikasi (untuk player remote, opsional)'),
  ('pengumuman_aktif',       '1',      'Nyalakan pengumuman suara sebelum bel terjadwal (1/0)');
