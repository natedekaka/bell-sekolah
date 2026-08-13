<?php

/**
 * Bell Sekolah — Halaman Pengaturan.
 * Form tunggal untuk mengubah seluruh pengaturan aplikasi dari tabel
 * `pengaturan` (dibaca via helper settings()). Data dikirim via POST
 * ke proses.php.
 */

require_once __DIR__ . '/../../core/init.php';

require_admin();

// Kategori jadwal untuk dropdown kategori_hari_libur
$stmt = conn()->prepare("SELECT id, nama FROM kategori_jadwal ORDER BY is_active DESC, id ASC");
$stmt->execute();
$daftarKategori = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$judul = 'Pengaturan';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-3xl space-y-6">

    <div>
        <p class="text-sm text-slate-500">
            Ubah pengaturan umum aplikasi Bell Sekolah. Klik <b>Simpan Pengaturan</b> untuk menerapkan.
        </p>
    </div>

    <form method="post" action="proses.php" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Kinerja & suara -->
        <div class="rounded-xl bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-slate-800">Kinerja & Suara</h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="toleransi_lag" class="mb-1 block text-sm font-medium text-slate-700">Toleransi Lag (detik)</label>
                    <input type="number" id="toleransi_lag" name="toleransi_lag" min="0" max="60" step="1"
                           value="<?= e((int) setting('toleransi_lag', 1)) ?>"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                    <p class="mt-1 text-xs text-slate-400">Toleransi keterlambatan bunyi bel (detik).</p>
                </div>

                <div>
                    <label for="volume_default" class="mb-1 block text-sm font-medium text-slate-700">
                        Volume Default: <span id="volume_tampil" class="font-semibold text-blue-600"><?= e((int) setting('volume_default', 80)) ?></span>
                    </label>
                    <input type="range" id="volume_default" name="volume_default" min="0" max="100" step="1"
                           value="<?= e((int) setting('volume_default', 80)) ?>"
                           oninput="document.getElementById('volume_tampil').textContent = this.value"
                           class="w-full accent-blue-600">
                    <p class="mt-1 text-xs text-slate-400">Volume bawaan pemutar bel (0–100).</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="max_upload_mb" class="mb-1 block text-sm font-medium text-slate-700">Batas Upload Audio (MB)</label>
                    <input type="number" id="max_upload_mb" name="max_upload_mb" min="1" max="1024" step="1"
                           value="<?= e((int) setting('max_upload_mb', 20)) ?>"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                    <p class="mt-1 text-xs text-slate-400">Batas maksimum ukuran file audio yang boleh diunggah (MB).</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-3 text-sm text-slate-700">
                        <input type="checkbox" name="pengumuman_aktif" value="1"
                               class="h-4 w-4 rounded border-gray-300 accent-blue-600"
                               <?= setting('pengumuman_aktif', '1') === '1' ? 'checked' : '' ?>>
                        Nyalakan pengumuman suara sebelum bel terjadwal
                    </label>
                    <p class="mt-1 text-xs text-slate-400">
                        Bel terjadwal akan didahului chime peringatan lalu pengumuman dari teks keterangan
                        (MP3 otomatis bila tersedia, fallback ucapan peramban). Bel manual/darurat tidak terdampak.
                    </p>
                </div>
            </div>
        </div>

        <!-- Keamanan pengaturan -->
        <div class="rounded-xl bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-slate-800">Keamanan Pengaturan</h3>

            <div>
                <label for="kunci_pengaturan" class="mb-1 block text-sm font-medium text-slate-700">Kunci Pengaturan (Password)</label>
                <input type="password" id="kunci_pengaturan" name="kunci_pengaturan" autocomplete="new-password"
                       placeholder="<?= setting('kunci_pengaturan', '') === '' ? 'Belum terkunci — isi untuk mengunci' : '••••••' ?>"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                <p class="mt-1 text-xs text-slate-400">
                    Isi untuk mengunci halaman pengaturan; kosongkan untuk tidak terkunci.
                    Password disimpan sebagai hash, bukan teks polos.
                </p>
            </div>
        </div>

        <!-- Auto shutdown -->
        <div class="rounded-xl bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-slate-800">Auto Shutdown PC Pemutar</h3>

            <div class="space-y-4">
                <label class="flex items-center gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="auto_shutdown_aktif" value="1"
                           class="h-4 w-4 rounded border-gray-300 accent-blue-600"
                           <?= setting('auto_shutdown_aktif', '0') === '1' ? 'checked' : '' ?>>
                    Aktifkan auto shutdown PC pemutar
                </label>

                <div>
                    <label for="auto_shutdown_jam" class="mb-1 block text-sm font-medium text-slate-700">Jam Shutdown</label>
                    <input type="time" id="auto_shutdown_jam" name="auto_shutdown_jam"
                           value="<?= e(substr(setting('auto_shutdown_jam', '00:00'), 0, 5)) ?>"
                           class="rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                    <p class="mt-1 text-xs text-slate-400">PC pemutar akan mati otomatis pada jam ini (HH:MM).</p>
                </div>
            </div>
        </div>

        <!-- Hari libur -->
        <div class="rounded-xl bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-slate-800">Perilaku Saat Hari Libur</h3>

            <div>
                <label for="kategori_hari_libur" class="mb-1 block text-sm font-medium text-slate-700">Kategori Pengganti</label>
                <select id="kategori_hari_libur" name="kategori_hari_libur"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                    <option value="" <?= setting('kategori_hari_libur', '') === '' ? 'selected' : '' ?>>
                        — Bel Mati —
                    </option>
                    <?php foreach ($daftarKategori as $k): ?>
                        <option value="<?= e($k['id']) ?>"
                            <?= (string) setting('kategori_hari_libur', '') === (string) $k['id'] ? 'selected' : '' ?>>
                            <?= e($k['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-400">
                    Saat hari libur: kosong = bel mati; pilih kategori = memakai jadwal kategori pengganti.
                </p>
            </div>
        </div>

        <!-- Backup otomatis -->
        <div class="rounded-xl bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-slate-800">Backup Otomatis</h3>

            <div class="space-y-4">
                <label class="flex items-center gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="backup_otomatis_aktif" value="1"
                           class="h-4 w-4 rounded border-gray-300 accent-blue-600"
                           <?= setting('backup_otomatis_aktif', '0') === '1' ? 'checked' : '' ?>>
                    Aktifkan backup otomatis
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="backup_periode" class="mb-1 block text-sm font-medium text-slate-700">Periode</label>
                        <select id="backup_periode" name="backup_periode"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                            <?php
                            $periodeSaatIni = setting('backup_periode', 'weekly');
                            $opsiPeriode = [
                                'daily'   => 'Harian',
                                'weekly'  => 'Mingguan',
                                'monthly' => 'Bulanan',
                            ];
                            foreach ($opsiPeriode as $nilai => $label): ?>
                                <option value="<?= e($nilai) ?>" <?= $periodeSaatIni === $nilai ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="backup_jumlah_simpan" class="mb-1 block text-sm font-medium text-slate-700">Jumlah Backup Disimpan</label>
                        <input type="number" id="backup_jumlah_simpan" name="backup_jumlah_simpan" min="0" step="1"
                               value="<?= e((int) setting('backup_jumlah_simpan', 10)) ?>"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                        <p class="mt-1 text-xs text-slate-400">Jumlah backup terakhir yang disimpan (0 = tidak dibatasi).</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- URL publik -->
        <div class="rounded-xl bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-slate-800">Lainnya</h3>

            <div>
                <label for="url_publik" class="mb-1 block text-sm font-medium text-slate-700">URL Publik Aplikasi</label>
                <input type="text" id="url_publik" name="url_publik" maxlength="255"
                       value="<?= e(setting('url_publik', '')) ?>"
                       placeholder="cth: https://bel.example.com"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                <p class="mt-1 text-xs text-slate-400">URL publik aplikasi untuk player remote (opsional).</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                Simpan Pengaturan
            </button>
            <a href="index.php"
               class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-slate-600 transition hover:bg-gray-100">
                Batal
            </a>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
