<?php

/**
 * Bell Sekolah — API status pemutar (kiosk).
 *
 * Bebas login (tanpa CSRF). Mengembalikan JSON berisi:
 *   - waktu server (server_now_ms, dipakai player.js untuk offset),
 *   - informasi hari ini (libur / minggu / aktif + kategori efektif),
 *   - daftar jadwal bel hari ini (dengan fallback suara default),
 *   - konfigurasi pemutar (volume_default, toleransi_lag).
 *
 * Logika kategori efektif:
 *   - Hari libur + setting kategori_hari_libur terisi  -> pakai kategori pengganti.
 *   - Hari libur tanpa pengganti                        -> bel mati (mode 'libur').
 *   - Minggu (hari_php_to_app() = 0)                    -> bel mati (mode 'minggu').
 *   - Selain itu                                        -> kategori aktif (mode 'aktif').
 */

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $conn = conn();

    $tanggal   = date('Y-m-d');
    $kodeHari  = hari_php_to_app();
    $nowMs     = (int) (microtime(true) * 1000);

    // Kategori aktif saat ini
    $kategori = null;
    $stmt = $conn->prepare("SELECT * FROM kategori_jadwal WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $kategori = $stmt->get_result()->fetch_assoc() ?: null;

    // Apakah hari ini tanggal libur
    $libur = null;
    $stmt = $conn->prepare("SELECT * FROM hari_libur WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $libur = $stmt->get_result()->fetch_assoc() ?: null;

    $kategoriLibur  = (int) setting('kategori_hari_libur', 0);
    $mode           = 'aktif';
    $liburPengganti = false;
    $kategoriEfektif = $kategori;

    if ($libur) {
        $mode = 'libur';
        if ($kategoriLibur > 0) {
            $stmt = $conn->prepare("SELECT * FROM kategori_jadwal WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $kategoriLibur);
            $stmt->execute();
            $katPengganti = $stmt->get_result()->fetch_assoc();
            if ($katPengganti) {
                $kategoriEfektif = $katPengganti;
                $liburPengganti  = true;
            } else {
                $kategoriEfektif = null;
            }
        } else {
            $kategoriEfektif = null;
        }
    } elseif ($kodeHari === 0) {
        $mode = 'minggu';
        $kategoriEfektif = null;
    }

    $suaraDefault = cari_suara_default();

    // Jadwal hari ini untuk kategori efektif
    $jadwal = [];
    if ($kategoriEfektif && $kodeHari > 0) {
        $stmt = $conn->prepare("
            SELECT j.id, j.jam, j.tipe, j.keterangan, j.durasi_bunyi, j.id_suara,
                   s.file_path AS suara
            FROM jadwal_bel j
            LEFT JOIN suara_bel s ON s.id = j.id_suara
            WHERE j.id_kategori = ? AND j.hari = ? AND j.aktif = 1
            ORDER BY j.jam ASC");
        $stmt->bind_param('ii', $kategoriEfektif['id'], $kodeHari);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as $r) {
            $suaraPath = $r['suara'] ?: ($suaraDefault ? $suaraDefault['file_path'] : null);
            $jadwal[] = [
                'id'           => (int) $r['id'],
                'jam'          => substr($r['jam'], 0, 5),
                'tipe'         => $r['tipe'],
                'tipe_label'   => tipe_bel_label($r['tipe']),
                'keterangan'   => $r['keterangan'],
                'id_suara'     => $r['id_suara'] ? (int) $r['id_suara'] : 0,
                'suara'        => $suaraPath,
                'durasi_bunyi' => (int) $r['durasi_bunyi'],
            ];
        }
    }

    // Teks tanggal Bahasa Indonesia: "Sabtu, 09 Agustus 2026"
    $bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $tanggalTeks = hari_label($kodeHari) . ', ' . (int) date('d', strtotime($tanggal))
                 . ' ' . $bulanNama[(int) date('m', strtotime($tanggal))]
                 . ' ' . date('Y', strtotime($tanggal));

    echo json_encode([
        'status'           => 'ok',
        'server_now_ms'    => $nowMs,
        'tanggal'          => $tanggal,
        'tanggal_teks'     => $tanggalTeks,
        'hari_kode'        => $kodeHari,
        'hari_nama'        => hari_label($kodeHari),
        'mode'             => $mode,
        'libur'            => (bool) $libur,
        'libur_keterangan' => $libur ? $libur['keterangan'] : '',
        'libur_pengganti'  => $liburPengganti,
        'kategori'         => $kategoriEfektif ? [
            'id'    => (int) $kategoriEfektif['id'],
            'nama'  => $kategoriEfektif['nama'],
            'warna' => $kategoriEfektif['warna'],
        ] : null,
        'jadwal'           => $jadwal,
        'volume_default'   => (int) setting('volume_default', 80),
        'toleransi_lag'    => (int) setting('toleransi_lag', 1),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
}
