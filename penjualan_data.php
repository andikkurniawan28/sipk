<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

// Kolom untuk sorting sesuai urutan tabel index
$columns = [
    0 => 'pn.id',
    1 => 'pn.tanggal',
    2 => 'pn.kode',
    3 => 'plg.nama',
    4 => 'u.nama',
    6 => 'pn.grand_total',
    7 => 'pn.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'pn.tanggal';

// Base Query Filter pencarian
$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (pn.kode LIKE '%$safeSearch%' OR plg.nama LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

// Total records keseluruhan
$totalQuery = $conn->query("SELECT COUNT(*) as total FROM penjualan pn");
$totalRecords = $totalQuery->fetch_assoc()['total'];

// Total records setelah filter
$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM penjualan pn 
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id 
    JOIN `user` u ON u.id = pn.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

// Data query utama
$sql = "SELECT pn.id, pn.tanggal, pn.kode, pn.grand_total, pn.timestamp,
               plg.nama AS nama_pelanggan, u.nama AS nama_admin 
        FROM penjualan pn 
        JOIN pelanggan plg ON plg.id = pn.pelanggan_id 
        JOIN `user` u ON u.id = pn.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $penjualan_id = $row['id'];

    // Ambil rincian produk & qty untuk penjualan ini
    $detailQuery = $conn->query("
        SELECT pr.nama AS nama_produk, pr.satuan, pr.kode AS kode_produk, pnd.qty 
        FROM penjualan_detail pnd 
        JOIN produk pr ON pnd.produk_id = pr.id 
        WHERE pnd.penjualan_id = $penjualan_id
    ");

    $htmlList = '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">';
    while ($det = $detailQuery->fetch_assoc()) {
        $htmlList .= '<li>' . h($det['kode_produk']) . ' - ' . h($det['nama_produk']) . ' <span class="text-muted">(' . rp($det['qty']) . ' ' . h($det['satuan']) . ')</span></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '
        <a href="penjualan_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat Detail">
            <i class="fas fa-eye"></i>
        </a>
        <a href="penjualan_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode']),
        h($row['nama_pelanggan']),
        h($row['nama_admin']),
        $htmlList,
        rp($row['grand_total']),
        h($row['timestamp']),
        $aksi
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
]);