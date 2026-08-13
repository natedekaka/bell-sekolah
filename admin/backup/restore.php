<?php

/**
 * Bell Sekolah — Proses RESTORE backup.
 * Menerima POST: id backup (dari dropdown di index.php) + radio konfirmasi.
 * File dibaca dari folder backups/ berdasarkan nama_file di tabel
 * backup_files (tidak pernah dari path yang dikirim user), divalidasi,
 * lalu seluruh data dipulihkan di dalam satu transaksi.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

require_csrf('index.php');

require_once __DIR__ . '/backup-functions.php';

// Konfirmasi wajib melalui radio
if (($_POST['konfirmasi'] ?? '') !== 'ya') {
    set_flash('error', 'Restore dibatalkan: konfirmasi tidak dicentang.');
    redirect('index.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    set_flash('error', 'Backup tidak ditemukan.');
    redirect('index.php');
}

// Ambil nama file dari database (satu-satunya sumber kebenaran)
$stmt = conn()->prepare("SELECT id, nama_file, created_at FROM backup_files WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$backup = $stmt->get_result()->fetch_assoc();

if (!$backup) {
    set_flash('error', 'Backup tidak ditemukan di database.');
    redirect('index.php');
}

$namaFile = basename($backup['nama_file']);
$path     = __DIR__ . '/../../backups/' . $namaFile;

if (!is_file($path)) {
    set_flash('error', 'File backup "' . $namaFile . '" tidak ditemukan di folder backups/.');
    redirect('index.php');
}

$isiJson = file_get_contents($path);
if ($isiJson === false) {
    set_flash('error', 'Gagal membaca file backup.');
    redirect('index.php');
}

$isi = json_decode($isiJson, true);
if ($isi === null) {
    set_flash('error', 'Isi file backup bukan JSON yang valid.');
    redirect('index.php');
}

if (!backup_validasi_isi($isi)) {
    set_flash('error', 'Isi file backup tidak lengkap/tidak valid — proses restore dibatalkan.');
    redirect('index.php');
}

[$ok, $pesan] = backup_pulihkan($isi);

set_flash($ok ? 'success' : 'error', $pesan);
redirect('index.php');
