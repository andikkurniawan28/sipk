<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

$columns = [
    0 => 'pb.id',
    1 => 'pb.tanggal',
    2 => 'pb.kode',
    3 => 'g.nama',
    4 => 'u.nama',
    6 => 'pb.total',
    7 => 'pb.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'pb.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (pb.kode LIKE '%$safeSearch%' OR g.nama LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM pengambilan_bahan pb");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM pengambilan_bahan pb 
    JOIN gudang g ON g.id = pb.gudang_id
    JOIN `user` u ON u.id = pb.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT pb.id, pb.tanggal, pb.kode AS kode_pengambilan, pb.total, pb.timestamp,
               g.nama AS nama_gudang, u.nama AS nama_admin 
        FROM pengambilan_bahan pb 
        JOIN gudang g ON g.id = pb.gudang_id
        JOIN `user` u ON u.id = pb.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $pengambilan_id = $row['id'];

    $detailQuery = $conn->query("
        SELECT pr.nama AS nama_produk, pr.satuan, pbd.qty 
        FROM pengambilan_bahan_detail pbd 
        JOIN produk pr ON pbd.produk_id = pr.id 
        WHERE pbd.pengambilan_bahan_id = $pengambilan_id
    ");

    $htmlList = '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">';
    while ($det = $detailQuery->fetch_assoc()) {
        $htmlList .= '<li>' . h($det['nama_produk']) . ' <span class="text-muted">(' . rp($det['qty']) . ' ' . h($det['satuan']) . ')</span></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '
        <a href="pengambilan_bahan_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat">
            <i class="fas fa-eye"></i>
        </a>
        <a href="pengambilan_bahan_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_pengambilan']),
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