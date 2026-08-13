<?php

/**
 * Bell Sekolah — Konfirmasi Hapus Kategori Jadwal.
 * Menampilkan form POST konfirmasi yang dikirim ke process.php (aksi=hapus).
 * Kategori default (is_default=1) tidak dapat dihapus.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);

// Ambil data kategori yang akan dihapus
$stmt = conn()->prepare("SELECT id, nama, keterangan, is_default, is_active FROM kategori_jadwal WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$kategori = $stmt->get_result()->fetch_assoc();

if (!$kategori) {
    set_flash('error', 'Kategori tidak ditemukan.');
    redirect('index.php');
}

// Hitung jumlah jadwal yang ikut terhapus (ON DELETE CASCADE)
$stmt = conn()->prepare("SELECT COUNT(*) FROM jadwal_bel WHERE id_kategori = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$jumlahJadwal = (int) $stmt->get_result()->fetch_row()[0];

$bisaDihapus = (int) $kategori['is_default'] !== 1;

$judul = 'Hapus Kategori Jadwal';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-xl">
    <div class="rounded-xl bg-white p-6 shadow">
        <?php if ($bisaDihapus): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Anda akan menghapus kategori <b><?= e($kategori['nama']) ?></b>.
                Seluruh jadwal bel pada kategori ini (<?= $jumlahJadwal ?> jadwal) juga akan ikut terhapus
                dan tidak dapat dikembalikan.
                <?php if ((int) $kategori['is_active'] === 1): ?>
                    <br>Kategori ini sedang <b>aktif</b> — setelah dihapus tidak ada kategori yang aktif.
                <?php endif; ?>
            </div>

            <form method="post" action="process.php" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $kategori['id'] ?>">

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                        Ya, Hapus Kategori
                    </button>
                    <a href="index.php" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-gray-100">
                        Batal
                    </a>
                </div>
            </form>
        <?php else: ?>
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Kategori <b><?= e($kategori['nama']) ?></b> adalah kategori <b>default</b> bawaan
                aplikasi dan tidak dapat dihapus.
            </div>
            <a href="index.php" class="mt-4 inline-block rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-gray-100"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Kembali ke Daftar</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
