<?php

/**
 * Bell Sekolah — Helfer bersama (auth, csrf, flash, pengaturan, upload).
 * Semua halaman meng-include file ini setelah core/Database.php.
 * Pastikan untuk memanggil session_start() di file pertama yang di-include
 * (core/init.php), jangan di sini.
 */

// ------------------------------------------------------------
// Sesi & autentikasi
// ------------------------------------------------------------

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function current_user_role() {
    return $_SESSION['user_role'] ?? '';
}

function is_admin() {
    return current_user_role() === 'admin';
}

function is_operator() {
    return current_user_role() === 'operator';
}

/** Prefix relatif menuju root aplikasi (untuk redirect antar direktori). */
function root_prefix() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '';
    }
    return str_repeat('../', substr_count(trim($dir, '/'), '/') + 1);
}

/** Redirect ke login jika belum login. */
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . root_prefix() . 'login.php');
        exit;
    }
}

/** Redirect ke dashboard jika role tidak mencukupi. */
function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: ' . root_prefix() . 'index.php');
        exit;
    }
}

function do_login($user) {
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user']      = $user;
    $_SESSION['user_role'] = $user['role'];
    // lebih aman regenerasi id sesi
    session_regenerate_id(true);
}

function do_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

/** Gunakan di awal setiap handler POST; redirect balik (dengan pesan) bila gagal. */
function require_csrf($redirect = null) {
    if (!verify_csrf()) {
        set_flash('error', 'Kode keamanan (CSRF) tidak valid. Silakan coba lagi.');
        header('Location: ' . ($redirect ?? $_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}

// ------------------------------------------------------------
// Flash message
// ------------------------------------------------------------

function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flash_html() {
    $f = get_flash();
    if (!$f) return '';
    $colors = [
        'success' => 'bg-green-100 text-green-800 border-green-300',
        'error'   => 'bg-red-100 text-red-800 border-red-300',
        'warning' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'info'    => 'bg-blue-100 text-blue-800 border-blue-300',
    ];
    $cls = $colors[$f['type']] ?? $colors['info'];
    return '<div class="mb-4 rounded-lg border px-4 py-3 ' . $cls . '">'
        . htmlspecialchars($f['message'], ENT_QUOTES, 'UTF-8') . '</div>';
}

// ------------------------------------------------------------
// Pengaturan (key-value dalam tabel pengaturan)
// ------------------------------------------------------------

function settings($key = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $result = conn()->query("SELECT kunci, nilai FROM pengaturan");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $cache[$row['kunci']] = $row['nilai'];
            }
        }
    }
    if ($key === null) return $cache;
    return $cache[$key] ?? null;
}

function setting($key, $default = null) {
    $val = settings($key);
    return ($val === null || $val === '') ? $default : $val;
}

function save_setting($key, $value) {
    $stmt = conn()->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    $stmt->bind_param('ss', $key, $value);
    return $stmt->execute();
}

// ------------------------------------------------------------
// Suara bel
// ------------------------------------------------------------

/** Ambil suara default; fallback ke suara pertama bila tidak ada yang bertanda default. */
function cari_suara_default() {
    $conn = conn();
    $r = $conn->query("SELECT * FROM suara_bel WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
    $s = $r ? $r->fetch_assoc() : null;
    if (!$s) {
        $r2 = $conn->query("SELECT * FROM suara_bel ORDER BY id ASC LIMIT 1");
        $s = $r2 ? $r2->fetch_assoc() : null;
    }
    return $s;
}

/** Path relatif MP3 pengumuman, deterministic dari teks keterangan. */
function path_pengumuman($keterangan) {
    return 'uploads/pengumuman/' . md5(trim((string) $keterangan)) . '.mp3';
}

/** Generate MP3 pengumuman via edge-tts. Mengembalikan path relatif bila berhasil, null bila gagal. */
function generate_pengumuman($keterangan) {
    $keterangan = trim((string) $keterangan);
    if ($keterangan === '') return null;

    $rel  = path_pengumuman($keterangan);
    $root = dirname(__DIR__);

    if (is_file($root . '/' . $rel) && filesize($root . '/' . $rel) > 0) {
        return $rel; // sudah pernah digenerate
    }

    $dir = $root . '/uploads/pengumuman';
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) return null;

    $script = $root . '/scripts/tts_pengumuman.py';
    $cmd = sprintf(
        'python3 %s %s %s 2>&1',
        escapeshellarg($script),
        escapeshellarg($keterangan),
        escapeshellarg($root . '/' . $rel)
    );
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    return ($code === 0 && is_file($root . '/' . $rel) && filesize($root . '/' . $rel) > 0) ? $rel : null;
}

/** URL relatif MP3 pengumuman bila file sudah digenerate; null bila belum ada. */
function url_pengumuman($keterangan) {
    $keterangan = trim((string) $keterangan);
    if ($keterangan === '') return null;
    $rel  = path_pengumuman($keterangan);
    $root = dirname(__DIR__);
    return is_file($root . '/' . $rel) && filesize($root . '/' . $rel) > 0 ? $rel : null;
}

// ------------------------------------------------------------
// Utility UI sederhana (tanpa dependency)
// ------------------------------------------------------------

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/** Nama hari waktu PHP: 1=Senin .. 7=Minggu. Konversi ke kode aplikasi 1..6 (Senin-Sabtu). */
function hari_php_to_app($w = null) {
    $w = $w ?? (int) date('N');
    return ($w >= 1 && $w <= 6) ? $w : 0; // 0 = Minggu (libur)
}

function hari_label($kode) {
    $nama = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    return $nama[$kode] ?? '';
}

function tipe_bel_label($tipe) {
    $map = [
        'masuk'     => 'Masuk',
        'ganti_jam' => 'Ganti Jam',
        'istirahat' => 'Istirahat',
        'sholat'    => 'Sholat',
        'pulang'    => 'Pulang',
        'reses'     => 'Reses',
        'kustom'    => 'Kustom',
    ];
    return $map[$tipe] ?? $tipe;
}

// ------------------------------------------------------------
// Upload audio bel
// ------------------------------------------------------------

const ALLOWED_AUDIO_EXT = ['mp3', 'wav', 'ogg'];
const UPLOAD_DIR = 'uploads/bel';

/**
 * Simpan file audio upload ke uploads/bel.
 * @return array [sukses(bool), file_path|error]
 */
function simpan_upload_audio($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return [false, 'Tidak ada file yang diunggah.'];
    }
    $maxSize = (int) (setting('max_upload_mb', 20)) * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return [false, 'Ukuran file melebihi batas (' . setting('max_upload_mb', 20) . ' MB).'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_AUDIO_EXT)) {
        return [false, 'Tipe file tidak diizinkan. Gunakan MP3, WAV, atau OGG.'];
    }
    if ($file['type'] !== '' && strpos($file['type'], 'audio/') !== 0) {
        return [false, 'File bukan audio.'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $nama = 'bel_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $path = UPLOAD_DIR . '/' . $nama;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return [false, 'Gagal menyimpan file audio.'];
    }
    return [true, $path];
}

/** Simpan audio hasil rekaman (berupa base64 data URI) ke uploads/bel. */
function simpan_rekaman_audio($dataUri, $namaDefault = 'rekaman') {
    if (preg_match('#^data:audio/(\w+);base64,(.+)$#', $dataUri, $m)) {
        $ext   = strtolower($m[1]);
        $data  = base64_decode($m[2]);
        if (!in_array($ext, ['mp3', 'wav', 'ogg', 'webm'])) {
            $ext = 'webm'; // fallback untuk perekam browser
        }
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $file = 'rekaman_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = UPLOAD_DIR . '/' . $file;
        if (file_put_contents($path, $data) === false) {
            return [false, 'Gagal menyimpan hasil rekaman.'];
        }
        return [true, $path];
    }
    return [false, 'Data rekaman tidak valid.'];
}