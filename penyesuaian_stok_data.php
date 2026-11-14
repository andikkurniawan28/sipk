<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$draw        = (int)($_GET['draw'] ?? 1);
$start       = max(0, (int)($_GET['start'] ?? 0));
$length      = (int)($_GET['length'] ?? 10);
if ($length <= 0 || $length > 500) $length = 500;
$searchValue = trim($_GET['search']['value'] ?? '');

// Indeks kolom tabel => kolom SQL (0 = No, 7 = Detail, 9 = Aksi tidak bisa diurutkan)
$columns = [
    1 => 'pb.tanggal',
    2 => 'pb.kode',
    3 => 'g.nama',
    4 => 'pb.jenis',
    5 => 'u.nama',
    6 => 'pb.keterangan',
    8 => 'pb.timestamp',
];
$orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 2);
$orderBy  = $columns[$orderColumnIndex] ?? 'pb.tanggal';
$orderDir = (($_GET['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$dari = "
    FROM penyesuaian_stok pb
    JOIN gudang g ON g.id = pb.gudang_id
    JOIN `user` u ON u.id = pb.user_id
";

$where  = '';
$types  = '';
$params = [];
if ($searchValue !== '') {
    $where  = "WHERE (pb.kode LIKE ? OR g.nama LIKE ? OR u.nama LIKE ? OR pb.keterangan LIKE ?)";
    $like   = '%' . $searchValue . '%';
    $types  = 'ssss';
    $params = [$like, $like, $like, $like];
}

$totalRecords = (int)$conn->query("SELECT COUNT(*) FROM penyesuaian_stok")->fetch_row()[0];

if ($where !== '') {
    $stmt = $conn->prepare("SELECT COUNT(*) $dari $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $filteredRecords = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
} else {
    $filteredRecords = $totalRecords;
}

$sql = "SELECT pb.id, pb.tanggal, pb.kode AS kode_penyesuaian, pb.jenis, pb.keterangan, pb.timestamp,
               g.nama AS nama_gudang, u.nama AS nama_admin
        $dari
        $where
        ORDER BY $orderBy $orderDir, pb.id DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types . 'ii', ...array_merge($params, [$start, $length]));
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Detail produk semua baris halaman ini dalam SATU query (qty saja)
$detailPerPenyesuaian = [];
if ($rows) {
    $daftar = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
    $hasil  = $conn->query("
        SELECT pbd.penyesuaian_stok_id, pr.kode, pr.nama AS nama_produk, pr.satuan, pbd.qty
        FROM penyesuaian_stok_detail pbd
        JOIN produk pr ON pr.id = pbd.produk_id
        WHERE pbd.penyesuaian_stok_id IN ($daftar)
        ORDER BY pbd.id
    ");
    while ($d = $hasil->fetch_assoc()) {
        $detailPerPenyesuaian[(int)$d['penyesuaian_stok_id']][] = $d;
    }
}

$bolehHapus = canAccess('penyesuaian_stok_hapus.php');

$data = [];
$no   = $start + 1;
foreach ($rows as $row) {
    $id = (int)$row['id'];

    $htmlList = '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">';
    foreach ($detailPerPenyesuaian[$id] ?? [] as $det) {
        $htmlList .= '<li>' . h($det['kode']) . ' - ' . h($det['nama_produk'])
                   . ' <span class="text-muted">(' . rp($det['qty']) . ' ' . h($det['satuan']) . ')</span></li>';
    }
    $htmlList .= '</ul>';

    $badgeJenis = ($row['jenis'] === 'tambah')
        ? '<span class="badge badge-success">Tambah</span>'
        : '<span class="badge badge-danger">Kurang</span>';

    $aksi = '<a href="penyesuaian_stok_lihat.php?id=' . $id . '" class="btn btn-info btn-sm" title="Lihat"><i class="fas fa-eye"></i></a>';
    if ($bolehHapus) {
        $aksi .= ' <a href="penyesuaian_stok_hapus.php?id=' . $id . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>';
    }

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_penyesuaian']),
        h($row['nama_gudang']),
        $badgeJenis,
        h($row['nama_admin']),
        h($row['keterangan']),
        $htmlList,
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