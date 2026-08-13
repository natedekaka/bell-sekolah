<?php

/**
 * Bell Sekolah — Konfirmasi Hapus Hari Libur.
 * Menampilkan form POST konfirmasi yang dikirim ke process.php (aksi=hapus).
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);

// Ambil data hari libur yang akan dihapus
$stmt = conn()->prepare("SELECT id, tanggal, keterangan FROM hari_libur WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$libur = $stmt->get_result()->fetch_assoc();

if (!$libur) {
    set_flash('error', 'Hari libur tidak ditemukan.');
    redirect('index.php');
}

$judul = 'Hapus Hari Libur';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-xl">
    <div class="rounded-xl bg-white p-6 shadow">
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Anda akan menghapus hari libur
            <b><?= e(date('d M Y', strtotime($libur['tanggal']))) ?></b>
            <?php if ($libur['keterangan'] !== ''): ?>
                (<b><?= e($libur['keterangan']) ?></b>)
            <?php endif; ?>
            dari daftar hari libur. Tindakan ini tidak dapat dibatalkan.
        </div>

        <form method="post" action="process.php" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= (int) $libur['id'] ?>">

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                    Ya, Hapus Hari Libur
                </button>
                <a href="index.php"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-gray-100">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
