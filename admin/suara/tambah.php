<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

$judul = 'Tambah Suara';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Upload Suara Baru</h3>
        <p class="text-sm text-slate-500 mb-6">Unggah file audio berformat MP3, WAV, atau OGG. Batas maksimum upload mengikuti pengaturan aplikasi.</p>

        <form method="post" action="proses_tambah.php" enctype="multipart/form-data" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label for="nama" class="block text-sm font-medium text-slate-700 mb-1">Nama Suara</label>
                <input type="text" id="nama" name="nama" required maxlength="150"
                       placeholder="Contoh: Bel Masuk Sekolah"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label for="file_audio" class="block text-sm font-medium text-slate-700 mb-1">File Audio</label>
                <input type="file" id="file_audio" name="file_audio" required
                       accept=".mp3,.wav,.ogg,audio/*"
                       class="w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                <p class="mt-1 text-xs text-slate-400">Format diizinkan: MP3, WAV, OGG.</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_default" name="is_default" value="1"
                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="is_default" class="text-sm text-slate-700">Jadikan sebagai suara default</label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                    Simpan Suara
                </button>
                <a href="index.php" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
