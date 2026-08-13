<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();

// Handler ini hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Metode request tidak diizinkan.');
    redirect('index.php');
}

require_csrf('index.php');

$tipeList = ['masuk'=>'Masuk','ganti_jam'=>'Ganti Jam','istirahat'=>'Istirahat','sholat'=>'Sholat','pulang'=>'Pulang','reses'=>'Reses','kustom'=>'Kustom'];

/** URL kembali ke index.php, mempertahankan filter kategori. */
function urlIndex(int $kategoriFilter = 0): string {
    return 'index.php' . ($kategoriFilter > 0 ? '?kategori=' . $kategoriFilter : '');
}

/**
 * Validasi & bersihkan input jadwal.
 * @return array data bersih, atau string pesan error bila tidak valid.
 */
function validasiInputJadwal(array $tipeList): array|string {
    $idKategori = (int) ($_POST['id_kategori'] ?? 0);
    $hari       = (int) ($_POST['hari'] ?? 0);
    $jam        = trim((string) ($_POST['jam'] ?? ''));
    $tipe       = (string) ($_POST['tipe'] ?? '');
    $idSuara    = trim((string) ($_POST['id_suara'] ?? ''));
    $durasi     = (int) ($_POST['durasi_bunyi'] ?? 0);
    $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
    $aktif      = isset($_POST['aktif']) ? 1 : 0;

    if ($idKategori <= 0) {
        return 'Pilih kategori jadwal.';
    }
    if ($hari < 1 || $hari > 6) {
        return 'Pilih hari yang valid (Senin–Sabtu).';
    }
    if ($jam === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $jam)) {
        return 'Jam tidak valid.';
    }
    if (!isset($tipeList[$tipe])) {
        return 'Tipe bel tidak valid.';
    }
    if ($durasi < 1 || $durasi > 3600) {
        return 'Durasi bunyi harus antara 1–3600 detik.';
    }
    if (strlen($keterangan) > 255) {
        return 'Keterangan maksimal 255 karakter.';
    }

    // Normalisasi jam ke format HH:MM:SS
    if (strlen($jam) === 5) {
        $jam .= ':00';
    }

    // Pastikan kategori ada
    $stmt = conn()->prepare("SELECT id FROM kategori_jadwal WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $idKategori);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        return 'Kategori jadwal tidak ditemukan.';
    }

    // id_suara opsional (NULL bila kosong) — pastikan ada di tabel suara_bel
    $idSuaraVal = null;
    if ($idSuara !== '') {
        $idSuaraVal = (int) $idSuara;
        if ($idSuaraVal <= 0) {
            return 'Suara tidak valid.';
        }
        $stmt = conn()->prepare("SELECT id FROM suara_bel WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $idSuaraVal);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            return 'Suara yang dipilih tidak ditemukan.';
        }
    }

    return [
        'id_kategori'  => $idKategori,
        'hari'         => $hari,
        'jam'          => $jam,
        'tipe'         => $tipe,
        'id_suara'     => $idSuaraVal,
        'durasi_bunyi' => $durasi,
        'keterangan'   => $keterangan,
        'aktif'        => $aktif,
    ];
}

/** Tambah satu jadwal bel (multi-bell di jam sama diperbolehkan). */
function aksiTambah(array $tipeList): void {
    $data = validasiInputJadwal($tipeList);
    if (is_string($data)) {
        set_flash('error', $data);
        redirect('index.php');
    }

    $stmt = conn()->prepare("
        INSERT INTO jadwal_bel (id_kategori, hari, jam, tipe, id_suara, durasi_bunyi, keterangan, aktif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'iissiisi',
        $data['id_kategori'],
        $data['hari'],
        $data['jam'],
        $data['tipe'],
        $data['id_suara'],
        $data['durasi_bunyi'],
        $data['keterangan'],
        $data['aktif']
    );

    if (!$stmt->execute()) {
        set_flash('error', 'Gagal menyimpan jadwal: ' . conn()->error);
        redirect(urlIndex($data['id_kategori']));
    }

    generate_pengumuman($data['keterangan']);

    set_flash('success', 'Jadwal bel berhasil ditambahkan.');
    redirect(urlIndex($data['id_kategori']));
}

/** Edit satu jadwal (nilai hari tunggal). */
function aksiEdit(array $tipeList): void {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        set_flash('error', 'ID jadwal tidak valid.');
        redirect('index.php');
    }

    $stmt = conn()->prepare("SELECT id FROM jadwal_bel WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        set_flash('error', 'Jadwal tidak ditemukan.');
        redirect('index.php');
    }

    $data = validasiInputJadwal($tipeList);
    if (is_string($data)) {
        set_flash('error', $data);
        redirect('edit.php?id=' . $id);
    }

    $stmt = conn()->prepare("
        UPDATE jadwal_bel
        SET id_kategori = ?, hari = ?, jam = ?, tipe = ?, id_suara = ?, durasi_bunyi = ?, keterangan = ?, aktif = ?
        WHERE id = ?");
    $stmt->bind_param(
        'iissiisii',
        $data['id_kategori'],
        $data['hari'],
        $data['jam'],
        $data['tipe'],
        $data['id_suara'],
        $data['durasi_bunyi'],
        $data['keterangan'],
        $data['aktif'],
        $id
    );

    if (!$stmt->execute()) {
        set_flash('error', 'Gagal memperbarui jadwal: ' . conn()->error);
        redirect('edit.php?id=' . $id);
    }

    generate_pengumuman($data['keterangan']);

    set_flash('success', 'Jadwal bel berhasil diperbarui.');
    redirect(urlIndex($data['id_kategori']));
}

/** Hapus satu jadwal. */
function aksiHapus(): void {
    $id       = (int) ($_POST['id'] ?? 0);
    $kategori = (int) ($_POST['kategori'] ?? 0);
    if ($id <= 0) {
        set_flash('error', 'ID jadwal tidak valid.');
        redirect('index.php');
    }

    $stmt = conn()->prepare("DELETE FROM jadwal_bel WHERE id = ?");
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        set_flash('error', 'Gagal menghapus jadwal: ' . conn()->error);
        redirect(urlIndex($kategori));
    }

    if ($stmt->affected_rows > 0) {
        set_flash('success', 'Jadwal bel berhasil dihapus.');
    } else {
        set_flash('error', 'Jadwal tidak ditemukan.');
    }
    redirect(urlIndex($kategori));
}

/** Toggle status aktif/nonaktif satu jadwal. */
function aksiToggle(): void {
    $id       = (int) ($_POST['id'] ?? 0);
    $kategori = (int) ($_POST['kategori'] ?? 0);
    if ($id <= 0) {
        set_flash('error', 'ID jadwal tidak valid.');
        redirect('index.php');
    }

    $stmt = conn()->prepare("UPDATE jadwal_bel SET aktif = 1 - aktif WHERE id = ?");
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        set_flash('error', 'Gagal mengubah status jadwal: ' . conn()->error);
        redirect(urlIndex($kategori));
    }

    if ($stmt->affected_rows > 0) {
        set_flash('success', 'Status aktif jadwal diperbarui.');
    } else {
        set_flash('error', 'Jadwal tidak ditemukan.');
    }
    redirect(urlIndex($kategori));
}

$aksi = $_POST['aksi'] ?? '';
switch ($aksi) {
    case 'tambah':
        aksiTambah($tipeList);
        break;
    case 'edit':
        aksiEdit($tipeList);
        break;
    case 'hapus':
        aksiHapus();
        break;
    case 'toggle':
        aksiToggle();
        break;
    default:
        set_flash('error', 'Aksi tidak dikenali.');
        redirect('index.php');
}
