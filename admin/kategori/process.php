<?php

/**
 * Bell Sekolah — Pemroses POST modul Kategori Jadwal.
 * Menangani aksi: tambah, edit, dan hapus.
 * Seluruh input divalidasi dan query memakai prepared statement.
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
// Aksi tambah & edit
// ------------------------------------------------------------
if ($aksi === 'tambah' || $aksi === 'edit') {
    $id         = (int) ($_POST['id'] ?? 0);
    $nama       = trim($_POST['nama'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $warna      = trim($_POST['warna'] ?? '#2563eb');

    // Validasi warna: wajib format heksadesimal #rrggbb, fallback warna bawaan
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $warna)) {
        $warna = '#2563eb';
    }

    // Validasi nama wajib diisi
    if ($nama === '') {
        set_flash('error', 'Nama kategori wajib diisi.');
        redirect($aksi === 'edit' ? 'edit.php?id=' . $id : 'tambah.php');
    }

    // Batas panjang kolom (dihitung per karakter, fallback byte bila mbstring tak ada)
    $panjangNama = function_exists('mb_strlen') ? mb_strlen($nama) : strlen($nama);
    $panjangKet  = function_exists('mb_strlen') ? mb_strlen($keterangan) : strlen($keterangan);

    if ($panjangNama > 100) {
        set_flash('error', 'Nama kategori maksimal 100 karakter.');
        redirect($aksi === 'edit' ? 'edit.php?id=' . $id : 'tambah.php');
    }
    if ($panjangKet > 255) {
        set_flash('error', 'Keterangan maksimal 255 karakter.');
        redirect($aksi === 'edit' ? 'edit.php?id=' . $id : 'tambah.php');
    }

    $conn = conn();

    if ($aksi === 'tambah') {
        // Kategori baru berstatus nonaktif; is_default tetap 0
        $stmt = $conn->prepare("INSERT INTO kategori_jadwal (nama, keterangan, warna) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $nama, $keterangan, $warna);

        if ($stmt->execute()) {
            set_flash('success', 'Kategori "' . $nama . '" berhasil ditambahkan.');
        } else {
            // Duplicate entry pada kolom unik nama (kode error MySQL 1062)
            if ($conn->errno === 1062) {
                set_flash('error', 'Nama kategori "' . $nama . '" sudah digunakan.');
            } else {
                set_flash('error', 'Gagal menambahkan kategori: ' . $conn->error);
            }
        }
    } else {
        // Pastikan kategori yang akan diedit masih ada
        $stmt = $conn->prepare("SELECT id FROM kategori_jadwal WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $kategori = $stmt->get_result()->fetch_assoc();

        if (!$kategori) {
            set_flash('error', 'Kategori tidak ditemukan.');
            redirect('index.php');
        }

        $stmt = $conn->prepare("UPDATE kategori_jadwal SET nama = ?, keterangan = ?, warna = ? WHERE id = ?");
        $stmt->bind_param('sssi', $nama, $keterangan, $warna, $id);

        if ($stmt->execute()) {
            set_flash('success', 'Kategori "' . $nama . '" berhasil diperbarui.');
        } else {
            if ($conn->errno === 1062) {
                set_flash('error', 'Nama kategori "' . $nama . '" sudah digunakan.');
            } else {
                set_flash('error', 'Gagal memperbarui kategori: ' . $conn->error);
            }
        }
    }

    redirect('index.php');
}

// ------------------------------------------------------------
// Aksi hapus
// ------------------------------------------------------------
if ($aksi === 'hapus') {
    $id = (int) ($_POST['id'] ?? 0);

    // Ambil nama kategori untuk pesan flash
    $stmt = conn()->prepare("SELECT id, nama, is_default FROM kategori_jadwal WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $kategori = $stmt->get_result()->fetch_assoc();

    if (!$kategori) {
        set_flash('error', 'Kategori tidak ditemukan.');
        redirect('index.php');
    }

    // Kategori default tidak boleh dihapus
    if ((int) $kategori['is_default'] === 1) {
        set_flash('error', 'Kategori default tidak dapat dihapus.');
        redirect('index.php');
    }

    // Hapus dalam satu transaksi; jadwal ikut terhapus via ON DELETE CASCADE
    $conn = conn();
    $conn->begin_transaction();
    try {
        // Syarat is_default=0 menjaga proteksi default walau data berubah
        $stmt = $conn->prepare("DELETE FROM kategori_jadwal WHERE id = ? AND is_default = 0");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        set_flash('error', 'Gagal menghapus kategori: ' . $e->getMessage());
        redirect('index.php');
    }

    set_flash('success', 'Kategori "' . $kategori['nama'] . '" berhasil dihapus.');
    redirect('index.php');
}

// Aksi tidak dikenal
set_flash('error', 'Aksi tidak dikenali.');
redirect('index.php');
