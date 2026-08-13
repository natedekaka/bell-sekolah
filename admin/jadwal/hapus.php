<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'ID jadwal tidak valid.');
    redirect('index.php');
}

// Ambil data jadwal yang akan dihapus
$stmt = conn()->prepare("
    SELECT j.*, s.nama AS nama_suara, k.nama AS nama_kategori
    FROM jadwal_bel j
    LEFT JOIN suara_bel s ON s.id = j.id_suara
    LEFT JOIN kategori_jadwal k ON k.id = j.id_kategori
    WHERE j.id = ?
    LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$jadwal = $stmt->get_result()->fetch_assoc();

if (!$jadwal) {
    set_flash('error', 'Jadwal tidak ditemukan.');
    redirect('index.php');
}

$judul = 'Hapus Jadwal Bel';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-xl">
    <div class="bg-white rounded-xl shadow p-6">
        <div class="mb-6">
            <h3 class="text-xl font-bold text-slate-800">Hapus Jadwal Bel</h3>
            <p class="text-sm text-slate-500 mt-1">Konfirmasi sebelum menghapus jadwal berikut ini.</p>
        </div>

        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-4 mb-6">
            <p class="text-sm text-red-800">
                Anda akan menghapus jadwal:
            </p>
            <table class="mt-3 w-full text-sm">
                <tbody>
                    <tr>
                        <td class="py-1 pr-4 text-red-600">Kategori</td>
                        <td class="py-1 text-red-900"><?= e($jadwal['nama_kategori'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 pr-4 text-red-600">Hari</td>
                        <td class="py-1 text-red-900"><?= e(hari_label((int) $jadwal['hari'])) ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 pr-4 text-red-600">Jam</td>
                        <td class="py-1 font-mono font-semibold text-red-900"><?= e(substr($jadwal['jam'], 0, 5)) ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 pr-4 text-red-600">Tipe</td>
                        <td class="py-1 text-red-900"><?= e(tipe_bel_label($jadwal['tipe'])) ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 pr-4 text-red-600">Keterangan</td>
                        <td class="py-1 text-red-900"><?= e($jadwal['keterangan']) ?: '—' ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 pr-4 text-red-600">Suara</td>
                        <td class="py-1 text-red-900"><?= e($jadwal['nama_suara'] ?? 'Default') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form method="post" action="process.php" class="flex items-center gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= e($jadwal['id']) ?>">
            <input type="hidden" name="kategori" value="<?= e($jadwal['id_kategori']) ?>">
            <button type="submit"
                    class="rounded-lg bg-red-600 hover:bg-red-700 px-5 py-2.5 text-sm font-medium text-white transition">
                Ya, Hapus
            </button>
            <a href="index.php"
               class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Batal
            </a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
