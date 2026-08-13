<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

// Filter kategori (opsional): 0 / kosong = tampilkan semua
$adaParamKategori = isset($_GET['kategori']);
$filterKategori   = $adaParamKategori ? (int) $_GET['kategori'] : 0;

// Daftar kategori untuk tab
$stmt = conn()->prepare("SELECT * FROM kategori_jadwal ORDER BY is_active DESC, id ASC");
$stmt->execute();
$daftarKategori = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Kategori aktif (is_active=1) dijadikan pilihan default bila tidak ada parameter kategori di URL
$kategoriAktif = null;
foreach ($daftarKategori as $k) {
    if ($k['is_active']) {
        $kategoriAktif = $k;
        break;
    }
}
if (!$adaParamKategori && $kategoriAktif !== null) {
    $filterKategori = (int) $kategoriAktif['id'];
}

// Query jadwal bel (dengan atau tanpa filter kategori)
if ($filterKategori > 0) {
    $stmt = conn()->prepare("
        SELECT j.*, s.nama AS nama_suara, k.nama AS nama_kategori
        FROM jadwal_bel j
        LEFT JOIN suara_bel s ON s.id = j.id_suara
        LEFT JOIN kategori_jadwal k ON k.id = j.id_kategori
        WHERE j.id_kategori = ?
        ORDER BY j.hari ASC, j.jam ASC, j.id ASC");
    $stmt->bind_param('i', $filterKategori);
} else {
    $stmt = conn()->prepare("
        SELECT j.*, s.nama AS nama_suara, k.nama AS nama_kategori
        FROM jadwal_bel j
        LEFT JOIN suara_bel s ON s.id = j.id_suara
        LEFT JOIN kategori_jadwal k ON k.id = j.id_kategori
        ORDER BY j.hari ASC, j.jam ASC, j.id ASC");
}
$stmt->execute();
$daftarJadwal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Kelompokkan jadwal per hari (query sudah urut oleh jam ASC)
$jadwalPerHari = [];
foreach ($daftarJadwal as $jb) {
    $jadwalPerHari[(int) $jb['hari']][] = $jb;
}

// Nama kategori terpilih untuk subtitle
$namaKategoriTerpilih = null;
foreach ($daftarKategori as $k) {
    if ((int) $k['id'] === $filterKategori) {
        $namaKategoriTerpilih = $k['nama'];
        break;
    }
}

// Sufiks kategori untuk link "Ada" agar tab aktif tetap dipertahankan
$sufKategori = $filterKategori > 0 ? '&kategori=' . $filterKategori : '';

$judul = 'Jadwal Bel';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="space-y-6">

    <!-- Kartu header + tab kategori -->
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-xl font-bold text-slate-800">Manajemen Jadwal Bel</h3>
                <p class="text-sm text-slate-500 mt-1">
                    <?php if ($filterKategori > 0): ?>
                        Menampilkan jadwal kategori <b><?= e($namaKategoriTerpilih ?? '—') ?></b>
                    <?php else: ?>
                        Menampilkan semua kategori
                    <?php endif; ?>
                    · <?= count($daftarJadwal) ?> jadwal
                </p>
            </div>
            <a href="tambah.php<?= $filterKategori > 0 ? '?kategori=' . $filterKategori : '' ?>"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-medium text-white transition">
                <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Tambah Jadwal
            </a>
        </div>

        <!-- Tab kategori: pill berwarna sesuai warna kategori -->
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
            <a href="index.php"
               class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium transition <?= $filterKategori === 0 ? 'bg-slate-800 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                Semua
            </a>
            <?php foreach ($daftarKategori as $k): ?>
                <?php $tabTerpilih = $filterKategori === (int) $k['id']; ?>
                <a href="index.php?kategori=<?= e($k['id']) ?>"
                   class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium transition"
                   style="<?= $tabTerpilih
                       ? 'background:' . e($k['warna']) . ';color:#fff;box-shadow:0 1px 2px rgba(15,23,42,.15)'
                       : 'background:' . e($k['warna']) . '1A;color:' . e($k['warna']) ?>">
                    <span class="inline-block h-2 w-2 rounded-full"
                          style="background:<?= $tabTerpilih ? 'rgba(255,255,255,.9)' : e($k['warna']) ?>"></span>
                    <?= e($k['nama']) ?>
                    <?php if ($k['is_active']): ?>
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide <?= $tabTerpilih ? 'bg-white/25 text-white' : 'bg-emerald-100 text-emerald-700' ?>">aktif</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($daftarKategori)): ?>
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Belum ada kategori jadwal. Buat kategori terlebih dahulu di menu <b>Kategori Jadwal</b> sebelum menambah jadwal bel.
        </div>
    <?php endif; ?>

    <!-- Kartu per hari (Senin–Sabtu) -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <?php for ($h = 1; $h <= 6; $h++): ?>
            <?php $belHari = $jadwalPerHari[$h] ?? []; ?>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-bold text-slate-800"><?= e(hari_label($h)) ?></h4>
                        <?php if (!empty($belHari)): ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                <?= count($belHari) ?> bel
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="tambah.php?hari=<?= $h ?><?= $sufKategori ?>"
                       class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-100">
                        <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        Ada
                    </a>
                </div>

                <div class="space-y-2 p-3">
                    <?php if (empty($belHari)): ?>
                        <p class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-xs text-slate-400">
                            Belum ada bel
                        </p>
                    <?php else: ?>
                        <?php foreach ($belHari as $jb): ?>
                            <div class="rounded-lg border border-gray-100 bg-slate-50/60 px-3 py-2.5 transition hover:border-gray-200 hover:bg-white hover:shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-mono text-sm font-bold text-slate-800"><?= e(substr($jb['jam'], 0, 5)) ?></span>
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                            <?= e(tipe_bel_label($jb['tipe'])) ?>
                                        </span>
                                        <?php if ($jb['keterangan'] !== ''): ?>
                                            <span class="text-xs text-slate-500"><?= e($jb['keterangan']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($jb['aktif']): ?>
                                        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-emerald-600">
                                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-red-500">
                                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-red-500"></span>Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-2">
                                    <span class="text-[11px] text-slate-400">Suara: <?= e($jb['nama_suara'] ?? 'Default') ?></span>
                                    <div class="flex items-center gap-3 text-xs">
                                        <a href="edit.php?id=<?= e($jb['id']) ?>"
                                           class="inline-flex items-center gap-1 font-medium text-blue-600 hover:text-blue-800">
                                            <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                                            Edit
                                        </a>
                                        <a href="hapus.php?id=<?= e($jb['id']) ?>"
                                           class="inline-flex items-center gap-1 font-medium text-red-600 hover:text-red-800">
                                            <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            Hapus
                                        </a>
                                        <form method="post" action="process.php" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="toggle">
                                            <input type="hidden" name="id" value="<?= e($jb['id']) ?>">
                                            <input type="hidden" name="kategori" value="<?= e($filterKategori) ?>">
                                            <button type="submit"
                                                    title="<?= $jb['aktif'] ? 'Nonaktifkan jadwal ini' : 'Aktifkan jadwal ini' ?>"
                                                    class="inline-flex items-center gap-1 font-medium <?= $jb['aktif'] ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' ?>">
                                                <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $jb['aktif']
                                                    ? '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>'
                                                    : '<path d="M20 6 9 17l-5-5"/>' ?></svg>
                                                <?= $jb['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
