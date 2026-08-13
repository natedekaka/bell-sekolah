<?php

/**
 * Bell Sekolah — Daftar Kategori Jadwal.
 * Menampilkan tabel kategori beserta status aktif, jumlah jadwal,
 * dan tombol aksi (Aktifkan, Edit, Hapus).
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Ambil semua kategori beserta jumlah jadwal masing-masing.
// Query statis tanpa variabel user, jadi aman dipakai via query().
$result = conn()->query("
    SELECT k.id, k.nama, k.keterangan, k.warna, k.is_default, k.is_active,
           (SELECT COUNT(*) FROM jadwal_bel j WHERE j.id_kategori = k.id) AS jumlah_jadwal
    FROM kategori_jadwal k
    ORDER BY k.is_active DESC, k.id ASC
");
$kategoriList = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Cari kategori yang sedang aktif untuk banner pemberitahuan
$kategoriAktif = null;
foreach ($kategoriList as $k) {
    if ((int) $k['is_active'] === 1) {
        $kategoriAktif = $k;
        break;
    }
}

$judul = 'Kategori Jadwal';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">
            Kelola kategori jadwal bel. Hanya satu kategori yang aktif dalam satu waktu.
        </p>
        <a href="tambah.php"
           class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
            + Tambah Kategori
        </a>
    </div>

    <?php if ($kategoriAktif): ?>
        <div class="flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
            <span class="inline-block h-4 w-4 rounded-full border border-white" style="background:<?= e($kategoriAktif['warna']) ?>"></span>
            Kategori yang sedang <b>aktif</b>: <b><?= e($kategoriAktif['nama']) ?></b>
        </div>
    <?php else: ?>
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Belum ada kategori aktif. Pilih salah satu kategori untuk diaktifkan.
        </div>
    <?php endif; ?>

    <div class="overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-slate-500">
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Keterangan</th>
                        <th class="px-4 py-3 font-medium">Warna</th>
                        <th class="px-4 py-3 font-medium">Status Aktif</th>
                        <th class="px-4 py-3 text-center font-medium">Jumlah Jadwal</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kategoriList as $k): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-slate-800">
                                <?= e($k['nama']) ?>
                                <?php if ((int) $k['is_default'] === 1): ?>
                                    <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">default</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= e($k['keterangan']) ?: '—' ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-2">
                                    <span class="inline-block h-5 w-5 rounded-full border border-gray-300" style="background:<?= e($k['warna']) ?>"></span>
                                    <span class="font-mono text-xs text-slate-500"><?= e($k['warna']) ?></span>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ((int) $k['is_active'] === 1): ?>
                                    <span class="inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktif</span>
                                <?php else: ?>
                                    <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-600"><?= (int) $k['jumlah_jadwal'] ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if ((int) $k['is_active'] !== 1): ?>
                                        <form method="post" action="aktifkan.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                            <button type="submit"
                                                    class="rounded-lg border border-green-300 bg-green-50 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-100 transition">
                                                Aktifkan
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="edit.php?id=<?= (int) $k['id'] ?>"
                                       class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-gray-100 transition">
                                        Edit
                                    </a>
                                    <a href="hapus.php?id=<?= (int) $k['id'] ?>"
                                       class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50 transition">
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($kategoriList)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                Belum ada kategori jadwal. Klik <b>+ Tambah Kategori</b> untuk membuat kategori pertama.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
