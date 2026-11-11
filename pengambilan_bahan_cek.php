<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$kode = strtoupper(trim($_GET['kode'] ?? ''));

if ($kode === '') {
    echo json_encode(['success' => false, 'pesan' => 'Nomor kosong.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, kode, tanggal FROM pengambilan_bahan WHERE kode = ? LIMIT 1");
$stmt->bind_param('s', $kode);
$stmt->execute();
$pb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pb) {
    echo json_encode(['success' => false, 'pesan' => 'Nomor pengambilan bahan tidak ditemukan.']);
    exit;
}

echo json_encode([
    'success' => true,
    'kode'    => $pb['kode'],
    'tanggal' => tanggal_indo($pb['tanggal']),
]);