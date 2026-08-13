<?php

/**
 * Bell Sekolah — Mengaktifkan satu kategori jadwal.
 * Hanya satu kategori yang boleh bernilai is_active = 1.
 * Proses dilakukan atomik dalam satu transaksi: semua kategori
 * dinonaktifkan dulu, lalu kategori terpilih diaktifkan.
 * Jadwal bel tidak terhapus dalam proses ini.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

require_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);

// Pastikan kategori yang akan diaktifkan benar-benar ada
$stmt = conn()->prepare("SELECT id, nama FROM kategori_jadwal WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$kategori = $stmt->get_result()->fetch_assoc();

if (!$kategori) {
    set_flash('error', 'Kategori tidak ditemukan.');
    redirect('index.php');
}

$conn = conn();
$conn->begin_transaction();
try {
    // Nonaktifkan semua kategori yang sedang aktif
    $conn->query("UPDATE kategori_jadwal SET is_active = 0 WHERE is_active = 1");

    // Aktifkan kategori terpilih
    $stmt = $conn->prepare("UPDATE kategori_jadwal SET is_active = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    set_flash('error', 'Gagal mengaktifkan kategori: ' . $e->getMessage());
    redirect('index.php');
}

set_flash('success', 'Kategori "' . $kategori['nama'] . '" berhasil diaktifkan.');
redirect('index.php');
