<?php

/**
 * Bell Sekolah — Unduh file backup.
 * Menerima GET ?id=N (id dari tabel backup_files), lalu mengirim file JSON
 * dari folder backups/ sebagai lampiran (attachment). Nama file diambil
 * HANYA dari record database — tidak pernah dari input user.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    set_flash('error', 'Backup tidak ditemukan.');
    redirect('index.php');
}

// Ambil nama file dari database (satu-satunya sumber kebenaran)
$stmt = conn()->prepare("SELECT id, nama_file FROM backup_files WHERE id = ?");
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

// Kirim sebagai unduhan
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Content-Length: ' . (int) filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
