<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['sipk-user_id'])) {
    http_response_code(401);
    echo '[]';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '[]';
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT d.produk_id, d.qty, pr.nama AS nama_produk, pr.satuan
        FROM proposal_detail d
        JOIN proposal p ON p.id = d.proposal_id
        JOIN produk pr  ON pr.id = d.produk_id
        WHERE d.proposal_id = ? AND p.status = 'Disetujui'
        ORDER BY d.id ASC
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($data as &$r) {
        $r['produk_id'] = (int)$r['produk_id'];
        $r['qty']       = (float)$r['qty'];
    }
    unset($r);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('penerimaan_get_detail: ' . $e->getMessage());
    http_response_code(500);
    echo '[]';
}