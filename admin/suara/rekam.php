<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

$judul = 'Rekam Suara';
require_once __DIR__ . '/../../views/layout.php';
?>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Rekam Suara Lewat Mikrofon</h3>
        <p class="text-sm text-slate-500 mb-6">Rekam audio langsung dari mikrofon browser, dengarkan hasilnya, lalu simpan ke library suara.</p>

        <form id="formRekaman" method="post" action="proses_tambah.php" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" id="data_rekaman" name="data_rekaman" value="">

            <div>
                <label for="nama" class="block text-sm font-medium text-slate-700 mb-1">Nama Suara</label>
                <input type="text" id="nama" name="nama" required maxlength="150"
                       placeholder="Contoh: Bel Rekaman Khusus"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="flex items-center gap-3">
                <button type="button" id="mulaiRekam"
                        class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                    ● Mulai Rekam
                </button>
                <button type="button" id="stopRekam" disabled
                        class="px-4 py-2 rounded-lg bg-slate-700 text-white text-sm font-medium hover:bg-slate-800">
                    ■ Stop
                </button>
                <span id="statusRekam" class="text-sm text-slate-500">Mikrofon belum aktif.</span>
            </div>

            <div id="panelPreview" class="hidden">
                <p class="text-sm font-medium text-slate-700 mb-1">Hasil Rekaman</p>
                <audio id="previewRekaman" controls class="w-full h-10"></audio>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_default" name="is_default" value="1"
                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_default" class="text-sm text-slate-700">Jadikan sebagai suara default</label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="button" id="simpanRekaman" disabled
                        class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    <svg style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg> Simpan Rekaman
                </button>
                <a href="index.php" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var formRekaman = document.getElementById('formRekaman');
    var inputData = document.getElementById('data_rekaman');
    var tombolMulai = document.getElementById('mulaiRekam');
    var tombolStop = document.getElementById('stopRekam');
    var tombolSimpan = document.getElementById('simpanRekaman');
    var statusEl = document.getElementById('statusRekam');
    var panelPreview = document.getElementById('panelPreview');
    var preview = document.getElementById('previewRekaman');

    var mediaRecorder = null;
    var potonganRekaman = [];
    var blobRekaman = null;

    tombolMulai.addEventListener('click', function () {
        if (!navigator.mediaDevices || !window.MediaRecorder) {
            statusEl.textContent = 'Browser tidak mendukung MediaRecorder. Gunakan Chrome, Edge, atau Firefox.';
            return;
        }
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function (stream) {
                mediaRecorder = new MediaRecorder(stream);
                potonganRekaman = [];
                blobRekaman = null;
                tombolSimpan.disabled = true;
                preview.pause();
                preview.removeAttribute('src');
                preview.load();

                mediaRecorder.ondataavailable = function (e) {
                    if (e.data && e.data.size > 0) {
                        potonganRekaman.push(e.data);
                    }
                };

                mediaRecorder.onstop = function () {
                    blobRekaman = new Blob(potonganRekaman, { type: mediaRecorder.mimeType });
                    preview.src = URL.createObjectURL(blobRekaman);
                    panelPreview.classList.remove('hidden');
                    statusEl.textContent = 'Rekaman selesai. Dengarkan hasilnya, lalu klik Simpan Rekaman.';
                    tombolSimpan.disabled = false;
                    stream.getTracks().forEach(function (track) { track.stop(); });
                };

                mediaRecorder.start();
                statusEl.textContent = 'Sedang merekam... klik Stop untuk mengakhiri.';
                tombolMulai.disabled = true;
                tombolStop.disabled = false;
            })
            .catch(function () {
                statusEl.textContent = 'Gagal mengakses mikrofon. Izinkan akses mikrofon pada browser lalu coba lagi.';
            });
    });

    tombolStop.addEventListener('click', function () {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            tombolMulai.disabled = false;
            tombolStop.disabled = true;
        }
    });

    tombolSimpan.addEventListener('click', function () {
        if (!blobRekaman) {
            statusEl.textContent = 'Tidak ada rekaman untuk disimpan.';
            return;
        }
        var pembaca = new FileReader();
        pembaca.onload = function () {
            inputData.value = pembaca.result;
            formRekaman.submit();
        };
        pembaca.readAsDataURL(blobRekaman);
    });
})();
</script>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
