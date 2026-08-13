/**
 * Bell Sekolah — Pemutar (Kiosk) client-side.
 *
 * Tanggung jawab:
 *   - Kalibrasi offset waktu server (server_now_ms - Date.now()).
 *   - Jam digital raksasa + tanggal/hari/kategori + countdown bel berikutnya.
 *   - Polling status.php tiap 5 detik; loop tick 250 ms.
 *   - Bunyi otomatis di ambang jadwal (dalam toleransi_lag) tanpa dobel 3 menit.
 *   - Recovery via sinyal.php (pending diputar=0) + konfirmasi via confirm.php.
 *   - Tombol Bel Manual (login + CSRF) dan Bel Darurat (bebas login), rate-limit.
 *   - Overlay notifikasi, fullscreen toggle, unlock audio dari gesture pertama.
 *
 * Elemen DOM yang dipakai: jam-besar, tanggal-info, kategori-chip, status-badge,
 * titik-koneksi, teks-koneksi, countdown-label, countdown-waktu, countdown-tujuan,
 * jadwal-list, tombol-manual, tombol-darurat, tombol-fullscreen, player-bunyi,
 * overlay-bunyi (+ -judul, -sub), toast.
 */

(function () {
    'use strict';

    var C = window.PLAYER_CONFIG || {};
    var API = C.apiBase || 'api/';

    // ---------- state ----------
    var offsetServer      = 0;            // server_now_ms - Date.now()
    var dataStatus        = null;         // payload terakhir api/status.php
    var tanggalHariIni    = '';
    var sinyalDipakai     = {};           // id log_bel yang sudah ditangani
    var jadwalTerakhir    = {};           // id_jadwal -> timestamp terakhir dibunyikan
    var pendingConfirm    = {};           // id log_bel yang menunggu konfirmasi
    var sedangBunyi       = false;
    var timerBunyi        = null;
    var terakhirKirim     = 0;            // guard tombol manual/darurat (klien)
    var timerToast        = null;

    var fmtWaktu = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: false, timeZone: 'Asia/Jakarta'
    });

    // ---------- util ----------
    function el(id) { return document.getElementById(id); }

    function serverNow() { return Date.now() + offsetServer; }

    function kalibrasi(dt) {
        if (dt && typeof dt.server_now_ms === 'number') {
            offsetServer = dt.server_now_ms - Date.now();
        }
    }

    function pad2(n) { return String(n).padStart(2, '0'); }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function formatDurasi(ms) {
        var total = Math.max(0, Math.floor(ms / 1000));
        var h = Math.floor(total / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;
        return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
    }

    /** Milidetik (epoch) jadwal pada tanggal hari ini — TZ-independent via Date.UTC. */
    function jadwalMs(j) {
        var tgl = (tanggalHariIni || '1970-01-01').split('-').map(Number);
        var jam = (j.jam || '00:00').split(':').map(Number);
        return Date.UTC(tgl[0], tgl[1] - 1, tgl[2], jam[0] || 0, jam[1] || 0, 0);
    }

    /** String "Y-m-d H:i:s" untuk kolom waktu log. */
    function waktuDb(j) {
        var jam = j.jam || '00:00';
        if (jam.length === 5) jam += ':00';
        return (tanggalHariIni || '1970-01-01') + ' ' + jam;
    }

    function fetchJSON(url, opts, timeout) {
        opts = opts || {};
        timeout = timeout || 8000;
        var ctl = ('AbortController' in window) ? new AbortController() : null;
        var t = setTimeout(function () { if (ctl) ctl.abort(); }, timeout);
        var headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
        return fetch(url, Object.assign({}, opts, {
            headers: headers,
            signal: ctl ? ctl.signal : undefined
        }))
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .finally(function () { clearTimeout(t); });
    }

    // ---------- bunyi & overlay ----------
    function hentikanBunyi() {
        var a = el('player-bunyi');
        if (a) {
            try { a.pause(); a.currentTime = 0; } catch (e) {}
        }
        if ('speechSynthesis' in window) {
            try { speechSynthesis.cancel(); } catch (e) {}
        }
        sedangBunyi = false;
        clearTimeout(timerBunyi);
        timerBunyi = null;
        var o = el('overlay-bunyi');
        if (o) o.classList.add('tersembunyi');
        flushConfirm();
    }

    /** Putar file audio sampai selesai (ended/error), resolve lalu lanjut. */
    function putarTunggu(a, src) {
        return new Promise(function (resolve) {
            var selesai = function () {
                a.removeEventListener('ended', selesai);
                a.removeEventListener('error', selesai);
                resolve();
            };
            a.addEventListener('ended', selesai);
            a.addEventListener('error', selesai);
            a.src = src;
            try { a.currentTime = 0; } catch (e) {}
            var p = a.play();
            if (p && typeof p.catch === 'function') p.catch(function () { resolve(); });
        });
    }

    /** Ucapan teks pengumuman via Web Speech API (fallback bila MP3 tidak ada). */
    function ucapkanPengumuman(teks) {
        return new Promise(function (resolve) {
            if (!('speechSynthesis' in window)) { resolve(); return; }
            var u = new SpeechSynthesisUtterance(teks);
            u.lang = 'id-ID';
            u.rate = 1;
            u.onend = function () { resolve(); };
            u.onerror = function () { resolve(); };
            var voices = speechSynthesis.getVoices();
            var v = voices.filter(function (x) {
                return x.lang && x.lang.toLowerCase().indexOf('id') === 0;
            })[0];
            if (v) u.voice = v;
            speechSynthesis.speak(u);
            setTimeout(function () { resolve(); }, 15000); // safety net
        });
    }

    /** Putar suara bel utama selama durasi_bunyi, lalu jadwalkan henti. */
    function mulaiBunyiBel(a, sig) {
        var src = sig.suara || C.suaraDefault;
        if (src) a.src = src;
        try { a.currentTime = 0; } catch (e) {}
        var p = a.play();
        if (p && typeof p.catch === 'function') p.catch(function () {});
        var dur = Math.max(3, (sig.durasi_bunyi || 10));
        clearTimeout(timerBunyi);
        timerBunyi = setTimeout(hentikanBunyi, dur * 1000);
    }

    function playSinyal(sig) {
        if (!sig) return Promise.resolve();
        sinyalDipakai[sig.id] = true;
        if (sig.id_jadwal) jadwalTerakhir[sig.id_jadwal] = Date.now();

        var a = el('player-bunyi');
        if (!a) return Promise.resolve();

        sedangBunyi = true;
        tampilkanOverlay(sig);
        clearTimeout(timerBunyi);
        a.volume = Math.max(0, Math.min(1, (C.volumeDefault || 80) / 100));

        var pakaiPengumuman = C.pengumumanAktif && C.chimeUrl && sig.jenis === 'otomatis';

        if (!pakaiPengumuman) {
            mulaiBunyiBel(a, sig);
            return Promise.resolve();
        }

        // urutan: chime peringatan -> pengumuman (MP3 bila ada, fallback ucapan) -> suara bel
        return putarTunggu(a, C.chimeUrl)
            .then(function () {
                if (sig.audio_pengumuman) return putarTunggu(a, sig.audio_pengumuman);
                if (sig.keterangan) return ucapkanPengumuman(sig.keterangan);
            })
            .then(function () { mulaiBunyiBel(a, sig); });
    }

    function tampilkanOverlay(sig) {
        var judul, sub;
        if (sig.jenis === 'darurat') {
            judul = 'BEL DARURAT';
            sub   = 'Situasi darurat — mohon tetap tenang';
        } else if (sig.jenis === 'manual') {
            judul = 'BEL MANUAL';
            sub   = sig.keterangan || 'Ditekan dari layar pemutar';
        } else {
            judul = sig.tipe_label || 'Bel';
            sub   = (sig.keterangan || '') + (String(sig.waktu || '').substring(11, 16) ? ' · ' + String(sig.waktu).substring(11, 16) : '');
        }
        var j = el('overlay-bunyi-judul');
        var s = el('overlay-bunyi-sub');
        if (j) j.textContent = judul;
        if (s) s.textContent = sub.trim();
        var o = el('overlay-bunyi');
        if (o) o.classList.remove('tersembunyi');
    }

    function tandaiConfirm(id) { if (id) pendingConfirm[id] = true; }

    function flushConfirm() {
        var ids = Object.keys(pendingConfirm).map(Number);
        if (!ids.length) return;
        pendingConfirm = {};
        fetchJSON(API + 'confirm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        }).catch(function () {});
    }

    // ---------- render ----------
    function renderStatus(dt) {
        var b = el('status-badge');
        if (!b) return;
        var teks, cls;
        if (dt.mode === 'libur' && dt.libur_pengganti) {
            teks = 'Libur · Pakai ' + (dt.kategori ? dt.kategori.nama : 'pengganti');
            cls  = 'status-pengganti';
        } else if (dt.mode === 'libur') {
            teks = 'Hari Libur — Bel Nonaktif';
            cls  = 'status-libur';
        } else if (dt.mode === 'minggu') {
            teks = 'Minggu — Bel Nonaktif';
            cls  = 'status-minggu';
        } else {
            teks = 'Bel Aktif';
            cls  = 'status-aktif';
        }
        b.textContent = teks;
        b.className = 'status-badge ' + cls;
    }

    function renderInfo(dt) {
        var info = el('tanggal-info');
        if (info) info.textContent = (dt.tanggal_teks || dt.tanggal) + ' WIB';
        var chip = el('kategori-chip');
        if (chip) {
            if (dt.kategori) {
                chip.style.display = 'inline-block';
                chip.style.background = (dt.kategori.warna || '#2563eb') + '26';
                chip.style.color = dt.kategori.warna || '#60a5fa';
                chip.textContent = dt.kategori.nama;
            } else {
                chip.style.display = 'none';
            }
        }
    }

    function renderJadwal(dt, now) {
        var kont = el('jadwal-list');
        if (!kont) return;
        var list = dt.jadwal || [];
        if (!list.length) {
            var pesan = dt.mode === 'libur'
                ? 'Hari libur — tidak ada jadwal bel.'
                : dt.mode === 'minggu'
                    ? 'Minggu — tidak ada jadwal bel.'
                    : 'Belum ada jadwal untuk kategori hari ini.';
            kont.innerHTML = '<li class="kosong">' + pesan + '</li>';
            return;
        }

        var upcoming = list.filter(function (j) { return jadwalMs(j) > now; });
        var berikut  = upcoming.length ? upcoming[0] : null;
        var sorot    = {};
        upcoming.slice(0, 3).forEach(function (j) { sorot[j.id] = true; });

        kont.innerHTML = list.map(function (j) {
            var lewat = jadwalMs(j) <= now;
            var cls = 'jadwal-item'
                + (lewat ? ' lewat' : '')
                + (sorot[j.id] ? ' berikutnya' : '')
                + (berikut && j.id === berikut.id ? ' paling-dekat' : '');
            return '<li class="' + cls + '">'
                + '<span class="waktu">' + escapeHtml(j.jam) + '</span>'
                + '<span class="tipe tipe-' + escapeHtml(j.tipe) + '">' + escapeHtml(j.tipe_label) + '</span>'
                + '<span class="ket">' + escapeHtml(j.keterangan || '') + '</span>'
                + '</li>';
        }).join('');
    }

    function renderCountdown(dt, now) {
        var elW = el('countdown-waktu');
        var elL = el('countdown-label');
        var elT = el('countdown-tujuan');
        if (!elW) return;

        var list = dt.jadwal || [];
        var upcoming = list.filter(function (j) { return jadwalMs(j) > now; });

        if (!upcoming.length) {
            elW.textContent = '--:--:--';
            if (dt.mode === 'libur') {
                elL.textContent = 'Hari libur';
                if (elT) elT.textContent = dt.libur_keterangan ? dt.libur_keterangan : 'Bel nonaktif';
            } else if (dt.mode === 'minggu') {
                elL.textContent = 'Minggu';
                if (elT) elT.textContent = 'Tidak ada bel hari ini';
            } else {
                elL.textContent = 'Tidak ada bel berikutnya';
                if (elT) elT.textContent = '';
            }
            return;
        }

        var next = upcoming[0];
        elW.textContent = formatDurasi(jadwalMs(next) - now);
        elL.textContent = 'Bel berikutnya';
        if (elT) elT.textContent = 'menuju ' + next.jam + ' · ' + (next.tipe_label || 'bel');
    }

    function renderSemua() {
        if (!dataStatus) return;
        var now = serverNow();
        renderStatus(dataStatus);
        renderInfo(dataStatus);
        renderJadwal(dataStatus, now);
        renderCountdown(dataStatus, now);
    }

    // ---------- polling ----------
    function setKoneksi(ok) {
        var titik = el('titik-koneksi');
        var teks  = el('teks-koneksi');
        if (titik) titik.className = 'titik' + (ok ? ' ok' : '');
        if (teks) teks.textContent = ok ? 'Terhubung' : 'Terputus';
    }

    function pollStatus() {
        return fetchJSON(API + 'status.php', {}, 6000)
            .then(function (dt) {
                kalibrasi(dt);
                dataStatus = dt;
                if (dt.tanggal) tanggalHariIni = dt.tanggal;
                renderSemua();
                setKoneksi(true);
            })
            .catch(function () { setKoneksi(false); });
    }

    function pollSinyal() {
        return fetchJSON(API + 'sinyal.php', {}, 6000)
            .then(function (dt) {
                kalibrasi(dt);
                if (!dt.sinyal || !dt.sinyal.length) return;
                for (var i = 0; i < dt.sinyal.length; i++) {
                    var s = dt.sinyal[i];
                    if (sinyalDipakai[s.id]) continue;
                    // jadwal yang baru saja dibunyikan (< 3 menit) tidak diulang
                    if (s.id_jadwal && jadwalTerakhir[s.id_jadwal] &&
                        Date.now() - jadwalTerakhir[s.id_jadwal] < 3 * 60 * 1000) {
                        sinyalDipakai[s.id] = true;
                        tandaiConfirm(s.id);
                        continue;
                    }
                    if (sedangBunyi) break;
                    return playSinyal(s).then(function () { tandaiConfirm(s.id); });
                }
            })
            .catch(function () {});
    }

    // ---------- ambang jadwal otomatis ----------
    function prosesAmbang() {
        if (!dataStatus || sedangBunyi) return;
        var list = dataStatus.jadwal || [];
        if (!list.length) return;

        var now = serverNow();
        var win = ((C.toleransiLag || 1) + 2) * 1000; // toleransi lag + buffer 2 dtk

        for (var i = 0; i < list.length; i++) {
            var j = list[i];
            var tMs = jadwalMs(j);
            if (tMs > now) break;               // jadwal terurut: sisanya belum waktunya
            var selisih = now - tMs;
            if (selisih > win) continue;        // sudah lewat jendela toleransi

            var last = jadwalTerakhir[j.id];
            if (last && now - last < 3 * 60 * 1000) continue; // anti dobel 3 menit

            jadwalTerakhir[j.id] = now;
            bunyikanOtomatis(j);
            return;                             // satu bunyi per tick
        }
    }

    function bunyikanOtomatis(j) {
        fetchJSON(API + 'log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                jenis: 'otomatis',
                id_jadwal: j.id,
                id_suara: j.id_suara || 0,
                keterangan: j.keterangan || '',
                waktu: waktuDb(j)
            })
        }, 6000)
        .then(function (res) {
            if (res.status === 'dup') return;               // sudah dicatat entitas lain
            if (res.status === 'ok' && res.sinyal) {
                return playSinyal(res.sinyal).then(function () {
                    tandaiConfirm(res.sinyal.id);
                });
            }
        })
        .catch(function () {});
    }

    // ---------- manual / darurat ----------
    function kirimManual(jenis) {
        var now = Date.now();
        if (now - terakhirKirim < 4000) return;             // guard klien
        terakhirKirim = now;

        var headers = { 'Content-Type': 'application/json' };
        if (C.csrfToken) headers['X-CSRF-Token'] = C.csrfToken;

        fetchJSON(API + 'manual.php', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ jenis: jenis })
        }, 6000)
        .then(function (res) {
            if (res.status === 'rate_limit') {
                tampilToast('Terlalu cepat. Tunggu ' + (res.tunggu || 10) + ' detik.');
                return;
            }
            if (res.status !== 'ok' || !res.sinyal) {
                tampilToast(res.pesan || 'Gagal membunyikan bel.');
                return;
            }
            return playSinyal(res.sinyal).then(function () {
                tandaiConfirm(res.sinyal.id);
            });
        })
        .catch(function () { tampilToast('Gagal terhubung ke server.'); });
    }

    // ---------- toast ----------
    function tampilToast(pesan) {
        var t = el('toast');
        if (!t) return;
        t.textContent = pesan;
        t.style.display = 'block';
        clearTimeout(timerToast);
        timerToast = setTimeout(function () { t.style.display = 'none'; }, 3000);
    }

    // ---------- fullscreen ----------
    function toggleFullscreen() {
        var doc = document;
        if (!doc.fullscreenElement && !doc.webkitFullscreenElement) {
            var req = doc.documentElement.requestFullscreen || doc.documentElement.webkitRequestFullscreen;
            if (req) req.call(doc.documentElement);
        } else {
            var ext = doc.exitFullscreen || doc.webkitExitFullscreen;
            if (ext) ext.call(doc);
        }
    }

    // ---------- unlock audio (wajib dari gesture pertama) ----------
    function unlockAudio() {
        var a = el('player-bunyi');
        if (!a) return;
        var vol = a.volume;
        a.volume = 0;
        var p = a.play();
        if (p && typeof p.then === 'function') {
            p.then(function () {
                try { a.pause(); a.currentTime = 0; } catch (e) {}
                a.volume = vol;
            }).catch(function () {});
        }
    }

    // ---------- loop utama ----------
    function loop() {
        var now = serverNow();
        var jam = el('jam-besar');
        if (jam) {
            jam.textContent = fmtWaktu.format(new Date(now)).replace('24:', '00:');
        }
        if (dataStatus) {
            renderCountdown(dataStatus, now);
            prosesAmbang();
        }
    }

    // ---------- init ----------
    function init() {
        var a = el('player-bunyi');
        if (a) {
            if (C.suaraDefault) a.src = C.suaraDefault;
            a.preload = 'auto';
        }

        var btnManual = el('tombol-manual');
        if (btnManual) btnManual.addEventListener('click', function () { kirimManual('manual'); });

        var btnDarurat = el('tombol-darurat');
        if (btnDarurat) btnDarurat.addEventListener('click', function () { kirimManual('darurat'); });

        var btnFs = el('tombol-fullscreen');
        if (btnFs) btnFs.addEventListener('click', toggleFullscreen);

        var ov = el('overlay-bunyi');
        if (ov) ov.addEventListener('click', hentikanBunyi);

        ['pointerdown', 'touchstart', 'keydown'].forEach(function (evt) {
            window.addEventListener(evt, unlockAudio, { once: true, passive: true });
        });

        // Kalibrasi offset awal
        fetchJSON(API + 'waktu.php', {}, 4000).then(function (dt) { kalibrasi(dt); }).catch(function () {});

        // Polling status & sinyal tiap 5 detik, tick halus tiap 250 ms
        pollStatus();
        setInterval(pollStatus, 5000);
        setInterval(pollSinyal, 5000);
        setInterval(loop, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
