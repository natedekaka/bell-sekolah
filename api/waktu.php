<?php

/**
 * Bell Sekolah — API waktu server.
 *
 * Bebas login (tanpa CSRF). Digunakan pemutar untuk mengkalibrasi offset
 * waktu (server_now_ms - Date.now()) saat halaman pertama dimuat.
 */

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    echo json_encode([
        'status'        => 'ok',
        'server_now_ms' => (int) (microtime(true) * 1000),
        'server_waktu'  => date('H:i:s'),
        'tanggal'       => date('Y-m-d'),
        'zona'          => date_default_timezone_get(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
}
