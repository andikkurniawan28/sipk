<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$penerimaan_id = (int)($_GET['id'] ?? 0);

if ($penerimaan_id <= 0) {
    echo json_encode(['success' => false, 'total' => 0]);
    exit;
}

// Hitung total dari qty penerimaan dikalikan harga asli pembelian
$stmt = $conn->prepare("
    SELECT SUM(ppld.qty * pld.harga) AS total_penerimaan
    FROM penerimaan_pembelian_langsung ppl
    JOIN penerimaan_pembelian_langsung_detail ppld ON ppld.penerimaan_pembelian_langsung_id = ppl.id
    JOIN pembelian_langsung_detail pld ON pld.pembelian_langsung_id = ppl.pembelian_langsung_id AND pld.produk_id = ppld.produk_id
    WHERE ppl.id = ?
");
$stmt->bind_param('i', $penerimaan_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total = (float)($res['total_penerimaan'] ?? 0);

echo json_encode(['success' => true, 'total' => $total]);