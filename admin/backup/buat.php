<?php

/**
 * Bell Sekolah — Proses pembuatan backup MANUAL.
 * Menerima POST dari form "Buat Backup Sekarang (Manual)" di index.php,
 * menjalankan rutin backup (tulis JSON + catat DB + pruning), lalu
 * redirect kembali dengan pesan flash.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

require_csrf('index.php');

require_once __DIR__ . '/backup-functions.php';

[$ok, $pesan] = jalankan_backup('manual', 'Backup manual oleh admin.');

set_flash($ok ? 'success' : 'error', $pesan);
redirect('index.php');
