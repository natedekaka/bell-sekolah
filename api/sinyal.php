<?php

/**
 * Bell Sekolah — API sinyal bel tertunda (recovery).
 *
 * Bebas login (tanpa CSRF). Mengembalikan maksimal 5 log_bel dengan
 * diputar = 0 yang waktunya sudah lewat/saatnya (waktu <= NOW()).
 * Pemutar memainkannya lalu menandai via api/confirm.php.
 */

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $conn = conn();
    $suaraDefault = cari_suara_default();

    $stmt = $conn->prepare("
        SELECT l.id, l.jenis, l.id_jadwal, l.id_suara, l.keterangan, l.waktu,
               s.file_path AS suara, j.durasi_bunyi
        FROM log_bel l
        LEFT JOIN suara_bel s ON s.id = l.id_suara
        LEFT JOIN jadwal_bel j ON j.id = l.id_jadwal
        WHERE l.diputar = 0 AND l.waktu <= NOW()
        ORDER BY l.waktu ASC, l.id ASC
        LIMIT 5");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $sinyal = [];
    foreach ($rows as $r) {
        $sinyal[] = [
            'id'           => (int) $r['id'],
            'jenis'        => $r['jenis'],
            'id_jadwal'    => $r['id_jadwal'] ? (int) $r['id_jadwal'] : null,
            'id_suara'     => $r['id_suara'] ? (int) $r['id_suara'] : 0,
            'waktu'        => $r['waktu'],
            'keterangan'   => $r['keterangan'],
            'suara'        => $r['suara'] ?: ($suaraDefault ? $suaraDefault['file_path'] : null),
            'durasi_bunyi' => $r['durasi_bunyi'] ? (int) $r['durasi_bunyi'] : 10,
            'audio_pengumuman' => url_pengumuman($r['keterangan']),
        ];
    }

    echo json_encode([
        'status'        => 'ok',
        'server_now_ms' => (int) (microtime(true) * 1000),
        'sinyal'        => $sinyal,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
}
