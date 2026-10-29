<?php
include 'config.php';
$penjualan_id = (int)($_GET['penjualan_id'] ?? 0);

// Ambil grand total penjualan
$stmt = $conn->prepare("SELECT grand_total FROM penjualan WHERE id = ?");
$stmt->bind_param('i', $penjualan_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo 'Faktur tidak ditemukan.';
    exit;
}

$grand_total = (float)$res['grand_total'];

// Ambil total pembayaran sebelumnya untuk faktur ini
$stmtBayar = $conn->prepare("SELECT COALESCE(SUM(total), 0) AS total_bayar FROM pembayaran_penjualan WHERE penjualan_id = ?");
$stmtBayar->bind_param('i', $penjualan_id);
$stmtBayar->execute();
$total_bayar = (float)$stmtBayar->get_result()->fetch_assoc()['total_bayar'];
$stmtBayar->close();

$sisa = $grand_total - $total_bayar;
$status = ($sisa <= 0) ? '<span class="badge badge-success">LUNAS</span>' : '<span class="badge badge-warning">BELUM LUNAS</span>';
?>
<div class="row">
    <div class="col-md-4"><strong>Total Faktur:</strong> <?= rp($grand_total) ?></div>
    <div class="col-md-4"><strong>Sudah Dibayar:</strong> <?= rp($total_bayar) ?></div>
    <div class="col-md-4"><strong>Sisa Piutang:</strong> <span class="text-danger font-weight-bold"><?= rp($sisa) ?></span> (<?= $status ?>)</div>
</div>