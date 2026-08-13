#!/usr/bin/env python3
"""Generate suara bel default (WAV 44100 Hz 16-bit mono) untuk Bell Sekolah.

Output: uploads/bel/bel_{masuk,ganti_jam,istirahat,pulang,darurat}.wav
"""
import math
import os
import struct
import wave

SAMPLE_RATE = 44100


def tulis_wav(path, frekuensi, durasi, pola="nada"):
    n = int(SAMPLE_RATE * durasi)
    data = []
    if pola == "dingdong":
        # dua nada naik (ding-dong) menyerupai bel sekolah
        f_awal, f_akhir = frekuensi, frekuensi * 1.25
        half = n // 2
        for i in range(n):
            f = f_awal if i < half else f_akhir
            env = math.exp(-4.0 * (i % half) / SAMPLE_RATE)
            v = int(32767 * 0.7 * env * math.sin(2 * math.pi * f * i / SAMPLE_RATE))
            data.append(struct.pack("<h", v))
    elif pola == "darurat":
        # sirena naik-turun keras, 2.5 Hz modulasi
        for i in range(n):
            t = i / SAMPLE_RATE
            f = frekuensi * (1 + 0.3 * math.sin(2 * math.pi * 2.5 * t))
            v = int(32767 * 0.9 * math.sin(2 * math.pi * f * t))
            data.append(struct.pack("<h", v))
    else:
        # nada tunggal dengan decay
        for i in range(n):
            t = i / SAMPLE_RATE
            env = math.exp(-2.5 * t) if pola == "nada" else 0.6
            v = int(32767 * env * math.sin(2 * math.pi * frekuensi * t))
            data.append(struct.pack("<h", v))

    os.makedirs(os.path.dirname(path), exist_ok=True)
    with wave.open(path, "w") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(SAMPLE_RATE)
        w.writeframes(b"".join(data))
    print("Buat:", path, "(", os.path.getsize(path), "byte )")


def main():
    tulis_wav("uploads/bel/bel_masuk.wav", 880, 1.5, "dingdong")
    tulis_wav("uploads/bel/bel_ganti_jam.wav", 660, 0.8, "nada")
    tulis_wav("uploads/bel/bel_istirahat.wav", 520, 2.0, "dingdong")
    tulis_wav("uploads/bel/bel_pulang.wav", 440, 2.5, "dingdong")
    tulis_wav("uploads/bel/bel_darurat.wav", 1000, 3.0, "darurat")


if __name__ == "__main__":
    main()
