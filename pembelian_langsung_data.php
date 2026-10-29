<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

// Kolom untuk sorting sesuai urutan tabel index
$columns = [
    0 => 'pl.id',
    1 => 'pl.tanggal',
    2 => 'pl.kode',
    3 => 'p.nama',
    4 => 'u.nama',
    6 => 'pl.grand_total',
    7 => 'pl.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'pl.tanggal';

// Base Query Filter pencarian
$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (pl.kode LIKE '%$safeSearch%' OR p.nama LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

// Total records keseluruhan
$totalQuery = $conn->query("SELECT COUNT(*) as total FROM pembelian_langsung pl");
$totalRecords = $totalQuery->fetch_assoc()['total'];

// Total records setelah filter
$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM pembelian_langsung pl 
    JOIN pemasok p ON p.id = pl.pemasok_id 
    JOIN `user` u ON u.id = pl.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

// Data query utama (mengambil pl.timestamp)
$sql = "SELECT pl.id, pl.tanggal, pl.kode, pl.grand_total, pl.timestamp,
               p.nama AS nama_pemasok, u.nama AS nama_admin 
        FROM pembelian_langsung pl 
        JOIN pemasok p ON p.id = pl.pemasok_id 
        JOIN `user` u ON u.id = pl.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $pembelian_id = $row['id'];

    // Ambil rincian produk & qty untuk pembelian ini
    $detailQuery = $conn->query("
        SELECT pr.nama AS nama_produk, pr.satuan, pr.kode, pld.qty 
        FROM pembelian_langsung_detail pld 
        JOIN produk pr ON pld.produk_id = pr.id 
        WHERE pld.pembelian_langsung_id = $pembelian_id
    ");

    $htmlList = '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">';
    while ($det = $detailQuery->fetch_assoc()) {
        $htmlList .= '<li>' . h($det['kode']) . ' - ' . h($det['nama_produk']) . ' <span class="text-muted">(' . rp($det['qty']) . ' ' . h($det['satuan']) . ')</span></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '
        <a href="pembelian_langsung_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat Detail">
            <i class="fas fa-eye"></i>
        </a>
        <a href="pembelian_langsung_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode']),
        h($row['nama_pemasok']),
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