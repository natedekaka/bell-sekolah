<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'ID jadwal tidak valid.');
    redirect('index.php');
}

// Ambil data jadwal yang akan diedit
$stmt = conn()->prepare("SELECT * FROM jadwal_bel WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$jadwal = $stmt->get_result()->fetch_assoc();

if (!$jadwal) {
    set_flash('error', 'Jadwal tidak ditemukan.');
    redirect('index.php');
}

$kategoriDipilih = (int) $jadwal['id_kategori'];
$hariDipilih     = (int) $jadwal['hari'];

// Daftar kategori & suara
$stmt = conn()->prepare("SELECT * FROM kategori_jadwal ORDER BY is_active DESC, nama ASC");
$stmt->execute();
$daftarKategori = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = conn()->prepare("SELECT * FROM suara_bel ORDER BY nama ASC");
$stmt->execute();
$daftarSuara = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$tipeList = ['masuk'=>'Masuk','ganti_jam'=>'Ganti Jam','istirahat'=>'Istirahat','sholat'=>'Sholat','pulang'=>'Pulang','reses'=>'Reses','kustom'=>'Kustom'];

$judul = 'Edit Jadwal Bel';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-800">Edit Jadwal Bel</h3>
                <p class="text-sm text-slate-500 mt-1">
                    Mengedit jadwal <?= e(hari_label($hariDipilih)) ?> pukul <?= e(substr($jadwal['jam'], 0, 5)) ?>.
                </p>
            </div>
            <a href="index.php" class="text-sm text-blue-600 hover:text-blue-800"><svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Kembali</a>
        </div>

        <form method="post" action="process.php" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" value="<?= e($jadwal['id']) ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="id_kategori" class="block text-sm font-medium text-slate-700 mb-1">Kategori</label>
                    <select name="id_kategori" id="id_kategori" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
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
                    <input type="time" name="jam" id="jam" value="<?= e(substr($jadwal['jam'], 0, 5)) ?>" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                </div>
                <div>
                    <label for="tipe" class="block text-sm font-medium text-slate-700 mb-1">Tipe</label>
                    <select name="tipe" id="tipe" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                        <?php foreach ($tipeList as $kunci => $label): ?>
                            <option value="<?= e($kunci) ?>" <?= $kunci === $jadwal['tipe'] ? 'selected' : '' ?>><?= e($label) ?></option>
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
                            <option value="<?= e($s['id']) ?>" <?= (int) ($jadwal['id_suara'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['nama']) ?> (<?= e($s['format']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="durasi_bunyi" class="block text-sm font-medium text-slate-700 mb-1">Durasi Bunyi (detik)</label>
                    <input type="number" name="durasi_bunyi" id="durasi_bunyi" value="<?= e($jadwal['durasi_bunyi']) ?>" min="1" max="3600" step="1" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                </div>
            </div>

            <div>
                <label for="keterangan" class="block text-sm font-medium text-slate-700 mb-1">Keterangan</label>
                <input type="text" name="keterangan" id="keterangan" maxlength="255" value="<?= e($jadwal['keterangan']) ?>"
                       placeholder="Contoh: Bel masuk pagi"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
            </div>

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="aktif" value="1" <?= $jadwal['aktif'] ? 'checked' : '' ?>
                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-400">
                    <span class="text-sm font-medium text-slate-700">Aktif</span>
                </label>
                <p class="mt-1 text-xs text-slate-400">Jadwal yang aktif ikut dibunyikan otomatis.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-sm font-medium text-white transition">
                    Simpan Perubahan
                </button>
                <a href="index.php"
                   class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
