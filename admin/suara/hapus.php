<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();
require_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'ID suara tidak valid.');
    redirect('index.php');
}

$stmt = conn()->prepare("SELECT * FROM suara_bel WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$suara = $stmt->get_result()->fetch_assoc();

if (!$suara) {
    set_flash('error', 'Suara tidak ditemukan.');
    redirect('index.php');
}

$adalahDefault = (int) $suara['is_default'] === 1;
$filePath = $suara['file_path'];

// Hapus baris dari database (jadwal_bel.id_suara otomatis NULL via FK ON DELETE SET NULL).
$stmt = conn()->prepare("DELETE FROM suara_bel WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

$pesan = 'Suara "' . $suara['nama'] . '" berhasil dihapus.';

// Jika suara yang dihapus adalah default, lepas status default dari semua suara tersisa.
if ($adalahDefault) {
    conn()->query("UPDATE suara_bel SET is_default = 0");
    $pesan .= ' Suara tersebut adalah default, sehingga status default kini dilepas.';
}

// Hapus file fisik — hanya jika benar berada di folder uploads/bel.
if (strpos($filePath, 'uploads/bel/') === 0
    && strpos($filePath, '..') === false
    && is_file($filePath)) {
    @unlink($filePath);
}

set_flash('success', $pesan);
redirect('index.php');
