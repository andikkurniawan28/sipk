<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

// Ambil detail produk dari pembelian langsung beserta produk_id, qty, harga, total, nama, dan satuan
$stmt = $conn->prepare("
    SELECT d.produk_id, d.qty, d.harga, d.total, pr.nama AS nama_produk, pr.satuan
    FROM pembelian_langsung_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.pembelian_langsung_id = ?
    ORDER BY d.id ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$detail = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($detail)) {
    echo json_encode(['success' => true, 'data' => $detail]);
} else {
    echo json_encode(['success' => false, 'data' => []]);
}