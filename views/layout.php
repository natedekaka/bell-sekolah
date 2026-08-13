<?php
/**
 * Layout admin — partial yang di-include dari halaman modul.
 * Cara pakai:
 *   $judul = 'Judul Halaman';
 *   require_once __DIR__ . '/../views/layout.php';  // (sesuaikan kedalaman)
 *   ...konten...
 *   require_once __DIR__ . '/../views/footer.php';
 */
if (!isset($judul)) $judul = 'Bell Sekolah';
require_login();
$userAktif = current_user();

// Prefix relatif menuju root proyek (dihitung dari kedalaman script aktif)
$dirCurrent = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$rootPrefix = '';
if ($dirCurrent !== '/' && $dirCurrent !== '.' && $dirCurrent !== '') {
    $depth = substr_count(trim($dirCurrent, '/'), '/') + 1;
    $rootPrefix = str_repeat('../', $depth);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($judul) ?> — Bell Sekolah</title>
    <link rel="stylesheet" href="<?= $rootPrefix ?>assets/css/app.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230f172a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9'/><path d='M10.3 21a1.94 1.94 0 0 0 3.4 0'/></svg>">
    <style>
        /* Konten dinamis agar sidebar tetap di kiri */
        .konten-utama { margin-left: 16rem; }
        @media (max-width: 768px) { .konten-utama { margin-left: 0; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<!-- Sidebar -->
<aside class="fixed inset-y-0 left-0 w-64 bg-slate-900 text-white flex flex-col z-20">
    <div class="p-4 border-b border-slate-700">
        <h1 class="text-xl font-bold flex items-center gap-2"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Bell Sekolah</h1>
        <p class="text-xs text-slate-400 mt-1">Sistem Bel Otomatis</p>
    </div>
    <nav class="flex-1 overflow-y-auto py-3">
        <a href="<?= $rootPrefix ?>index.php" class="block px-4 py-2 text-sm hover:bg-slate-800 <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'bg-slate-800 text-white' : 'text-slate-300' ?>"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg> Dashboard</a>
        <?php if (is_admin()): ?>
            <p class="px-4 pt-4 pb-1 text-xs uppercase tracking-wider text-slate-500">Jadwal</p>
            <a href="<?= $rootPrefix ?>admin/jadwal/index.php" class="block px-4 py-2 text-sm hover:bg-slate-800 <?= strpos($_SERVER['SCRIPT_NAME'], 'admin/jadwal') !== false ? 'bg-slate-800 text-white' : 'text-slate-300' ?>"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Jadwal Bel</a>
            <a href="<?= $rootPrefix ?>admin/kategori/index.php" class="block px-4 py-2 text-sm hover:bg-slate-800 <?= strpos($_SERVER['SCRIPT_NAME'], 'admin/kategori') !== false ? 'bg-slate-800 text-white' : 'text-slate-300' ?>"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg> Kategori Jadwal</a>
            <a href="<?= $rootPrefix ?>admin/suara/index.php" class="block px-4 py-2 text-sm hover:bg-slate-800 <?= strpos($_SERVER['SCRIPT_NAME'], 'admin/suara') !== false ? 'bg-slate-800 text-white' : 'text-slate-300' ?>"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg> Kelola Suara</a>
            <p class="px-4 pt-4 pb-1 text-xs uppercase tracking-wider text-slate-500">Pengaturan</p>
            <a href="<?= $rootPrefix ?>admin/hari_libur/index.php" class="block px-4 py-2 text-sm hover:bg-slate-800 <?= strpos($_SERVER['SCRIPT_NAME'], 'admin/hari_libur') !== false ? 'bg-slate-800 text-white' : 'text-slate-300' ?>"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg> Hari Libur</a>
            <a href="<?= $rootPrefix ?>admin/pengaturan/index.php" class="block px-4 py-2 text-sm hover:bg-slate-800 <?= strpos($_SERVER['SCRIPT_NAME'], 'admin/pengaturan') !== false ? 'bg-slate-800 text-white' : 'text-slate-300' ?>"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg> Pengaturan</a>
            <a href="<?= $rootPrefix ?>admin/backup/index.php" class="block px-4 py-2 text-sm hover:bg-slate-800 <?= strpos($_SERVER['SCRIPT_NAME'], 'admin/backup') !== false ? 'bg-slate-800 text-white' : 'text-slate-300' ?>"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg> Backup & Restore</a>
        <?php endif; ?>
        <?php if (is_operator() || is_admin()): ?>
            <p class="px-4 pt-4 pb-1 text-xs uppercase tracking-wider text-slate-500">Pemutar</p>
            <a href="<?= $rootPrefix ?>player.php" target="_blank" class="block px-4 py-2 text-sm hover:bg-slate-800 text-slate-300"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg> Layar Pemutar (Kiosk)</a>
        <?php endif; ?>
    </nav>
    <div class="p-4 border-t border-slate-700 text-sm">
        <p class="text-slate-300"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= e($userAktif['nama_lengkap'] ?: $userAktif['username']) ?>
            <span class="text-xs text-slate-500">(<?= $userAktif['role'] ?>)</span>
        </p>
        <a href="<?= $rootPrefix ?>logout.php" class="mt-2 inline-block text-red-400 hover:text-red-300">Keluar <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
    </div>
</aside>

<!-- Konten -->
<div class="konten-utama min-h-screen flex flex-col">
    <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800"><?= e($judul) ?></h2>
        <div class="text-sm text-gray-500" id="jam-header">--:--:--</div>
    </header>
    <main class="flex-1 p-6">
        <?= flash_html() ?>