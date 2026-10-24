<?php
include 'config.php';
header('Content-Type: application/json');

$sumber    = $_GET['sumber'] ?? 'panen';
$currentId = (int)($_GET['exclude_id'] ?? 0);

$data = [];

try {
    if ($sumber === 'panen') {
        // Menggunakan prepared statement yang aman dan menangani kondisi jika tabel kosong / belum ada yang dikecualikan
        $stmt = $conn->prepare("
            SELECT p.id, p.kode, p.tanggal, pt.nama AS nama_petani 
            FROM panen p
            JOIN petani pt ON pt.id = p.petani_id
            WHERE p.id NOT IN (
                SELECT COALESCE(panen_id, 0) 
                FROM penerimaan 
                WHERE panen_id IS NOT NULL AND id != ?
            )
            ORDER BY p.tanggal DESC
        ");
        $stmt->bind_param('i', $currentId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                'id'    => $row['id'],
                'label' => $row['kode'] . ' - ' . $row['nama_petani'] . ' (' . date('d-m-Y', strtotime($row['tanggal'])) . ')'
            ];
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            SELECT og.id, og.kode, og.tanggal 
            FROM pengolahan og
            WHERE og.id NOT IN (
                SELECT COALESCE(pengolahan_id, 0) 
                FROM penerimaan 
                WHERE pengolahan_id IS NOT NULL AND id != ?
            )
            ORDER BY og.tanggal DESC
        ");
        $stmt->bind_param('i', $currentId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                'id'    => $row['id'],
                'label' => $row['kode'] . ' (' . date('d-m-Y', strtotime($row['tanggal'])) . ')'
            ];
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    // Kembalikan format JSON berisi pesan error jika terjadi kendala database
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode($data);