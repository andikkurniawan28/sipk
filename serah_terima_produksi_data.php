<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

$columns = [
    0 => 'stp.id',
    1 => 'stp.tanggal',
    2 => 'stp.kode',
    3 => 'g.nama',
    4 => 'u.nama',
    6 => 'stp.total',
    7 => 'stp.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'stp.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (stp.kode LIKE '%$safeSearch%' OR g.nama LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM serah_terima_produksi stp");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM serah_terima_produksi stp 
    JOIN gudang g ON g.id = stp.gudang_id
    JOIN `user` u ON u.id = stp.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT stp.id, stp.tanggal, stp.kode AS kode_serah, stp.total, stp.timestamp,
               g.nama AS nama_gudang, u.nama AS nama_admin 
        FROM serah_terima_produksi stp 
        JOIN gudang g ON g.id = stp.gudang_id
        JOIN `user` u ON u.id = stp.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $serah_id = $row['id'];

    $detailQuery = $conn->query("
        SELECT pr.nama AS nama_produk, pr.satuan, stpd.qty 
        FROM serah_terima_produksi_detail stpd 
        JOIN produk pr ON stpd.produk_id = pr.id 
        WHERE stpd.serah_terima_produksi_id = $serah_id
    ");

    $htmlList = '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">';
    while ($det = $detailQuery->fetch_assoc()) {
        $htmlList .= '<li>' . h($det['nama_produk']) . ' <span class="text-muted">(' . rp($det['qty']) . ' ' . h($det['satuan']) . ')</span></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '
        <a href="serah_terima_produksi_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat">
            <i class="fas fa-eye"></i>
        </a>
        <a href="serah_terima_produksi_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_serah']),
        h($row['nama_gudang']),
        h($row['nama_admin']),
        $htmlList,
        rp($row['total']),
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