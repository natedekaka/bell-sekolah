<?php

/**
 * Inisialisasi aplikasi — include pertama di semua halaman.
 * Memulai sesi, menghubungkan DB, memuat helper.
 */

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';