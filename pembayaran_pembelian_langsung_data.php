<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

$columns = [
    0 => 'ppbl.id',
    1 => 'ppbl.tanggal',
    2 => 'ppbl.kode',
    3 => 'pl.kode',
    4 => 'a.nama',
    5 => 'u.nama',
    6 => 'ppbl.total'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'ppbl.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (ppbl.kode LIKE '%$safeSearch%' OR pl.kode LIKE '%$safeSearch%' OR a.nama LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM pembayaran_pembelian_langsung ppbl");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM pembayaran_pembelian_langsung ppbl 
    JOIN pembelian_langsung pl ON pl.id = ppbl.pembelian_langsung_id
    JOIN akun a ON a.id = ppbl.akun_id
    JOIN `user` u ON u.id = ppbl.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT ppbl.id, ppbl.tanggal, ppbl.kode AS kode_pembayaran, ppbl.total, 
               pl.kode AS kode_pembelian, a.nama AS nama_akun, u.nama AS nama_user 
        FROM pembayaran_pembelian_langsung ppbl 
        JOIN pembelian_langsung pl ON pl.id = ppbl.pembelian_langsung_id
        JOIN akun a ON a.id = ppbl.akun_id
        JOIN `user` u ON u.id = ppbl.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $aksi = '
        <a href="pembayaran_pembelian_langsung_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat Detail">
            <i class="fas fa-eye"></i>
        </a>
        <a href="pembayaran_pembelian_langsung_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Yakin ingin menghapus data pembayaran ini?\')" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_pembayaran']),
        h($row['kode_pembelian']),
        h($row['nama_akun']),
        h($row['nama_user']),
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