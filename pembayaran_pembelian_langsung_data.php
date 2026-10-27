<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

// Definisi indeks kolom untuk sorting (sesuaikan dengan urutan tabel di index)
$columns = [
    0 => 'ppbl.id',
    1 => 'ppbl.tanggal',
    2 => 'ppbl.kode',
    3 => 'ppl.kode',
    4 => 'pl.kode',
    5 => 'a.nama',
    6 => 'u.nama',
    7 => 'ppbl.total',
    8 => 'ppbl.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'ppbl.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (ppbl.kode LIKE '%$safeSearch%' OR ppl.kode LIKE '%$safeSearch%' OR pl.kode LIKE '%$safeSearch%' OR a.nama LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM pembayaran_pembelian_langsung ppbl");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM pembayaran_pembelian_langsung ppbl 
    JOIN penerimaan_pembelian_langsung ppl ON ppl.id = ppbl.penerimaan_pembelian_langsung_id
    JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
    JOIN akun a ON a.id = ppbl.akun_id
    JOIN `user` u ON u.id = ppbl.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT ppbl.id, ppbl.tanggal, ppbl.kode AS kode_pembayaran, ppbl.total, ppbl.timestamp,
               ppl.kode AS kode_penerimaan, pl.kode AS kode_pembelian, a.nama AS nama_akun, u.nama AS nama_admin 
        FROM pembayaran_pembelian_langsung ppbl 
        JOIN penerimaan_pembelian_langsung ppl ON ppl.id = ppbl.penerimaan_pembelian_langsung_id
        JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
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
        <a href="pembayaran_pembelian_langsung_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_pembayaran']),
        h($row['kode_penerimaan']),
        h($row['kode_pembelian']),
        h($row['nama_akun']),
        h($row['nama_admin']),
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