<?php

/**
 * Bell Sekolah — Halaman Backup & Restore.
 * Menampilkan daftar backup (dari tabel backup_files + file .json di folder
 * backups/), tombol buat backup manual, unduh, hapus, dan form restore
 * (dropdown pilih file + radio konfirmasi).
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

require_once __DIR__ . '/backup-functions.php';

// Daftar backup dari database (terbaru di atas)
$res     = conn()->query("SELECT id, nama_file, ukuran_bytes, tipe, created_at FROM backup_files ORDER BY created_at DESC, id DESC");
$daftar  = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$adaDiDb = [];
foreach ($daftar as $b) {
    $adaDiDb[$b['nama_file']] = true;
}

// Pindai folder backups/ untuk file .json yang tidak tercatat di database
$fileYatim = [];
$dir       = backup_root_dir();
if (is_dir($dir)) {
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        $nama = basename($file);
        if (!isset($adaDiDb[$nama])) {
            $fileYatim[] = [
                'nama'      => $nama,
                'ukuran'    => (int) filesize($file),
                'mtime'     => filemtime($file),
            ];
        }
    }
    usort($fileYatim, function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });
}

// Info backup otomatis
$autoAktif  = setting('backup_otomatis_aktif', '0') === '1';
$periode    = setting('backup_periode', 'weekly');
$labelPeriode = [
    'daily'   => 'Harian',
    'weekly'  => 'Mingguan',
    'monthly' => 'Bulanan',
];
$jumlahSimpan = (int) setting('backup_jumlah_simpan', 10);

/** Format ukuran file (B / KB / MB). */
function format_ukuran_backup($bytes) {
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        return '&mdash;';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

$judul = 'Backup & Restore';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="space-y-6">

    <!-- Info backup otomatis + tombol manual -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-6 shadow">
        <div>
            <h3 class="text-lg font-semibold text-slate-800">Backup Data</h3>
            <p class="mt-1 text-sm text-slate-500">
                Backup menyimpan seluruh data aplikasi (jadwal, suara, kategori, hari libur, pengaturan, pengguna) ke file JSON di folder <code class="rounded bg-gray-100 px-1">backups/</code>.
            </p>
            <p class="mt-2 text-sm text-slate-600">
                Backup otomatis:
                <?php if ($autoAktif): ?>
                    <span class="inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktif</span>
                    <span class="text-xs text-slate-500">(periode <?= e($labelPeriode[$periode] ?? $periode) ?>, simpan <?= (int) $jumlahSimpan > 0 ? (int) $jumlahSimpan . ' backup terakhir' : 'semua' ?>)</span>
                <?php else: ?>
                    <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Nonaktif</span>
                <?php endif; ?>
            </p>
        </div>
        <form method="post" action="buat.php">
            <?= csrf_field() ?>
            <button type="submit"
                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg> Buat Backup Sekarang (Manual)
            </button>
        </form>
    </div>

    <!-- Tabel daftar backup -->
    <div class="overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-slate-500">
                        <th class="px-4 py-3 font-medium">Nama File</th>
                        <th class="px-4 py-3 font-medium">Dibuat</th>
                        <th class="px-4 py-3 font-medium">Ukuran</th>
                        <th class="px-4 py-3 font-medium">Tipe</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $b): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= e($b['nama_file']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e(date('d M Y H:i:s', strtotime($b['created_at']))) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= format_ukuran_backup($b['ukuran_bytes']) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($b['tipe'] === 'otomatis'): ?>
                                    <span class="inline-block rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">Otomatis</span>
                                <?php else: ?>
                                    <span class="inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="download.php?id=<?= (int) $b['id'] ?>"
                                       class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-gray-100">
                                        Unduh
                                    </a>
                                    <form method="post" action="hapus_backup.php"
                                          onsubmit="return confirm('Hapus backup ini? Tindakan ini tidak dapat dibatalkan.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit"
                                                class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($daftar)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                Belum ada backup. Klik <b>Buat Backup Sekarang (Manual)</b> untuk membuat backup pertama.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- File di folder backups/ yang tidak tercatat di database -->
    <?php if (!empty($fileYatim)): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 shadow-sm">
            <h4 class="text-sm font-semibold text-amber-800">File backup di folder backups/ yang tidak tercatat di database</h4>
            <p class="mt-1 text-xs text-amber-700">
                File ini ada di disk tapi tidak punya catatan di tabel <code>backup_files</code> (mis. disalin manual).
                Untuk keamanan, hanya file yang tercatat di database yang bisa diunduh/direstore.
            </p>
            <ul class="mt-3 space-y-1 text-xs text-amber-800">
                <?php foreach ($fileYatim as $f): ?>
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-mono"><?= e($f['nama']) ?></span>
                        <span class="text-amber-600">(<?= format_ukuran_backup($f['ukuran']) ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form restore -->
    <div class="rounded-xl bg-white p-6 shadow">
        <h3 class="text-lg font-semibold text-slate-800">Pulihkan Data (Restore)</h3>
        <p class="mt-1 text-sm text-slate-500">
            Memulihkan seluruh data dari file backup terpilih. <b>Data saat ini akan ditimpa</b> — pastikan Anda telah membuat
            backup terbaru bila ragu.
        </p>

        <?php if (empty($daftar)): ?>
            <p class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-slate-500">
                Tidak ada backup yang bisa direstore. Buat backup terlebih dahulu.
            </p>
        <?php else: ?>
            <form method="post" action="restore.php" class="mt-4 space-y-4"
                  onsubmit="return confirm('Data saat ini akan ditimpa oleh backup terpilih. Lanjutkan restore?');">
                <?= csrf_field() ?>

                <div>
                    <label for="pilih_backup" class="mb-1 block text-sm font-medium text-slate-700">Pilih File Backup</label>
                    <select id="pilih_backup" name="id" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                        <option value="">— Pilih file backup —</option>
                        <?php foreach ($daftar as $b): ?>
                            <option value="<?= (int) $b['id'] ?>">
                                <?= e($b['nama_file']) ?> · <?= e(date('d M Y H:i', strtotime($b['created_at']))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <input type="radio" name="konfirmasi" value="ya" required
                           class="h-4 w-4 accent-red-600">
                    Ya, saya yakin — timpa data saat ini dengan backup yang dipilih.
                </label>

                <button type="submit"
                        class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                    Pulihkan Sekarang
                </button>
            </form>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
