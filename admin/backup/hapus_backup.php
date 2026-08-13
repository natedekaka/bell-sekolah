<?php

/**
 * Bell Sekolah — Proses hapus backup.
 * Menerima POST ?id=N, menghapus file JSON dari folder backups/ sekaligus
 * catatannya di tabel backup_files.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

require_csrf('index.php');

require_once __DIR__ . '/backup-functions.php';

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    set_flash('error', 'Backup tidak ditemukan.');
    redirect('index.php');
}

// Hapus (file + catatan DB) sekaligus; false bila id tak ada
if (hapus_satu_backup($id)) {
    set_flash('success', 'Backup berhasil dihapus.');
} else {
    set_flash('error', 'Backup tidak ditemukan atau gagal dihapus.');
}

redirect('index.php');
