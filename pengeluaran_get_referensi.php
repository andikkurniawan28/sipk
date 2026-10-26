<?php
include 'config.php';
header('Content-Type: application/json; charset=utf-8');

$sumber   = trim($_GET['sumber'] ?? 'preparasi');
$currentId = (int)($_GET['exclude_id'] ?? 0);
$data     = [];

try {
    if ($sumber === 'penjualan') {
        // Ambil referensi penjualan yang belum digunakan di pengeluaran lain
        $stmt = $conn->prepare("
            SELECT p.id, p.kode, p.tanggal, pl.nama AS nama_pelanggan
            FROM penjualan p 
            JOIN pelanggan pl ON pl.id = p.pelanggan_id
            WHERE p.id NOT IN (
                SELECT COALESCE(penjualan_id, 0) 
                FROM pengeluaran 
                WHERE penjualan_id IS NOT NULL AND id != ?
            )
            ORDER BY p.tanggal DESC
        ");
        $stmt->bind_param('i', $currentId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                'id'    => (int)$row['id'],
                'label' => $row['kode'] . ' - ' . $row['nama_pelanggan'] . ' (' . date('d-m-Y', strtotime($row['tanggal'])) . ')'
            ];
        }
        $stmt->close();
    } else {
        // Default: Ambil referensi preparasi yang belum digunakan di pengeluaran lain
        $stmt = $conn->prepare("
            SELECT p.id, p.kode, p.tanggal 
            FROM preparasi p 
            WHERE p.id NOT IN (
                SELECT COALESCE(preparasi_id, 0) 
                FROM pengeluaran 
                WHERE preparasi_id IS NOT NULL AND id != ?
            )
            ORDER BY p.tanggal DESC
        ");
        $stmt->bind_param('i', $currentId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                'id'    => (int)$row['id'],
                'label' => $row['kode'] . ' (' . date('d-m-Y', strtotime($row['tanggal'])) . ')'
            ];
        }
        $stmt->close();
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);