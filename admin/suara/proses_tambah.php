<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();
require_csrf('index.php');

$nama = trim($_POST['nama'] ?? '');
$jadikanDefault = !empty($_POST['is_default']) ? 1 : 0;

if ($nama === '') {
    set_flash('error', 'Nama suara wajib diisi.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}

// Simpan file: dari rekaman (data URI) atau upload biasa.
$dataRekaman = trim($_POST['data_rekaman'] ?? '');
if ($dataRekaman !== '') {
    list($sukses, $hasil) = simpan_rekaman_audio($dataRekaman);
    $sumber = 'rekaman';
} else {
    list($sukses, $hasil) = simpan_upload_audio($_FILES['file_audio'] ?? null);
    $sumber = 'upload';
}

if (!$sukses) {
    set_flash('error', $hasil);
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}

$filePath = $hasil;
$format = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
// Kolom format hanya menerima mp3/wav/ogg; webm (hasil rekaman browser) disimpan sebagai ogg.
if (!in_array($format, ['mp3', 'wav', 'ogg'], true)) {
    $format = 'ogg';
}
$ukuranBytes = is_file($filePath) ? (int) filesize($filePath) : 0;

if ($jadikanDefault) {
    conn()->query("UPDATE suara_bel SET is_default = 0");
}

$stmt = conn()->prepare("INSERT INTO suara_bel (nama, file_path, format, ukuran_bytes, sumber, is_default)
    VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssisi', $nama, $filePath, $format, $ukuranBytes, $sumber, $jadikanDefault);
$stmt->execute();

set_flash('success', 'Suara "' . $nama . '" berhasil ditambahkan.');
redirect('index.php');
