#!/usr/bin/env python3
"""Generate MP3 pengumuman via edge-tts (suara id-ID natural).

Usage: tts_pengumuman.py <teks> <output.mp3>
"""
import asyncio
import sys

import edge_tts

VOICE = "id-ID-ArdiNeural"


async def main(teks: str, output: str) -> None:
    komunikasi = edge_tts.Communicate(teks, VOICE)
    await komunikasi.save(output)


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: tts_pengumuman.py <teks> <output.mp3>", file=sys.stderr)
        sys.exit(2)
    try:
        asyncio.run(main(sys.argv[1], sys.argv[2]))
    except Exception as e:  # noqa: BLE001 - keluar non-zero agar PHP tahu gagal
        print(f"TTS gagal: {e}", file=sys.stderr)
        sys.exit(1)
