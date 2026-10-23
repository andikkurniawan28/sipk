<?php
include 'config.php';
require_once 'pengolahan_helper.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, tanggal, kode, keterangan FROM pengolahan WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$pengolahan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$pengolahan) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pengolahan tidak ada.'];
    header('Location: pengolahan_index.php');
    exit;
}

$stmt = $conn->prepare("SELECT produk_id, gudang_id, qty, harga FROM input_pengolahan WHERE pengolahan_id = ? ORDER BY id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail_in = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT produk_id, gudang_id, qty, harga FROM output_pengolahan WHERE pengolahan_id = ? ORDER BY id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail_out = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Jika update sebelumnya gagal validasi untuk pengolahan ini, isi form dikembalikan
if (!empty($_SESSION['sipk-old-pengolahan']) && (int)($_SESSION['sipk-old-pengolahan']['id'] ?? 0) === $id) {
    [$pengolahanLama, $inLama, $outLama] = pg_dari_old($_SESSION['sipk-old-pengolahan']);
    $pengolahan = array_merge($pengolahan, $pengolahanLama);
    $detail_in  = $inLama;
    $detail_out = $outLama;
}
unset($_SESSION['sipk-old-pengolahan']);

$page_title   = "Edit Pengolahan";
$form_action  = 'pengolahan_update.php';
$label_tombol = 'Update';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'pengolahan_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>