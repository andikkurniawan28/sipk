<?php
include 'config.php';
require_once 'proposal_helper.php';

$tanggal = date('Y-m-d');

$proposal = [
    'tanggal'   => $tanggal,
    'kode'      => proposal_kode_baru($conn, $tanggal),
    'petani_id' => '',
    'pajak'     => '0',
    'ongkir'    => '0',
    'biaya'     => '0',
    'rafaksi'   => '0',
    'diskon'    => '0',
];
$detail = [];

// Jika simpan sebelumnya gagal validasi, isi form dikembalikan
if (!empty($_SESSION['sipk-old-proposal']) && empty($_SESSION['sipk-old-proposal']['id'])) {
    [$proposalLama, $detailLama] = proposal_dari_old($_SESSION['sipk-old-proposal']);
    $proposal  = array_merge($proposal, $proposalLama);
    $detail = $detailLama;
}
unset($_SESSION['sipk-old-proposal']);

$page_title   = "Tambah Proposal";
$form_action  = 'proposal_simpan.php';
$label_tombol = 'Simpan';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'proposal_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>