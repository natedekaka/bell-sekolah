<?php

/**
 * Bell Sekolah — Pemroses POST modul Hari Libur.
 * Menangani aksi tambah dan hapus. Seluruh input divalidasi
 * dan query memakai prepared statement.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

require_csrf('index.php');

$aksi = $_POST['aksi'] ?? '';

// ------------------------------------------------------------
// Aksi tambah
// ------------------------------------------------------------
if ($aksi === 'tambah') {
    $tanggal    = trim($_POST['tanggal'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Validasi format tanggal Y-m-d (menggunakan checkdate agar tanggal
    // seperti 2026-02-30 tidak lolos sekalipun cocok dengan regex).
    $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) === 1;
    if ($valid) {
        [$tahun, $bulan, $hari] = array_map('intval', explode('-', $tanggal));
        $valid = checkdate($bulan, $hari, $tahun);
    }
    if (!$valid) {
        set_flash('error', 'Format tanggal tidak valid. Gunakan format Y-m-d (cth: 2026-08-17).');
        redirect('index.php');
    }

    $panjangKet = function_exists('mb_strlen') ? mb_strlen($keterangan) : strlen($keterangan);
    if ($panjangKet > 255) {
        set_flash('error', 'Keterangan maksimal 255 karakter.');
        redirect('index.php');
    }

    $conn = conn();
    $stmt = $conn->prepare("INSERT INTO hari_libur (tanggal, keterangan) VALUES (?, ?)");
    $stmt->bind_param('ss', $tanggal, $keterangan);

    if ($stmt->execute()) {
        set_flash('success', 'Tanggal ' . $tanggal . ' berhasil ditambahkan sebagai hari libur.');
    } else {
        // Duplicate entry pada kolom unik tanggal (kode error MySQL 1062)
        if ($conn->errno === 1062) {
            set_flash('error', 'Tanggal ' . $tanggal . ' sudah terdaftar sebagai hari libur.');
        } else {
            set_flash('error', 'Gagal menambahkan hari libur: ' . $conn->error);
        }
    }

    redirect('index.php');
}

// ------------------------------------------------------------
// Aksi hapus
// ------------------------------------------------------------
if ($aksi === 'hapus') {
    $id = (int) ($_POST['id'] ?? 0);

    // Ambil tanggal untuk pesan flash
    $stmt = conn()->prepare("SELECT id, tanggal FROM hari_libur WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $libur = $stmt->get_result()->fetch_assoc();

    if (!$libur) {
        set_flash('error', 'Hari libur tidak ditemukan.');
        redirect('index.php');
    }

    $stmt = conn()->prepare("DELETE FROM hari_libur WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    set_flash('success', 'Hari libur tanggal ' . $libur['tanggal'] . ' berhasil dihapus.');
    redirect('index.php');
}

// Aksi tidak dikenal
set_flash('error', 'Aksi tidak dikenali.');
redirect('index.php');
