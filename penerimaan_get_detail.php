<?php
include 'config.php';
header('Content-Type: application/json');

$sumber = $_GET['sumber'] ?? 'panen';
$id     = (int)($_GET['id'] ?? 0);
$result = [];

if ($id <= 0) {
    echo json_encode([]);
    exit;
}

if ($sumber === 'panen') {
    $stmt = $conn->prepare("
        SELECT pd.produk_id, pd.qty, pr.nama AS nama_produk, pr.satuan 
        FROM panen_detail pd 
        JOIN produk pr ON pr.id = pd.produk_id 
        WHERE pd.panen_id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $result[] = $row;
    }
    $stmt->close();
} else {
    // Sumber pengolahan mengambil dari output_pengolahan (barang hasil jadi masuk ke gudang)
    $stmt = $conn->prepare("
        SELECT op.produk_id, op.qty, pr.nama AS nama_produk, pr.satuan 
        FROM output_pengolahan op 
        JOIN produk pr ON pr.id = op.produk_id 
        WHERE op.pengolahan_id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $result[] = $row;
    }
    $stmt->close();
}

echo json_encode($result);