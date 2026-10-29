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
    5 => 'a.nama',
    6 => 'u.nama',
    7 => 'pp.total'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'pp.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (pp.kode LIKE '%$safeSearch%' OR pn.kode LIKE '%$safeSearch%' OR plg.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM pembayaran_penjualan pp");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM pembayaran_penjualan pp 
    JOIN penjualan pn ON pn.id = pp.penjualan_id
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id
    JOIN akun a ON a.id = pp.akun_id
    JOIN `user` u ON u.id = pp.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT pp.id, pp.tanggal, pp.kode, pp.total,
               pn.kode AS kode_penjualan, plg.nama AS nama_pelanggan, a.nama AS nama_akun, u.nama AS nama_admin 
        FROM pembayaran_penjualan pp 
        JOIN penjualan pn ON pn.id = pp.penjualan_id
        JOIN pelanggan plg ON plg.id = pn.pelanggan_id
        JOIN akun a ON a.id = pp.akun_id
        JOIN `user` u ON u.id = pp.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $aksi = '
        <a href="pembayaran_penjualan_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat">
            <i class="fas fa-eye"></i>
        </a>
        <a href="pembayaran_penjualan_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode']),
        h($row['kode_penjualan']),
        h($row['nama_pelanggan']),
        h($row['nama_akun']),
        h($row['nama_admin']),
        rp($row['total']),
        $aksi
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
]);