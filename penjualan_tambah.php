<?php
include 'config.php';
require_once 'penjualan_helper.php';

$tanggal = date('Y-m-d');

$penjualan = [
    'tanggal'   => $tanggal,
    'kode'      => penjualan_kode_baru($conn, $tanggal),
    'pelanggan_id' => '',
    'gudang_id' => '',
    'pajak'     => '0',
    'ongkir'    => '0',
    'biaya'     => '0',
    'rafaksi'   => '0',
    'diskon'    => '0',
];
$detail = [];

// Jika simpan sebelumnya gagal validasi, isi form dikembalikan
if (!empty($_SESSION['sipk-old-penjualan']) && empty($_SESSION['sipk-old-penjualan']['id'])) {
    [$penjualanLama, $detailLama] = penjualan_dari_old($_SESSION['sipk-old-penjualan']);
    $penjualan  = array_merge($penjualan, $penjualanLama);
    $detail = $detailLama;
}
unset($_SESSION['sipk-old-penjualan']);

$page_title   = "Tambah Penjualan";
$form_action  = 'penjualan_simpan.php';
$label_tombol = 'Simpan';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'penjualan_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>