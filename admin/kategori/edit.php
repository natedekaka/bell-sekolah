<?php

/**
 * Bell Sekolah — Form Edit Kategori Jadwal.
 * Data dikirim via POST ke process.php dengan aksi=edit.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);

// Ambil data kategori untuk mengisi form
$stmt = conn()->prepare("SELECT id, nama, keterangan, warna, is_default, is_active FROM kategori_jadwal WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$kategori = $stmt->get_result()->fetch_assoc();

if (!$kategori) {
    set_flash('error', 'Kategori tidak ditemukan.');
    redirect('index.php');
}

$judul = 'Edit Kategori Jadwal';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-2xl">
    <div class="rounded-xl bg-white p-6 shadow">
        <form method="post" action="process.php" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" value="<?= (int) $kategori['id'] ?>">

            <div>
                <label for="nama" class="mb-1 block text-sm font-medium text-slate-700">Nama Kategori <span class="text-red-500">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="100" value="<?= e($kategori['nama']) ?>"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
            </div>

            <div>
                <label for="keterangan" class="mb-1 block text-sm font-medium text-slate-700">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" maxlength="255" value="<?= e($kategori['keterangan']) ?>"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
            </div>

            <div>
                <label for="warna" class="mb-1 block text-sm font-medium text-slate-700">Warna</label>
                <input type="color" id="warna" name="warna" value="<?= e($kategori['warna']) ?>"
                       class="h-10 w-20 cursor-pointer rounded-lg border border-gray-300 bg-white p-1">
            </div>

            <?php if ((int) $kategori['is_default'] === 1): ?>
                <p class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                    <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg> Kategori ini adalah kategori <b>default</b> bawaan aplikasi dan tidak dapat dihapus.
                </p>
            <?php endif; ?>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Simpan Perubahan
                </button>
                <a href="index.php" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-gray-100">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
