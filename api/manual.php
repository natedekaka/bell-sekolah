<?php

/**
 * Bell Sekolah — API bunyi manual / darurat dari layar pemutar.
 *
 * Otentikasi:
 *   - jenis 'manual'  : WAJIB login + header CSRF (X-CSRF-Token).
 *   - jenis 'darurat' : bebas login (tanpa CSRF) — tombol darurat selalu tampil.
 *
 * Rate-limit: maksimal 1 bunyi manual/darurat per 10 detik (dicek dari
 * log_bel terakhir). Bila terlampau cepat balas {"status":"rate_limit"}.
 *
 * Request: { jenis: 'manual' | 'darurat' }
 * Response ok: { status:'ok', sinyal:{ id, jenis, id_jadwal, id_suara,
 *               waktu, keterangan, suara, durasi_bunyi } }
 */

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'pesan' => 'Metode tidak diizinkan.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$jenis = $input['jenis'] ?? '';

// Otentikasi per jenis
if ($jenis === 'manual') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'pesan' => 'Sesi login diperlukan untuk bel manual.']);
        exit;
    }
    if (!verify_csrf()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'pesan' => 'Token CSRF tidak valid.']);
        exit;
    }
} elseif ($jenis !== 'darurat') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'pesan' => 'Jenis bunyi tidak valid (manual/darurat).']);
    exit;
}

try {
    $conn = conn();

    // Rate-limit 10 detik HANYA untuk bel manual (darurat harus selalu bisa berbunyi)
    if ($jenis === 'manual') {
        $stmt = $conn->prepare("
            SELECT TIMESTAMPDIFF(SECOND, waktu, NOW()) AS lalu
            FROM log_bel
            WHERE jenis = 'manual'
              AND waktu >= (NOW() - INTERVAL 15 SECOND)
            ORDER BY id DESC
            LIMIT 1");
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
    } else {
        $r = null;
    }

    if ($r && (int) $r['lalu'] < 10) {
        $tunggu = max(1, 10 - (int) $r['lalu']);
        echo json_encode([
            'status' => 'rate_limit',
            'tunggu' => $tunggu,
            'pesan'  => 'Bel terlalu sering ditekan. Tunggu ' . $tunggu . ' detik lagi.',
        ]);
        exit;
    }

    // Pilih suara: darurat preferensi file bertanda 'darurat'; manual pakai default
    $suara = null;
    if ($jenis === 'darurat') {
        $res = $conn->query("SELECT * FROM suara_bel WHERE file_path LIKE '%darurat%' ORDER BY id ASC LIMIT 1");
        $suara = $res ? $res->fetch_assoc() : null;
        if (!$suara) {
            $suara = cari_suara_default();
        }
        $keterangan = 'Bel darurat ditekan dari layar pemutar';
    } else {
        $suara      = cari_suara_default();
        $keterangan = 'Bel manual ditekan dari layar pemutar';
    }

    if (!$suara) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'pesan' => 'Tidak ada suara bel tersedia.']);
        exit;
    }

    $idSuara = (int) $suara['id'];

    $stmt = $conn->prepare("
        INSERT INTO log_bel (waktu, jenis, id_suara, keterangan, diputar)
        VALUES (NOW(), ?, ?, ?, 0)");
    $stmt->bind_param('sis', $jenis, $idSuara, $keterangan);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'pesan' => 'Gagal menyimpan log: ' . $conn->error]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'sinyal' => [
            'id'           => (int) $conn->insert_id,
            'jenis'        => $jenis,
            'id_jadwal'    => null,
            'id_suara'     => $idSuara,
            'waktu'        => date('Y-m-d H:i:s'),
            'keterangan'   => $keterangan,
            'suara'        => $suara['file_path'],
            'durasi_bunyi' => 10,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
}
