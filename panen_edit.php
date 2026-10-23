<?php
include 'config.php';
require_once 'panen_helper.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT id, tanggal, kode, petani_id, gudang_id, pajak, ongkir, biaya, rafaksi, diskon
    FROM panen WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$panen = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$panen) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data panen tidak ada.'];
    header('Location: panen_index.php');
    exit;
}

$stmt = $conn->prepare("SELECT produk_id, qty, harga FROM panen_detail WHERE panen_id = ? ORDER BY id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Jika update sebelumnya gagal validasi untuk panen ini, isi form dikembalikan
if (!empty($_SESSION['sipk-old-panen']) && (int)($_SESSION['sipk-old-panen']['id'] ?? 0) === $id) {
    [$panenLama, $detailLama] = panen_dari_old($_SESSION['sipk-old-panen']);
    $panen  = array_merge($panen, $panenLama);
    $detail = $detailLama;
}
unset($_SESSION['sipk-old-panen']);

$page_title   = "Edit Panen";
$form_action  = 'panen_update.php';
$label_tombol = 'Update';

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php include 'panen_form.php'; ?>

<?php
include 'footer.php';
include 'scripts.php';
?>