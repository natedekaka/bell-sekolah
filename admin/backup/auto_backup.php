<?php

/**
 * Bell Sekolah — Skrip BACKUP OTOMATIS (tanpa UI).
 *
 * Dipanggil lewat cron / task scheduler, contoh (setiap hari):
 *   0 2 * * * php /path/ke/proyek/admin/backup/auto_backup.php
 *
 * Alur:
 *   1. Baca pengaturan backup_otomatis_aktif & backup_periode.
 *   2. Hitung selisih waktu sejak backup terakhir (tabel backup_files).
 *   3. Bila sudah lewat periode → jalankan rutin backup yang sama seperti
 *      buat.php, dengan tipe = 'otomatis'.
 *
 * Jika diakses lewat web, wajib login sebagai admin (guard sederhana).
 */

require_once __DIR__ . '/../../core/init.php';

// Saat diakses lewat web harus admin; lewat CLI langsung jalan
if (PHP_SAPI !== 'cli') {
    require_admin();
}

require_once __DIR__ . '/backup-functions.php';

// Konversi periode pengaturan ke detik
$periodeDetik = [
    'daily'   => 86400,
    'weekly'  => 604800,
    'monthly' => 2629746, // ±1 bulan (rata-rata 30,44 hari)
];

$aktif  = setting('backup_otomatis_aktif', '0') === '1';
$periode = setting('backup_periode', 'weekly');

if (!$aktif) {
    exit("Backup otomatis nonaktif.\n");
}

$detik = $periodeDetik[$periode] ?? $periodeDetik['weekly'];

$terakhir = backup_terakhir();
if ($terakhir) {
    $selisih = time() - strtotime($terakhir['created_at']);
    if ($selisih < $detik) {
        $tersisa = max(0, $detik - $selisih);
        exit('Backup terakhir masih baru (' . (int) round($tersisa / 3600) . ' jam tersisa sebelum periode berikutnya).' . "\n");
    }
}

[$ok, $pesan] = jalankan_backup('otomatis', 'Backup otomatis (jadwal).');
echo ($ok ? 'OK: ' : 'GAGAL: ') . $pesan . "\n";
exit($ok ? 0 : 1);
