<?php

/**
 * Bell Sekolah — Daftar Hari Libur.
 * Menampilkan tabel tanggal libur (urut tanggal terbaru) beserta
 * form tambah inline di atasnya. Aksi tambah dikirim via POST
 * ke process.php, aksi hapus lewat halaman konfirmasi hapus.php.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Ambil seluruh hari libur, tanggal terbaru di atas
$result = conn()->query("
    SELECT id, tanggal, keterangan
    FROM hari_libur
    ORDER BY tanggal DESC, id DESC
");
$daftarLibur = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$judul = 'Hari Libur';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="space-y-6">

    <div>
        <p class="text-sm text-slate-500">
            Kelola tanggal hari libur. Saat tanggal libur tiba, bel otomatis mati
            atau memakai kategori pengganti sesuai pengaturan.
        </p>
    </div>

    <!-- Form tambah inline -->
    <div class="rounded-xl bg-white p-6 shadow">
        <h3 class="text-lg font-semibold text-slate-800">Tambah Hari Libur</h3>
        <form method="post" action="process.php" class="mt-4 flex flex-wrap items-end gap-4">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">

            <div>
                <label for="tanggal" class="mb-1 block text-sm font-medium text-slate-700">Tanggal <span class="text-red-500">*</span></label>
                <input type="date" id="tanggal" name="tanggal" required
                       class="rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
            </div>

            <div class="min-w-0 flex-1">
                <label for="keterangan" class="mb-1 block text-sm font-medium text-slate-700">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" maxlength="255"
                       placeholder="cth: Libur Tahun Baru"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                + Tambah
            </button>
        </form>
    </div>

    <!-- Tabel daftar hari libur -->
    <div class="overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-slate-500">
                        <th class="px-4 py-3 font-medium">Tanggal</th>
                        <th class="px-4 py-3 font-medium">Keterangan</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarLibur as $libur): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-slate-800">
                                    <?= e(date('d M Y', strtotime($libur['tanggal']))) ?>
                                </span>
                                <span class="ml-1 font-mono text-xs text-slate-400"><?= e($libur['tanggal']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= e($libur['keterangan']) ?: '—' ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end">
                                    <a href="hapus.php?id=<?= (int) $libur['id'] ?>"
                                       class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($daftarLibur)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-slate-500">
                                Belum ada hari libur. Gunakan form di atas untuk menambah tanggal libur pertama.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
