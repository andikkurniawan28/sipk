<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

// Kolom untuk sorting sesuai urutan pada tabel index
$columns = [
    0 => 'ppl.id',
    1 => 'ppl.tanggal',
    2 => 'ppl.kode',
    3 => 'pl.kode',
    4 => 'g.nama',
    5 => 'u.nama',
    7 => 'ppl.total',
    8 => 'ppl.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'ppl.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (ppl.kode LIKE '%$safeSearch%' OR pl.kode LIKE '%$safeSearch%' OR g.nama LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM penerimaan_pembelian_langsung ppl");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM penerimaan_pembelian_langsung ppl 
    JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
    JOIN gudang g ON g.id = ppl.gudang_id
    JOIN `user` u ON u.id = ppl.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT ppl.id, ppl.tanggal, ppl.kode AS kode_penerimaan, ppl.total, ppl.timestamp,
               pl.kode AS kode_pembelian, g.nama AS nama_gudang, u.nama AS nama_admin 
        FROM penerimaan_pembelian_langsung ppl 
        JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
        JOIN gudang g ON g.id = ppl.gudang_id
        JOIN `user` u ON u.id = ppl.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $penerimaan_id = $row['id'];

    // Ambil rincian produk & qty untuk penerimaan ini dari tabel detail penerimaan
    $detailQuery = $conn->query("
        SELECT pr.nama AS nama_produk, pr.satuan, pr.kode, ppld.qty 
        FROM penerimaan_pembelian_langsung_detail ppld 
        JOIN produk pr ON ppld.produk_id = pr.id 
        WHERE ppld.penerimaan_pembelian_langsung_id = $penerimaan_id
    ");

    $htmlList = '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">';
    while ($det = $detailQuery->fetch_assoc()) {
        $htmlList .= '<li>' . h($det['kode']) . ' - ' . h($det['nama_produk']) . ' <span class="text-muted">(' . rp($det['qty']) . ' ' . h($det['satuan']) . ')</span></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '
        <a href="penerimaan_pembelian_langsung_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat Detail">
            <i class="fas fa-eye"></i>
        </a>
        <a href="penerimaan_pembelian_langsung_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_penerimaan']),
        h($row['kode_pembelian']),
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