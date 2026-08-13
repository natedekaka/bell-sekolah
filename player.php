<?php

/**
 * Bell Sekolah — Layar Pemutar (Kiosk) mode penuh layar.
 *
 * Halaman ini TANPA kunci login untuk dilihat (cocok untuk TV/monitor
 * kiosk di sekolah). Tombol "Bel Manual" hanya muncul bila sesi sudah
 * login; tombol "Bel Darurat" selalu tampil.
 *
 * Konfigurasi pemutar (volume_default, toleransi_lag, kategori_hari_libur)
 * dibaca dari tabel pengaturan via helper setting().
 *
 * Seluruh logika waktu/bunyi dijalankan oleh assets/js/player.js yang
 * berkomunikasi dengan API JSON di folder api/.
 */

require_once __DIR__ . '/core/init.php';

$volumeDefault   = (int) setting('volume_default', 80);
$toleransiLag    = (int) setting('toleransi_lag', 1);
$kategoriLibur   = (int) setting('kategori_hari_libur', 0);
$pengumumanAktif = setting('pengumuman_aktif', '1') === '1';
$chimeUrl        = is_file(__DIR__ . '/uploads/bel/bel_chime.wav') ? 'uploads/bel/bel_chime.wav' : null;
$loggedIn        = is_logged_in();

// Suara default: dipakai sebagai fallback bunyi sekaligus pembuka kunci audio
$suaraDefault = null;
$stmt = conn()->prepare("SELECT * FROM suara_bel WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
$stmt->execute();
$suaraDefault = $stmt->get_result()->fetch_assoc();
if (!$suaraDefault) {
    $res = conn()->query("SELECT * FROM suara_bel ORDER BY id ASC LIMIT 1");
    $suaraDefault = $res ? $res->fetch_assoc() : null;
}

header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Layar Pemutar — Bell Sekolah</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230f172a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9'/><path d='M10.3 21a1.94 1.94 0 0 0 3.4 0'/></svg>">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; }
    body {
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: radial-gradient(1200px 800px at 50% -10%, #1e293b 0%, #0f172a 55%, #020617 100%);
        color: #e2e8f0;
        overflow: hidden;
        user-select: none;
        -webkit-user-select: none;
    }
    #layar { height: 100vh; display: flex; flex-direction: column; padding: 1rem 1.5rem 1rem; }
    #bar-atas { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; }
    .koneksi { display: inline-flex; align-items: center; gap: .4rem; font-size: .75rem; color: #64748b; margin-left: .2rem; }
    .titik { width: .55rem; height: .55rem; border-radius: 50%; background: #ef4444; }
    .titik.ok { background: #22c55e; animation: kedip 2s infinite; }
    @keyframes kedip { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
    #tombol-aksi { margin-left: auto; display: flex; gap: .6rem; }
    .tombol {
        border: 0; border-radius: .75rem; padding: .6rem 1.1rem; font-weight: 700;
        font-size: .9rem; color: #fff; cursor: pointer; font-family: inherit;
        transition: transform .08s ease, filter .15s ease;
    }
    .tombol:active { transform: scale(.96); }
    .tombol-manual { background: #2563eb; }
    .tombol-manual:hover { filter: brightness(1.12); }
    .tombol-darurat { background: #dc2626; animation: pulsa 1.5s infinite; }
    .tombol-darurat:hover { filter: brightness(1.15); }
    .tombol-layar-penuh { background: #334155; }
    .tombol-layar-penuh:hover { background: #475569; }
    @keyframes pulsa {
        0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, .55); }
        50% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
    }
    #tengah { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .35rem; text-align: center; min-height: 0; }
    #jam-besar {
        font-size: clamp(5rem, 16vw, 13rem); font-weight: 800; line-height: 1;
        font-variant-numeric: tabular-nums; letter-spacing: .02em; color: #f8fafc;
        text-shadow: 0 0 80px rgba(37, 99, 235, .35);
    }
    #tanggal-info { font-size: clamp(1rem, 2.4vw, 1.5rem); color: #94a3b8; display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; justify-content: center; }
    #kategori-chip { display: inline-block; padding: .2rem .8rem; border-radius: 9999px; font-size: .8rem; font-weight: 700; }
    #countdown-card { margin-top: .9rem; display: flex; flex-direction: column; gap: .1rem; }
    #countdown-label { color: #64748b; font-size: .8rem; text-transform: uppercase; letter-spacing: .18em; }
    #countdown-waktu { font-size: clamp(1.8rem, 5vw, 3.2rem); font-weight: 700; font-variant-numeric: tabular-nums; color: #38bdf8; line-height: 1.1; }
    #countdown-tujuan { color: #94a3b8; font-size: clamp(.85rem, 1.8vw, 1.05rem); }
    #jadwal-card {
        width: min(900px, 98vw); margin: .7rem auto 0; max-height: 30vh;
        background: rgba(255, 255, 255, .04); border: 1px solid rgba(255, 255, 255, .09);
        border-radius: 1rem; padding: .7rem 1rem; overflow-y: auto;
    }
    #jadwal-kartu-judul { font-size: .72rem; color: #64748b; text-transform: uppercase; letter-spacing: .14em; margin-bottom: .45rem; }
    #jadwal-list { list-style: none; }
    .jadwal-item {
        display: flex; align-items: center; gap: .9rem; padding: .42rem .7rem;
        border-radius: .6rem; margin-bottom: .2rem; opacity: .55; border: 1px solid transparent;
        transition: opacity .2s ease, background .2s ease;
    }
    .jadwal-item.lewat { opacity: .25; }
    .jadwal-item.berikutnya { opacity: 1; background: rgba(37, 99, 235, .14); border-color: rgba(37, 99, 235, .32); }
    .jadwal-item.paling-dekat { background: rgba(37, 99, 235, .26); border-color: #3b82f6; }
    .waktu { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 700; font-size: 1.05rem; width: 3.4rem; flex-shrink: 0; }
    .tipe { font-size: .66rem; font-weight: 700; padding: .14rem .55rem; border-radius: 9999px; background: rgba(255, 255, 255, .1); text-transform: capitalize; flex-shrink: 0; }
    .tipe-masuk { color: #22d3ee; }
    .tipe-ganti_jam { color: #a78bfa; }
    .tipe-istirahat { color: #4ade80; }
    .tipe-sholat { color: #fbbf24; }
    .tipe-pulang { color: #f87171; }
    .tipe-reses { color: #f472b6; }
    .tipe-kustom { color: #94a3b8; }
    .ket { color: #cbd5e1; font-size: .92rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kosong { color: #64748b; padding: .5rem .2rem; font-size: .9rem; }
    #kaki { text-align: center; font-size: .7rem; color: #334155; padding-top: .5rem; }
    #jadwal-card::-webkit-scrollbar { width: 6px; }
    #jadwal-card::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    #overlay-bunyi {
        position: fixed; inset: 0; z-index: 50; display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: .9rem; cursor: pointer;
        background: rgba(2, 6, 23, .88); text-align: center; padding: 1rem;
    }
    #overlay-bunyi.tersembunyi { display: none; }
    #overlay-bunyi-ikon { color: #f8fafc; animation: goyang 1s infinite; }
    #overlay-bunyi-ikon svg { width: clamp(4rem, 12vw, 8rem); height: clamp(4rem, 12vw, 8rem); }
    @keyframes goyang { 0%, 100% { transform: rotate(0); } 25% { transform: rotate(-10deg); } 75% { transform: rotate(10deg); } }
    #overlay-bunyi-judul { font-size: clamp(2.2rem, 7vw, 5rem); font-weight: 800; color: #f8fafc; }
    #overlay-bunyi-sub { font-size: clamp(1rem, 3vw, 1.7rem); color: #94a3b8; }
    #overlay-bunyi-close { margin-top: 1rem; font-size: .8rem; color: #475569; }
    #toast {
        position: fixed; bottom: 1.6rem; left: 50%; transform: translateX(-50%); z-index: 60;
        background: #1e293b; color: #f1f5f9; border: 1px solid #475569; border-radius: .6rem;
        padding: .55rem 1.1rem; font-size: .85rem; display: none; max-width: 90vw;
    }
    .status-badge { padding: .3rem .85rem; border-radius: 9999px; font-size: .75rem; font-weight: 700; border: 1px solid transparent; }
    .status-aktif { background: rgba(34, 197, 94, .15); color: #4ade80; border-color: rgba(34, 197, 94, .4); }
    .status-libur { background: rgba(245, 158, 11, .15); color: #fbbf24; border-color: rgba(245, 158, 11, .4); }
    .status-minggu { background: rgba(100, 116, 139, .15); color: #94a3b8; border-color: rgba(100, 116, 139, .4); }
    .status-pengganti { background: rgba(139, 92, 246, .15); color: #c4b5fd; border-color: rgba(139, 92, 246, .4); }
</style>
</head>
<body>

<div id="layar">

    <!-- Bar atas: status, kategori, indikator koneksi, tombol aksi -->
    <div id="bar-atas">
        <span id="status-badge" class="status-badge status-aktif">Memuat…</span>
        <span id="kategori-chip" style="display:none"></span>
        <span class="koneksi">
            <span id="titik-koneksi" class="titik"></span>
            <span id="teks-koneksi">Menghubungkan…</span>
        </span>
        <div id="tombol-aksi">
            <button id="tombol-fullscreen" class="tombol tombol-layar-penuh" type="button" title="Layar penuh / keluar"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg> Layar Penuh</button>
            <?php if ($loggedIn): ?>
                <button id="tombol-manual" class="tombol tombol-manual" type="button"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Bel Manual</button>
            <?php endif; ?>
            <button id="tombol-darurat" class="tombol tombol-darurat" type="button"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 18v-6a5 5 0 1 1 10 0v6"/><path d="M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z"/><path d="M21 12h1"/><path d="M18.5 4.5 18 5"/><path d="M2 12h1"/><path d="M12 2v1"/><path d="m4.929 4.929.707.707"/><path d="M12 12v6"/></svg> Darurat</button>
        </div>
    </div>

    <!-- Tengah: jam raksasa, tanggal, countdown -->
    <div id="tengah">
        <div id="jam-besar">--:--:--</div>
        <div id="tanggal-info">Memuat…</div>
        <div id="countdown-card">
            <span id="countdown-label">Bel berikutnya</span>
            <span id="countdown-waktu">--:--:--</span>
            <span id="countdown-tujuan"></span>
        </div>
    </div>

    <!-- Daftar jadwal hari ini -->
    <div id="jadwal-card">
        <div id="jadwal-kartu-judul">Jadwal Bel Hari Ini</div>
        <ul id="jadwal-list"><li class="kosong">Memuat jadwal…</li></ul>
    </div>

    <p id="kaki">Bell Sekolah · Layar Pemutar Kiosk</p>
</div>

<!-- Elemen audio global; src diatur oleh player.js -->
<audio id="player-bunyi" preload="auto"></audio>

<!-- Overlay notifikasi saat bel berbunyi (ketuk untuk menutup) -->
<div id="overlay-bunyi" class="tersembunyi">
    <div id="overlay-bunyi-ikon"><svg style="display:block;margin:0 auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></div>
    <div id="overlay-bunyi-judul"></div>
    <div id="overlay-bunyi-sub"></div>
    <div id="overlay-bunyi-close">Ketuk di mana saja untuk menutup</div>
</div>

<!-- Toast pesan singkat -->
<div id="toast"></div>

<script>
window.PLAYER_CONFIG = <?= json_encode([
    'apiBase'         => 'api/',
    'volumeDefault'   => $volumeDefault,
    'toleransiLag'    => $toleransiLag,
    'kategoriHariLibur' => $kategoriLibur,
    'loggedIn'        => $loggedIn,
    'csrfToken'       => $loggedIn ? csrf_token() : null,
    'suaraDefault'    => $suaraDefault ? $suaraDefault['file_path'] : null,
    'chimeUrl'        => $chimeUrl,
    'pengumumanAktif' => $pengumumanAktif,
], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/player.js" defer></script>
</body>
</html>
