#!/usr/bin/env python3
"""Generate chime pengumuman (WAV 44100 Hz 16-bit mono) untuk Bell Sekolah.

Output: uploads/bel/bel_chime.wav

Pola: "ding-dong" dua nada (ding nada tinggi, dong nada rendah) diulang 3x,
menyerupai bunyi pengeras suara stasiun sebelum pengumuman.
"""
import math
import os
import struct
import wave

SAMPLE_RATE = 44100

# Frekuensi "ding" (nada tinggi) dan "dong" (nada rendah) — interval nada ke-5
F_DING = 1046.5   # C6
F_DONG = 783.99   # G5

DURASI_NADA = 0.35    # detik per nada
JEDA_NADA   = 0.08    # jeda antar nada dalam satu pasang
JEDA_PASANG = 0.22    # jeda antar pasangan
ULANGAN     = 3       # jumlah pasangan ding-dong


def _nada(path, frekuensi, durasi, amplitudo=0.7):
    """Tulis satu nada dengan envelope attack-decay lembut (mirip lonceng)."""
    n = int(SAMPLE_RATE * durasi)
    data = []
    attack = int(SAMPLE_RATE * 0.02)  # 20 ms attack hindari klik
    for i in range(n):
        t = i / SAMPLE_RATE
        # envelope: attack cepat + decay eksponensial
        if i < attack:
            env = (i / attack) * math.exp(-3.0 * t)
        else:
            env = math.exp(-3.0 * t)
        v = int(32767 * amplitudo * env * math.sin(2 * math.pi * frekuensi * t))
        data.append(struct.pack("<h", v))
    with wave.open(path, "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(SAMPLE_RATE)
        w.writeframes(b"".join(data))


def main():
    # Render tiap nada ke buffer sementara, lalu gabung dengan jeda senyap.
    import io

    def nada_bytes(frekuensi, durasi):
        buf = io.BytesIO()
        _nada(buf, frekuensi, durasi)
        buf.seek(0)
        with wave.open(buf) as w:
            return w.readframes(w.getnframes())

    def senyap(durasi):
        return b"\x00\x00" * int(SAMPLE_RATE * durasi)

    frame = senyap(0.1)  # jeda awal
    for i in range(ULANGAN):
        frame += nada_bytes(F_DING, DURASI_NADA) + senyap(JEDA_NADA)
        frame += nada_bytes(F_DONG, DURASI_NADA)
        if i < ULANGAN - 1:
            frame += senyap(JEDA_PASANG)
    frame += senyap(0.4)  # jeda akhir

    path = "uploads/bel/bel_chime.wav"
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with wave.open(path, "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(SAMPLE_RATE)
        w.writeframes(frame)
    print("Buat:", path, "(", os.path.getsize(path), "byte )")


if __name__ == "__main__":
    main()
