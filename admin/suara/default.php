<?php
require_once __DIR__ . '/../../core/init.php';

require_admin();
require_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'ID suara tidak valid.');
    redirect('index.php');
}

// Hanya satu suara default: yang dipilih jadi 1, sisanya 0.
$stmt = conn()->prepare("UPDATE suara_bel SET is_default = CASE WHEN id = ? THEN 1 ELSE 0 END");
$stmt->bind_param('i', $id);
$stmt->execute();

set_flash('success', 'Suara default berhasil diperbarui.');
redirect('index.php');
