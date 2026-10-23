<?php
include 'config.php';
require_once 'penjualan_helper.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT id, tanggal, kode, pelanggan_id, gudang_id, pajak, ongkir, biaya, rafaksi, diskon
    FROM penjualan WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$penjualan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$penjualan) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data penjualan tidak ada.'];
    header('Location: penjualan_index.php');
    exit;
}

$stmt = $conn->prepare("SELECT produk_id, qty, harga FROM penjualan_detail WHERE penjualan_id = ? ORDER BY id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Jika update sebelumnya gagal validasi untuk penjualan ini, isi form dikembalikan
if (!empty($_SESSION['sipk-old-penjualan']) && (int)($_SESSION['sipk-old-penjualan']['id'] ?? 0) === $id) {
    [$penjualanLama, $detailLama] = penjualan_dari_old($_SESSION['sipk-old-penjualan']);
    $penjualan  = array_merge($penjualan, $penjualanLama);
    $detail = $detailLama;
}
unset($_SESSION['sipk-old-penjualan']);

$page_title   = "Edit Penjualan";
$form_action  = 'penjualan_update.php';
$label_tombol = 'Update';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'penjualan_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>