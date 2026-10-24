<?php
$page_title = "Tambah Penerimaan";
include 'config.php';
require_once 'penerimaan_helper.php';

$penerimaan = [];
$detail     = [];

if (!empty($_SESSION['sipk-old-penerimaan'])) {
    [$penerimaan, $detail] = penerimaan_dari_old($_SESSION['sipk-old-penerimaan']);
    unset($_SESSION['sipk-old-penerimaan']);
} else {
    // Generate kode otomatis jika belum ada data old input
    $penerimaan['kode'] = generate_kode_penerimaan($conn);
}

$form_action  = 'penerimaan_simpan.php';
$label_tombol = 'Simpan';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'penerimaan_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>