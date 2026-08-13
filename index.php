<?php
require_once __DIR__ . '/core/init.php';

require_login();

$userAktif   = current_user();
$jamSekarang = date('H:i');
$hariPHP     = (int) date('N');       // 1..7
$kodeHari    = hari_php_to_app($hariPHP); // 1..6, 0 = Minggu

// Kategori aktif
$kategoriAktif = null;
$stmt = conn()->prepare("SELECT * FROM kategori_jadwal WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$kategoriAktif = $stmt->get_result()->fetch_assoc();

// Statistik
function statCount($sql) {
    $r = conn()->query($sql);
    return $r ? (int) $r->fetch_row()[0] : 0;
}
$jmlKategori = 0;
$jmlJadwal   = 0;
$jmlSuara    = 0;
$jmlLibur    = 0;

try {
    $jmlKategori = statCount("SELECT COUNT(*) FROM kategori_jadwal");
    $jmlJadwal   = statCount("SELECT COUNT(*) FROM jadwal_bel");
    $jmlSuara    = statCount("SELECT COUNT(*) FROM suara_bel");
    $jmlLibur    = statCount("SELECT COUNT(*) FROM hari_libur");
} catch (Throwable $e) {
    set_flash('error', 'Gagal terhubung ke database: ' . $e->getMessage());
}

// Jadwal hari ini (kategori aktif)
$jadwalHariIni = [];
if ($kategoriAktif && $kodeHari > 0) {
    $stmt = conn()->prepare("
        SELECT j.*, s.nama AS nama_suara
        FROM jadwal_bel j
        LEFT JOIN suara_bel s ON s.id = j.id_suara
        WHERE j.id_kategori = ? AND j.hari = ? AND j.aktif = 1
        ORDER BY j.jam ASC");
    $stmt->bind_param('ii', $kategoriAktif['id'], $kodeHari);
    $stmt->execute();
    $jadwalHariIni = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Cek apakah hari ini tanggal libur
$tanggalIni = date('Y-m-d');
$stmt = conn()->prepare("SELECT * FROM hari_libur WHERE tanggal = ?");
$stmt->bind_param('s', $tanggalIni);
$stmt->execute();
$hariLibur = $stmt->get_result()->fetch_assoc();

$judul = 'Dashboard';
require_once __DIR__ . '/views/layout.php';
?>

<div class="space-y-6">

    <div class="bg-white rounded-2xl shadow p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-xl font-bold text-slate-800">Halo, <?= e($userAktif['nama_lengkap'] ?: $userAktif['username']) ?>! <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 11V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2"/><path d="M14 10V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg></h3>
                <p class="text-sm text-slate-500 mt-1"><?= e(hari_label($kodeHari)) ?>, <?= e(date('d M Y')) ?> · Pukul <?= e($jamSekarang) ?> WIB</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-slate-400">Kategori aktif</p>
                <p class="text-lg font-bold text-blue-600"><?= e($kategoriAktif['nama'] ?? '—') ?></p>
            </div>
        </div>
        <?php if ($hariLibur): ?>
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-amber-800 text-sm">
                <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg> Hari ini <b>libur</b>: <?= e($hariLibur['keterangan']) ?> — bel otomatis dimatikan / memakai kategori pengganti.
            </div>
        <?php endif; ?>
    </div>

    <!-- Kartu statistik -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-sm text-slate-500">Kategori Jadwal</p>
            <p class="text-3xl font-bold text-blue-600 mt-1"><?= $jmlKategori ?></p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-sm text-slate-500">Jadwal Bel</p>
            <p class="text-3xl font-bold text-cyan-600 mt-1"><?= $jmlJadwal ?></p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-sm text-slate-500">Suara Tersedia</p>
            <p class="text-3xl font-bold text-green-600 mt-1"><?= $jmlSuara ?></p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-sm text-slate-500">Tanggal Libur</p>
            <p class="text-3xl font-bold text-amber-600 mt-1"><?= $jmlLibur ?></p>
        </div>
    </div>

    <!-- Jadwal hari ini -->
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="font-semibold text-slate-700">Jadwal Bel Hari Ini — <?= e(hari_label($kodeHari)) ?></h4>
            <?php if ($kategoriAktif): ?>
                <span class="text-xs px-3 py-1 rounded-full font-medium" style="background:<?= e($kategoriAktif['warna']) ?>15; color:<?= e($kategoriAktif['warna']) ?>">
                    <?= e($kategoriAktif['nama']) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($kodeHari === 0): ?>
            <p class="text-slate-500">Hari ini Minggu (di luar Senin–Sabtu).</p>
        <?php elseif (empty($jadwalHariIni)): ?>
            <p class="text-slate-500">Belum ada jadwal untuk kategori aktif hari ini.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b">
                            <th class="py-2 pr-4">Jam</th>
                            <th class="py-2 pr-4">Tipe</th>
                            <th class="py-2 pr-4">Keterangan</th>
                            <th class="py-2">Suara</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jadwalHariIni as $jb): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 pr-4 font-mono font-semibold text-slate-700"><?= e(substr($jb['jam'], 0, 5)) ?></td>
                                <td class="py-2 pr-4">
                                    <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs"><?= e(tipe_bel_label($jb['tipe'])) ?></span>
                                </td>
                                <td class="py-2 pr-4 text-slate-600"><?= e($jb['keterangan']) ?></td>
                                <td class="py-2 text-slate-600"><?= e($jb['nama_suara'] ?? 'default') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
