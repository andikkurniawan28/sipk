<?php
include 'config.php';
require_once 'pengolahan_helper.php';

$tanggal = date('Y-m-d');

$pengolahan = [
    'tanggal'    => $tanggal,
    'kode'       => pg_kode_baru($conn, $tanggal),
    'keterangan' => '',
];
$detail_in  = [];
$detail_out = [];

// Jika simpan sebelumnya gagal validasi, isi form dikembalikan
if (!empty($_SESSION['sipk-old-pengolahan']) && empty($_SESSION['sipk-old-pengolahan']['id'])) {
    [$pengolahanLama, $inLama, $outLama] = pg_dari_old($_SESSION['sipk-old-pengolahan']);
    $pengolahan = array_merge($pengolahan, $pengolahanLama);
    $detail_in  = $inLama;
    $detail_out = $outLama;
}
unset($_SESSION['sipk-old-pengolahan']);

$page_title   = "Tambah Pengolahan";
$form_action  = 'pengolahan_simpan.php';
$label_tombol = 'Simpan';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'pengolahan_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>