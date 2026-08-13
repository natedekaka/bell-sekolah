<?php

/**
 * Bell Sekolah — API pencatat log bunyi bel.
 *
 * Bebas login (tanpa CSRF). Hanya menerima metode POST (JSON atau form).
 * Dedupe: bila sudah ada log OTOMATIS untuk id_jadwal yang sama dalam
 * 3 menit terakhir, tidak dibuat baris baru — balas {"status":"dup"}.
 *
 * Request: { jenis: 'otomatis'|'manual'|'darurat', id_jadwal?, id_suara?,
 *            keterangan?, waktu? }
 * Response ok: { status:'ok', sinyal:{ id, jenis, id_jadwal, id_suara,
 *                waktu, keterangan, suara, durasi_bunyi } }
 */

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/** Bangun objek sinyal dari baris log_bel (lengkap dengan suara & durasi). */
function sinyal_dari_log($conn, $logId) {
    $suaraDefault = cari_suara_default();
    $stmt = $conn->prepare("
        SELECT l.id, l.jenis, l.id_jadwal, l.id_suara, l.keterangan, l.waktu,
               s.file_path AS suara, j.durasi_bunyi
        FROM log_bel l
        LEFT JOIN suara_bel s ON s.id = l.id_suara
        LEFT JOIN jadwal_bel j ON j.id = l.id_jadwal
        WHERE l.id = ?
        LIMIT 1");
    $stmt->bind_param('i', $logId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if (!$r) return null;

    return [
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'pesan' => 'Metode tidak diizinkan.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

try {
    $conn = conn();

    $jenis      = $input['jenis'] ?? '';
    $idJadwal   = (int) ($input['id_jadwal'] ?? 0);
    $idSuara    = (int) ($input['id_suara'] ?? 0);
    $keterangan = trim((string) ($input['keterangan'] ?? ''));

    if (!in_array($jenis, ['otomatis', 'manual', 'darurat'], true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'pesan' => 'Jenis log tidak valid.']);
        exit;
    }

    // Waktu bunyi: default sekarang; boleh dikirim eksplisit (format Y-m-d H:i[:s])
    $waktu = null;
    if (!empty($input['waktu'])) {
        $w = (string) $input['waktu'];
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $w) === 1) {
            $ts = strtotime($w);
            if ($ts !== false) {
                $waktu = date('Y-m-d H:i:s', $ts);
            }
        }
    }
    if ($waktu === null) {
        $waktu = date('Y-m-d H:i:s');
    }

    // Dedupe: log otomatis untuk id_jadwal sama dalam 3 menit -> dup
    if ($jenis === 'otomatis' && $idJadwal > 0) {
        $stmt = $conn->prepare("
            SELECT id FROM log_bel
            WHERE jenis = 'otomatis' AND id_jadwal = ?
              AND waktu >= (NOW() - INTERVAL 3 MINUTE)
            LIMIT 1");
        $stmt->bind_param('i', $idJadwal);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            echo json_encode(['status' => 'dup']);
            exit;
        }
    }

    // Kolom NULL-able diberi null bila nilainya 0 (menghindari FK tidak valid)
    $idJadwalDb = $idJadwal > 0 ? $idJadwal : null;
    $idSuaraDb  = $idSuara > 0 ? $idSuara : null;

    $stmt = $conn->prepare("
        INSERT INTO log_bel (waktu, jenis, id_jadwal, id_suara, keterangan, diputar)
        VALUES (?, ?, ?, ?, ?, 0)");
    $stmt->bind_param('ssiss', $waktu, $jenis, $idJadwalDb, $idSuaraDb, $keterangan);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'pesan' => 'Gagal menyimpan log: ' . $conn->error]);
        exit;
    }

    $logId  = $conn->insert_id;
    $sinyal = sinyal_dari_log($conn, $logId);

    echo json_encode(['status' => 'ok', 'sinyal' => $sinyal], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
}
