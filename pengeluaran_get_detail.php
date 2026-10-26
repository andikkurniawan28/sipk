<?php
include 'config.php';
header('Content-Type: application/json; charset=utf-8');

$sumber = trim($_GET['sumber'] ?? 'preparasi');
$id     = (int)($_GET['id'] ?? 0);
$result = [];

if ($id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($sumber === 'penjualan') {
        $stmt = $conn->prepare("
            SELECT pd.produk_id, pd.qty, pr.nama AS nama_produk, pr.satuan 
            FROM penjualan_detail pd 
            JOIN produk pr ON pr.id = pd.produk_id 
            WHERE pd.penjualan_id = ?
        ");
    } else {
        // Default: sumber preparasi
        $stmt = $conn->prepare("
            SELECT pd.produk_id, pd.qty, pr.nama AS nama_produk, pr.satuan 
            FROM preparasi_detail pd 
            JOIN produk pr ON pr.id = pd.produk_id 
            WHERE pd.preparasi_id = ?
        ");
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $result[] = [
            'produk_id'   => (int)$row['produk_id'],
            'qty'         => (float)$row['qty'],
            'nama_produk' => $row['nama_produk'],
            'satuan'      => $row['satuan']
        ];
    }
    $stmt->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);