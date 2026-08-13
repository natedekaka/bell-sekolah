<?php

/**
 * Bell Sekolah — API konfirmasi sinyal sudah diputar.
 *
 * Bebas login (tanpa CSRF). Menandai log_bel sebagai diputar = 1.
 * Terima { id: 5 } atau { ids: [1, 2, 3] }.
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

$ids = [];
if (isset($input['ids']) && is_array($input['ids'])) {
    foreach ($input['ids'] as $v) {
        $v = (int) $v;
        if ($v > 0) $ids[] = $v;
    }
} elseif (isset($input['id'])) {
    $v = (int) $input['id'];
    if ($v > 0) $ids[] = $v;
}

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'pesan' => 'ID sinyal wajib diisi.']);
    exit;
}

try {
    $conn = conn();
    $dikonfirmasi = 0;
    $stmt = $conn->prepare("UPDATE log_bel SET diputar = 1 WHERE id = ?");

    foreach ($ids as $id) {
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $dikonfirmasi++;
        }
    }

    echo json_encode(['status' => 'ok', 'dikonfirmasi' => $dikonfirmasi]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
}
