<?php
include 'config.php';
require_once 'panen_helper.php';

$tanggal = date('Y-m-d');

$panen = [
    'tanggal'   => $tanggal,
    'kode'      => panen_kode_baru($conn, $tanggal),
    'petani_id' => '',
    'gudang_id' => '',
    'pajak'     => '0',
    'ongkir'    => '0',
    'biaya'     => '0',
    'rafaksi'   => '0',
    'diskon'    => '0',
];
$detail = [];

// Jika simpan sebelumnya gagal validasi, isi form dikembalikan
if (!empty($_SESSION['sipk-old-panen']) && empty($_SESSION['sipk-old-panen']['id'])) {
    [$panenLama, $detailLama] = panen_dari_old($_SESSION['sipk-old-panen']);
    $panen  = array_merge($panen, $panenLama);
    $detail = $detailLama;
}
unset($_SESSION['sipk-old-panen']);

$page_title   = "Tambah Panen";
$form_action  = 'panen_simpan.php';
$label_tombol = 'Simpan';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'panen_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>