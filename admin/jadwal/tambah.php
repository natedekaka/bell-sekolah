<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

// Kategori pilihan via query string (opsional, mis. dari tab index.php)
$kategoriDipilih = isset($_GET['kategori']) ? (int) $_GET['kategori'] : 0;

// Hari pilihan via query string (opsional, mis. tombol "+ Ada" di kartu hari)
$hariDipilih = isset($_GET['hari']) ? (int) $_GET['hari'] : 1;
if ($hariDipilih < 1 || $hariDipilih > 6) {
    $hariDipilih = 1;
}

// Daftar kategori & suara
$stmt = conn()->prepare("SELECT * FROM kategori_jadwal ORDER BY is_active DESC, nama ASC");
$stmt->execute();
$daftarKategori = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = conn()->prepare("SELECT * FROM suara_bel ORDER BY nama ASC");
$stmt->execute();
$daftarSuara = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Jika kategori terpilih dari query tidak ada, fallback ke kategori aktif / pertama
if (!empty($daftarKategori)) {
    $kategoriAda = false;
    foreach ($daftarKategori as $k) {
        if ((int) $k['id'] === $kategoriDipilih) {
            $kategoriAda = true;
            break;
        }
    }
    if (!$kategoriAda) {
        $kategoriDipilih = 0;
        foreach ($daftarKategori as $k) {
            if ($k['is_active']) {
                $kategoriDipilih = (int) $k['id'];
                break;
            }
        }
    }
    if ($kategoriDipilih === 0) {
        $kategoriDipilih = (int) $daftarKategori[0]['id'];
    }
}

$tipeList = ['masuk'=>'Masuk','ganti_jam'=>'Ganti Jam','istirahat'=>'Istirahat','sholat'=>'Sholat','pulang'=>'Pulang','reses'=>'Reses','kustom'=>'Kustom'];

$judul = 'Tambah Jadwal Bel';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-800">Tambah Jadwal Bel</h3>
                <p class="text-sm text-slate-500 mt-1">Satu jadwal untuk satu hari. Boleh menambahkan beberapa bel pada hari yang sama (multi-bell).</p>
            </div>
            <a href="index.php" class="text-sm text-blue-600 hover:text-blue-800"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Kembali</a>
        </div>

        <?php if (empty($daftarKategori)): ?>
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Belum ada kategori jadwal. Buat kategori terlebih dahulu di menu <b>Kategori Jadwal</b> sebelum menambah jadwal bel.
            </div>
        <?php else: ?>
            <form method="post" action="process.php" class="space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="id_kategori" class="block text-sm font-medium text-slate-700 mb-1">Kategori</label>
                        <select name="id_kategori" id="id_kategori" required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                            <option value="">— Pilih Kategori —</option>
                            <?php foreach ($daftarKategori as $k): ?>
                                <option value="<?= e($k['id']) ?>" <?= $kategoriDipilih === (int) $k['id'] ? 'selected' : '' ?>>
                                    <?= e($k['nama']) ?><?= $k['is_active'] ? ' (aktif)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="hari" class="block text-sm font-medium text-slate-700 mb-1">Hari</label>
                        <select name="hari" id="hari" required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                            <?php for ($h = 1; $h <= 6; $h++): ?>
                                <option value="<?= $h ?>" <?= $h === $hariDipilih ? 'selected' : '' ?>><?= e(hari_label($h)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="jam" class="block text-sm font-medium text-slate-700 mb-1">Jam</label>
                        <input type="time" name="jam" id="jam" required
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                    </div>
                    <div>
                        <label for="tipe" class="block text-sm font-medium text-slate-700 mb-1">Tipe</label>
                        <select name="tipe" id="tipe" required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                            <?php foreach ($tipeList as $kunci => $label): ?>
                                <option value="<?= e($kunci) ?>" <?= $kunci === 'masuk' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="id_suara" class="block text-sm font-medium text-slate-700 mb-1">Suara</label>
                        <select name="id_suara" id="id_suara"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                            <option value="">Default (tanpa suara khusus)</option>
                            <?php foreach ($daftarSuara as $s): ?>
                                <option value="<?= e($s['id']) ?>"><?= e($s['nama']) ?> (<?= e($s['format']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="durasi_bunyi" class="block text-sm font-medium text-slate-700 mb-1">Durasi Bunyi (detik)</label>
                        <input type="number" name="durasi_bunyi" id="durasi_bunyi" value="10" min="1" max="3600" step="1" required
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                    </div>
                </div>

                <div>
                    <label for="keterangan" class="block text-sm font-medium text-slate-700 mb-1">Keterangan</label>
                    <input type="text" name="keterangan" id="keterangan" maxlength="255"
                           placeholder="Contoh: Bel masuk pagi"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                </div>

                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="aktif" value="1" checked
                               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-400">
                        <span class="text-sm font-medium text-slate-700">Aktif</span>
                    </label>
                    <p class="mt-1 text-xs text-slate-400">Jadwal yang aktif ikut dibunyikan otomatis.</p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            class="rounded-lg bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-sm font-medium text-white transition">
                        Simpan
                    </button>
                    <a href="index.php"
                       class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Batal
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
