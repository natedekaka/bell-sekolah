<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

// Ambil semua suara, default tampil paling atas
$stmt = conn()->query("SELECT * FROM suara_bel ORDER BY is_default DESC, id ASC");
$suaraList = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];

// Format ukuran file manual (B / KB / MB)
function format_ukuran_file($bytes) {
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

// Label sumber suara
function label_sumber_suara($sumber) {
    $map = [
        'bawaan'  => 'Bawaan',
        'upload'  => 'Upload',
        'rekaman' => 'Rekaman Badge',
    ];
    return $map[$sumber] ?? $sumber;
}

$judul = 'Kelola Suara';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Library Suara Bel</h3>
            <p class="text-sm text-slate-500 mt-1">Kelola file audio bel: unggah, rekam, atau pilih suara default.</p>
        </div>
        <div class="flex gap-2">
            <a href="tambah.php" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                + Upload Suara
            </a>
            <a href="rekam.php" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg> Rekam Suara
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <?php if (empty($suaraList)): ?>
            <p class="text-slate-500 text-sm">Belum ada suara tersedia. Tambahkan lewat tombol di atas.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b">
                            <th class="py-2 pr-4">Nama</th>
                            <th class="py-2 pr-4">Format</th>
                            <th class="py-2 pr-4">Ukuran</th>
                            <th class="py-2 pr-4">Sumber</th>
                            <th class="py-2 pr-4">Default</th>
                            <th class="py-2 pr-4">Preview</th>
                            <th class="py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suaraList as $suara): ?>
                            <?php
                            $ukuran = (int) $suara['ukuran_bytes'];
                            if ($ukuran <= 0 && is_file($suara['file_path'])) {
                                $ukuran = (int) filesize($suara['file_path']);
                            }
                            $isDefault = (int) $suara['is_default'] === 1;
                            $fileUrl = '../../' . ltrim($suara['file_path'], '/');
                            ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 pr-4 font-medium text-slate-700"><?= e($suara['nama']) ?></td>
                                <td class="py-2 pr-4 uppercase text-xs text-slate-600"><?= e($suara['format']) ?></td>
                                <td class="py-2 pr-4 text-slate-600"><?= format_ukuran_file($ukuran) ?></td>
                                <td class="py-2 pr-4">
                                    <?php if ($suara['sumber'] === 'bawaan'): ?>
                                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs">Bawaan</span>
                                    <?php elseif ($suara['sumber'] === 'rekaman'): ?>
                                        <span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs">Rekaman Badge</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs">Upload</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 pr-4">
                                    <?php if ($isDefault): ?>
                                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-xs font-semibold"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg> Default</span>
                                    <?php else: ?>
                                        <span class="text-slate-300 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 pr-4">
                                    <audio controls preload="none" class="h-8" src="<?= e($fileUrl) ?>"></audio>
                                </td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <?php if (!$isDefault): ?>
                                            <form method="post" action="default.php">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $suara['id'] ?>">
                                                <button type="submit"
                                                        class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-medium hover:bg-green-700"
                                                        title="Jadikan suara default">
                                                    Jadikan Default
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="hapus.php"
                                              onsubmit="return confirm('Hapus suara ini? Tindakan ini tidak bisa dibatalkan.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $suara['id'] ?>">
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-medium hover:bg-red-700">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-400">Catatan: file suara bawaan dibuat oleh <code>scripts/generate_suara.py</code> saat pertama kali aplikasi berjalan.</p>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
