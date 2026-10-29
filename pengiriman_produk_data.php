<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

$columns = [
    0 => 'pp.id',
    1 => 'pp.tanggal',
    2 => 'pp.kode',
    3 => 'pn.kode',
    4 => 'plg.nama',
    5 => 'g.nama',
    6 => 'u.nama',
    7 => 'pp.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'pp.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (pp.kode LIKE '%$safeSearch%' OR pn.kode LIKE '%$safeSearch%' OR plg.nama LIKE '%$safeSearch%' OR g.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM pengiriman_produk pp");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM pengiriman_produk pp 
    JOIN penjualan pn ON pn.id = pp.penjualan_id
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id
    JOIN gudang g ON g.id = pp.gudang_id
    JOIN `user` u ON u.id = pp.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT pp.id, pp.tanggal, pp.kode AS kode_do, pn.kode AS kode_penjualan, pp.timestamp,
               plg.nama AS nama_pelanggan, g.nama AS nama_gudang, u.nama AS nama_admin 
        FROM pengiriman_produk pp 
        JOIN penjualan pn ON pn.id = pp.penjualan_id
        JOIN pelanggan plg ON plg.id = pn.pelanggan_id
        JOIN gudang g ON g.id = pp.gudang_id
        JOIN `user` u ON u.id = pp.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $aksi = '
        <a href="pengiriman_produk_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat Surat Jalan">
            <i class="fas fa-eye"></i>
        </a>
        <a href="pengiriman_produk_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_do']),
        h($row['kode_penjualan']),
        h($row['nama_pelanggan']),
        h($row['nama_gudang']),
        h($row['nama_admin']),
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