<?php

/**
 * Bell Sekolah — Pemroses POST modul Pengaturan.
 * Menerima seluruh field dari form pengaturan, memvalidasi tiap kunci
 * dari daftar putih (whitelist), lalu menyimpannya satu per satu via
 * save_setting().
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

require_csrf('index.php');

$conn = conn();

// Kunci yang boleh diubah dari form ini (whitelist).
// Checkbox dikirim hanya jika dicentang, jadi inisialisasi default '0' dulu.
$nilaiBaru = [
    'toleransi_lag'         => (int) setting('toleransi_lag', 1),
    'volume_default'        => (int) setting('volume_default', 80),
    'kunci_pengaturan'      => '',
    'auto_shutdown_aktif'   => '0',
    'auto_shutdown_jam'     => setting('auto_shutdown_jam', '00:00'),
    'kategori_hari_libur'   => setting('kategori_hari_libur', ''),
    'backup_otomatis_aktif' => '0',
    'backup_periode'        => setting('backup_periode', 'weekly'),
    'backup_jumlah_simpan'  => (int) setting('backup_jumlah_simpan', 10),
    'max_upload_mb'         => (int) setting('max_upload_mb', 20),
    'url_publik'            => '',
    'pengumuman_aktif'      => '1',
];

// ------------------------------------------------------------
// Baca & validasi tiap field
// ------------------------------------------------------------

$toleransi = filter_input(INPUT_POST, 'toleransi_lag', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($toleransi !== false && $toleransi !== null) {
    $nilaiBaru['toleransi_lag'] = (string) $toleransi;
}

$volume = filter_input(INPUT_POST, 'volume_default', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
if ($volume !== false && $volume !== null) {
    $nilaiBaru['volume_default'] = (string) $volume;
}

$kunci = trim((string) ($_POST['kunci_pengaturan'] ?? ''));
if ($kunci !== '') {
    $nilaiBaru['kunci_pengaturan'] = password_hash($kunci, PASSWORD_DEFAULT);
} else {
    // Kosong berarti tidak terkunci — simpan string kosong
    $nilaiBaru['kunci_pengaturan'] = '';
}

$nilaiBaru['auto_shutdown_aktif'] = isset($_POST['auto_shutdown_aktif']) ? '1' : '0';

$jam = trim((string) ($_POST['auto_shutdown_jam'] ?? ''));
if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $jam) === 1) {
    $nilaiBaru['auto_shutdown_jam'] = $jam;
}

$kategoriLibur = trim((string) ($_POST['kategori_hari_libur'] ?? ''));
if ($kategoriLibur !== '') {
    // Pastikan kategori yang dipilih benar-benar ada
    $idKategori = (int) $kategoriLibur;
    $stmt = $conn->prepare("SELECT id FROM kategori_jadwal WHERE id = ?");
    $stmt->bind_param('i', $idKategori);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $nilaiBaru['kategori_hari_libur'] = (string) $idKategori;
    }
} else {
    $nilaiBaru['kategori_hari_libur'] = '';
}

$nilaiBaru['backup_otomatis_aktif'] = isset($_POST['backup_otomatis_aktif']) ? '1' : '0';

$nilaiBaru['pengumuman_aktif'] = isset($_POST['pengumuman_aktif']) ? '1' : '0';

$periode = (string) ($_POST['backup_periode'] ?? '');
if (in_array($periode, ['daily', 'weekly', 'monthly'], true)) {
    $nilaiBaru['backup_periode'] = $periode;
}

$jumlahSimpan = filter_input(INPUT_POST, 'backup_jumlah_simpan', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($jumlahSimpan !== false && $jumlahSimpan !== null) {
    $nilaiBaru['backup_jumlah_simpan'] = (string) $jumlahSimpan;
}

$maxUpload = filter_input(INPUT_POST, 'max_upload_mb', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($maxUpload !== false && $maxUpload !== null) {
    $nilaiBaru['max_upload_mb'] = (string) $maxUpload;
}

$url = trim((string) ($_POST['url_publik'] ?? ''));
if ($url !== '') {
    // Terima URL http/https; selain itu abaikan dan biarkan nilai lama
    $filtered = filter_var($url, FILTER_VALIDATE_URL);
    if ($filtered !== false && preg_match('#^https?://#i', $filtered) === 1) {
        $nilaiBaru['url_publik'] = $filtered;
    }
} else {
    $nilaiBaru['url_publik'] = '';
}

// ------------------------------------------------------------
// Simpan seluruh kunci (whitelist) via save_setting()
// ------------------------------------------------------------
$tersimpan = 0;
$gagal     = [];
foreach ($nilaiBaru as $kunci => $nilai) {
    if (save_setting($kunci, $nilai)) {
        $tersimpan++;
    } else {
        $gagal[] = $kunci;
    }
}

if (empty($gagal)) {
    set_flash('success', 'Pengaturan berhasil disimpan (' . $tersimpan . ' kunci diperbarui).');
} else {
    set_flash('warning', 'Sebagian pengaturan gagal disimpan: ' . implode(', ', $gagal) . '.');
}

redirect('index.php');
