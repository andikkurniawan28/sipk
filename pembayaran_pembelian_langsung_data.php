<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$draw   = (int)($_GET['draw'] ?? 1);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 10);
if ($length <= 0 || $length > 500) $length = 500;
$searchValue = trim($_GET['search']['value'] ?? '');

// Indeks kolom sesuai urutan tabel di index (0 = No, 8 = Aksi tidak bisa diurutkan)
$columns = [
    1 => 'ppbl.tanggal',
    2 => 'ppbl.kode',
    3 => 'pl.kode',
    4 => 'a.nama',
    5 => 'u.nama',
    6 => 'ppbl.total',
    7 => 'ppbl.timestamp',
];
$orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 1);
$orderBy  = $columns[$orderColumnIndex] ?? 'ppbl.tanggal';
$orderDir = (($_GET['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$dari = "
    FROM pembayaran_pembelian_langsung ppbl
    JOIN pembelian_langsung pl ON pl.id = ppbl.pembelian_langsung_id
    JOIN akun a ON a.id = ppbl.akun_id
    JOIN `user` u ON u.id = ppbl.user_id
";

$where  = '';
$types  = '';
$params = [];
if ($searchValue !== '') {
    $where  = "WHERE (ppbl.kode LIKE ? OR pl.kode LIKE ? OR a.nama LIKE ? OR u.nama LIKE ?)";
    $like   = '%' . $searchValue . '%';
    $types  = 'ssss';
    $params = [$like, $like, $like, $like];
}

$totalRecords = (int)$conn->query("SELECT COUNT(*) FROM pembayaran_pembelian_langsung")->fetch_row()[0];

if ($where !== '') {
    $stmt = $conn->prepare("SELECT COUNT(*) $dari $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $filteredRecords = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
} else {
    $filteredRecords = $totalRecords;
}

$sql = "SELECT ppbl.id, ppbl.tanggal, ppbl.kode AS kode_pembayaran, ppbl.total, ppbl.timestamp,
               pl.kode AS kode_pembelian, a.nama AS nama_akun, u.nama AS nama_admin
        $dari
        $where
        ORDER BY $orderBy $orderDir, ppbl.id DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types . 'ii', ...array_merge($params, [$start, $length]));
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$bolehHapus = canAccess('pembayaran_pembelian_langsung_hapus.php');

$data = [];
$no = $start + 1;
foreach ($rows as $row) {
    $id   = (int)$row['id'];
    $aksi = '<a href="pembayaran_pembelian_langsung_lihat.php?id=' . $id . '" class="btn btn-info btn-sm" title="Lihat Detail"><i class="fas fa-eye"></i></a>';
    if ($bolehHapus) {
        $aksi .= ' <a href="pembayaran_pembelian_langsung_hapus.php?id=' . $id . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>';
    }

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_pembayaran']),
        h($row['kode_pembelian']),
        h($row['nama_akun']),
        h($row['nama_admin']),
        rp($row['total']),
        h($row['timestamp']),
        $aksi,
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data'            => $data,
]);