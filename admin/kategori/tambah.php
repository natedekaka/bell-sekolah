<?php

/**
 * Bell Sekolah — Form Tambah Kategori Jadwal.
 * Data dikirim via POST ke process.php dengan aksi=tambah.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

$judul = 'Tambah Kategori Jadwal';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-2xl">
    <div class="rounded-xl bg-white p-6 shadow">
        <form method="post" action="process.php" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">

            <div>
                <label for="nama" class="mb-1 block text-sm font-medium text-slate-700">Nama Kategori <span class="text-red-500">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="100" placeholder="cth: Reguler"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                <p class="mt-1 text-xs text-slate-400">Nama kategori harus unik.</p>
            </div>

            <div>
                <label for="keterangan" class="mb-1 block text-sm font-medium text-slate-700">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" maxlength="255" placeholder="cth: Jadwal bel normal hari sekolah"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
            </div>

            <div>
                <label for="warna" class="mb-1 block text-sm font-medium text-slate-700">Warna</label>
                <input type="color" id="warna" name="warna" value="#2563eb"
                       class="h-10 w-20 cursor-pointer rounded-lg border border-gray-300 bg-white p-1">
                <p class="mt-1 text-xs text-slate-400">Warna penanda kategori pada dashboard dan layar pemutar.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Simpan
                </button>
                <a href="index.php" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-gray-100">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
