<?php
$page_title = "Tambah Pengeluaran";
include 'config.php';
require_once 'pengeluaran_helper.php';

$pengeluaran = [];
$detail     = [];

if (!empty($_SESSION['sipk-old-pengeluaran'])) {
    [$pengeluaran, $detail] = pengeluaran_dari_old($_SESSION['sipk-old-pengeluaran']);
    unset($_SESSION['sipk-old-pengeluaran']);
} else {
    // Generate kode otomatis jika belum ada data old input
    $pengeluaran['kode'] = generate_kode_pengeluaran($conn);
}

$form_action  = 'pengeluaran_simpan.php';
$label_tombol = 'Simpan';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'pengeluaran_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>