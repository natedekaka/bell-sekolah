<?php

/**
 * Bell Sekolah — Fungsi bantu backup/restore (lokal modul admin/backup).
 * Dipakai oleh buat.php, auto_backup.php, restore.php, dan hapus_backup.php.
 * File ini BUKAN bagian dari core/helpers.php agar tidak mengubah inti aplikasi.
 *
 * Wajib di-include SETELAH core/init.php agar conn(), settings(), dsb. tersedia.
 */

// ------------------------------------------------------------
// Lokasi & daftar tabel
// ------------------------------------------------------------

/** Path absolut folder data backup (root proyek/backups). */
function backup_root_dir() {
    return __DIR__ . '/../../backups';
}

/** Daftar tabel yang diekspor ke dalam file backup (urut stabil). */
function backup_daftar_tabel() {
    return ['kategori_jadwal', 'jadwal_bel', 'suara_bel', 'hari_libur', 'pengaturan', 'users'];
}

// ------------------------------------------------------------
// Pembuatan backup
// ------------------------------------------------------------

/**
 * Susun struktur data JSON backup dari seluruh tabel aplikasi.
 * Tabel `pengaturan` disimpan sebagai peta kunci => nilai agar mudah
 * dipulihkan lewat save_setting(); tabel lain disimpan sebagai larik
 * baris (SELECT *) termasuk id agar relasi antar tabel terjaga.
 */
function backup_build_data($catatan = '') {
    $conn = conn();
    $data = [];

    foreach (backup_daftar_tabel() as $tabel) {
        if ($tabel === 'pengaturan') {
            $peta = [];
            $res  = $conn->query("SELECT kunci, nilai FROM pengaturan");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $peta[$row['kunci']] = $row['nilai'];
                }
            }
            $data[$tabel] = $peta;
            continue;
        }

        // Nama tabel berasal dari daftar tetap (bukan input user), aman.
        $stmt = $conn->prepare("SELECT * FROM `$tabel`");
        $data[$tabel] = [];
        if ($stmt && $stmt->execute()) {
            $res = $stmt->get_result();
            $data[$tabel] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }
    }

    return [
        'created_at' => date('Y-m-d H:i:s'),
        'database'   => 'bell_sekolah',
        'data'       => $data,
        'catatan'    => (string) $catatan,
    ];
}

/**
 * Tulis struktur backup ke file JSON `bell_sekolah_YYYYmmdd_HHMMSS.json`
 * di folder backups/, lalu catat ke tabel backup_files.
 *
 * @return array [sukses(bool), pesan|namaFile]
 */
function backup_tulis_json($isi, $tipe, $catatan = '') {
    $dir = backup_root_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return [false, 'Gagal membuat folder backups/.'];
    }

    $namaFile = 'bell_sekolah_' . date('Ymd_His') . '.json';
    $path     = $dir . '/' . $namaFile;

    $json = json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return [false, 'Gagal membuat data JSON backup.'];
    }

    if (file_put_contents($path, $json) === false) {
        return [false, 'Gagal menulis file backup ke disk.'];
    }

    $ukuran = (int) filesize($path);
    $stmt   = conn()->prepare("INSERT INTO backup_files (nama_file, ukuran_bytes, tipe) VALUES (?, ?, ?)");
    $stmt->bind_param('sis', $namaFile, $ukuran, $tipe);
    if (!$stmt->execute()) {
        @unlink($path); // bersihkan file bila pencatatan DB gagal
        return [false, 'Gagal mencatat backup ke database: ' . conn()->error];
    }

    return [true, $namaFile];
}

/**
 * Hapus file backup + catatan DB milik satu id.
 * Dipakai untuk hapus manual dan pruning otomatis.
 */
function hapus_satu_backup($id, $namaFile = null) {
    $conn = conn();

    if ($namaFile === null) {
        $stmt = $conn->prepare("SELECT nama_file FROM backup_files WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return false;
        }
        $namaFile = $row['nama_file'];
    }

    // basename() agar path tak bisa diarahkan ke luar folder backups/
    $path = backup_root_dir() . '/' . basename((string) $namaFile);
    if (is_file($path)) {
        @unlink($path);
    }

    $stmt = $conn->prepare("DELETE FROM backup_files WHERE id = ?");
    $stmt->bind_param('i', $id);
    return $stmt->execute();
}

/**
 * Pruning otomatis: pertahankan hanya N backup terbaru (berdasarkan
 * created_at), sisanya dihapus (file + catatan DB).
 *
 * @return int jumlah backup lama yang dihapus
 */
function backup_prune_lama($jumlahSimpan) {
    $jumlahSimpan = (int) $jumlahSimpan;
    if ($jumlahSimpan < 1) {
        return 0; // 0 = tidak dibatasi
    }

    $res = conn()->query("SELECT id, nama_file FROM backup_files ORDER BY created_at DESC, id DESC");
    if (!$res) {
        return 0;
    }

    $dihapus = 0;
    $ke      = 0;
    while ($row = $res->fetch_assoc()) {
        $ke++;
        if ($ke <= $jumlahSimpan) {
            continue;
        }
        if (hapus_satu_backup((int) $row['id'], $row['nama_file'])) {
            $dihapus++;
        }
    }
    return $dihapus;
}

/**
 * Rutin utama backup: susun data, tulis file JSON, catat DB, pruning.
 * Dipanggil buat.php (manual) dan auto_backup.php (otomatis).
 *
 * @return array [sukses(bool), pesan]
 */
function jalankan_backup($tipe, $catatan = '') {
    if (!in_array($tipe, ['manual', 'otomatis'], true)) {
        return [false, 'Tipe backup tidak valid.'];
    }

    [$ok, $hasil] = backup_tulis_json(backup_build_data($catatan), $tipe, $catatan);
    if (!$ok) {
        return [false, $hasil];
    }

    $dihapus = backup_prune_lama(setting('backup_jumlah_simpan', 10));

    return [
        true,
        'Backup "' . $hasil . '" berhasil dibuat' . ($dihapus > 0 ? '; ' . $dihapus . ' backup lama dihapus.' : '.'),
    ];
}

/** Ambil record backup terbaru dari tabel backup_files, atau null bila kosong. */
function backup_terakhir() {
    $res = conn()->query("SELECT id, nama_file, created_at FROM backup_files ORDER BY created_at DESC, id DESC LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

// ------------------------------------------------------------
// Restore
// ------------------------------------------------------------

/**
 * Validasi isi JSON hasil decode sebelum dipakai restore.
 * Pastikan struktur punya kunci 'data' dan memuat semua tabel yang
 * dibutuhkan aplikasi, dan tiap tabel berupa array (tabel kosong tetap
 * valid sebagai array kosong; null / non-array ditolak).
 */
function backup_validasi_isi($isi) {
    if (!is_array($isi) || !isset($isi['data']) || !is_array($isi['data'])) {
        return false;
    }
    foreach (backup_daftar_tabel() as $tabel) {
        if (!isset($isi['data'][$tabel]) || !is_array($isi['data'][$tabel])) {
            return false;
        }
    }
    return true;
}

/**
 * Sisipkan baris dari backup ke satu tabel menggunakan prepared statement.
 * Hanya kolom yang benar-benar ada di skema tabel yang dipakai (kolom id
 * ikut disisipkan agar relasi antar tabel tetap valid).
 */
function backup_insert_rows($tabel, $rows) {
    if (!is_array($rows) || empty($rows)) {
        return;
    }

    $conn = conn();

    // Kolom aktual tabel (dari skema), bukan dari JSON.
    $kolomSkema = [];
    $res = $conn->query("SHOW COLUMNS FROM `$tabel`");
    if (!$res) {
        throw new RuntimeException('Tabel ' . $tabel . ' tidak dikenali: ' . $conn->error);
    }
    while ($kolom = $res->fetch_assoc()) {
        $kolomSkema[] = $kolom['Field'];
    }

    foreach ($rows as $row) {
        $row = (array) $row;

        // Pasangkan hanya kolom yang ada di skema
        $pasang = [];
        foreach ($kolomSkema as $namaKolom) {
            if (array_key_exists($namaKolom, $row)) {
                $pasang[$namaKolom] = $row[$namaKolom];
            }
        }
        if (empty($pasang)) {
            continue;
        }

        $namaKolom = array_keys($pasang);
        $nilai     = array_values($pasang);
        $query     = "INSERT INTO `$tabel` (`" . implode('`,`', $namaKolom) . "`) VALUES ("
            . implode(',', array_fill(0, count($nilai), '?')) . ")";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan query restore tabel ' . $tabel . ': ' . $conn->error);
        }

        // Semua diikat sebagai string; MySQL/MariaDB mengonversi otomatis.
        // Dipakai referensi ke elemen larik agar bind_param aman.
        $tipe = str_repeat('s', count($nilai));
        $refs = [];
        foreach ($nilai as $i => $v) {
            $refs[$i] = &$nilai[$i];
        }
        $stmt->bind_param($tipe, ...$refs);

        if (!$stmt->execute()) {
            throw new RuntimeException('Gagal mengembalikan data tabel ' . $tabel . ': ' . $conn->error);
        }
    }
}

/**
 * Pulihkan seluruh data dari struktur backup di dalam satu transaksi.
 * Urutan: hapus tabel anak dulu, lalu sisipkan tabel induk dulu.
 * log_bel TIDAK dihapus (FK-nya diset NULL otomatis oleh database).
 *
 * @return array [sukses(bool), pesan]
 */
function backup_pulihkan($isi) {
    $conn = conn();
    $conn->begin_transaction();

    try {
        // 1. Hapus: tabel anak terlebih dahulu agar tidak melanggar FK
        $tabelHapus = ['jadwal_bel', 'hari_libur', 'suara_bel', 'kategori_jadwal', 'users'];
        foreach ($tabelHapus as $tabel) {
            $conn->query("DELETE FROM `$tabel`");
        }

        // 2. Sisipkan: tabel induk terlebih dahulu (relasi FK tetap valid)
        foreach (['kategori_jadwal', 'suara_bel', 'jadwal_bel', 'hari_libur'] as $tabel) {
            backup_insert_rows($tabel, $isi['data'][$tabel]);
        }

        // 3. Pengaturan disisipkan lewat save_setting() per kunci
        $pengaturan = $isi['data']['pengaturan'];
        if (is_array($pengaturan)) {
            foreach ($pengaturan as $kunci => $nilai) {
                save_setting((string) $kunci, (string) $nilai);
            }
        }

        // 4. Users: hapus-dan-masuk (hash password ikut dipulihkan)
        backup_insert_rows('users', $isi['data']['users']);

        $conn->commit();
        return [true, 'Restore data berhasil. Silakan login kembali agar sesi tetap valid.'];
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, 'Restore gagal, perubahan dibatalkan: ' . $e->getMessage()];
    }
}
