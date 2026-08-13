</main>
    <footer class="px-6 py-3 bg-white border-t border-gray-200 text-xs text-gray-400">
        Bell Sekolah — Sistem Bel Otomatis · <span id="teks-footer-waktu"></span>
    </footer>
</div>

<!-- Jam header (Asia/Jakarta di sisi klien) -->
<script>
    function formatDua(n) { return String(n).padStart(2, '0'); }
    function perbaruiJam() {
        var k = new Date();
        var s = formatDua(k.getHours()) + ':' + formatDua(k.getMinutes()) + ':' + formatDua(k.getSeconds());
        var el = document.getElementById('jam-header'); if (el) el.textContent = s;
        var el2 = document.getElementById('teks-footer-waktu'); if (el2) el2.textContent = 'Waktu: ' + s + ' WIB';
    }
    perbaruiJam();
    setInterval(perbaruiJam, 1000);
</script>
</body>
</html>