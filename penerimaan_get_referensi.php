<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['sipk-user_id'])) {
    http_response_code(401);
    echo '[]';
    exit;
}

$exclude = (int)($_GET['exclude_id'] ?? 0);   // id penerimaan yang sedang diedit (0 jika baru)

try {
    // Proposal Disetujui yang belum punya penerimaan (selain penerimaan yang sedang diedit)
    $stmt = $conn->prepare("
        SELECT p.id, p.kode, p.tanggal, pt.nama AS petani
        FROM proposal p
        JOIN petani pt ON pt.id = p.petani_id
        WHERE p.status = 'Disetujui'
          AND NOT EXISTS (
              SELECT 1 FROM penerimaan n
              WHERE n.proposal_id = p.id AND n.id <> ?
          )
        ORDER BY p.tanggal DESC, p.id DESC
    ");
    $stmt->bind_param('i', $exclude);
    $stmt->execute();
    $hasil = $stmt->get_result();

    $data = [];
    while ($r = $hasil->fetch_assoc()) {
        $data[] = [
            'id'    => (int)$r['id'],
            'label' => $r['kode'] . ' - ' . $r['petani'] . ' (' . date('d-m-Y', strtotime($r['tanggal'])) . ')',
        ];
    }
    $stmt->close();

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('penerimaan_get_referensi: ' . $e->getMessage());
    http_response_code(500);
    echo '[]';
}